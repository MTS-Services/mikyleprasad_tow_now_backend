<?php

namespace App\Jobs;

use App\Services\Translation\TranslationOrchestrator;
use App\Traits\HasTranslations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Async translation job.
 *
 * Dispatched automatically by $model->autoTranslate() when queue is enabled.
 * You can also dispatch it manually:
 *
 *   TranslateModelJob::dispatch($product, ['name' => '...', 'description' => '...']);
 */
class TranslateModelJob implements ShouldQueue
{
    use Queueable;

    /** Retry up to 3 times on transient Google API errors */
    public int $tries = 3;

    /** Exponential-style back-off between retries (seconds) */
    public array $backoff = [10, 30, 60];

    /** Max seconds the job may run before being killed */
    public int $timeout = 90;

    /**
     * @param  Model&HasTranslations  $model
     * @param  array<string, string>  $sourceData
     */
    public function __construct(
        public readonly Model $model,
        public readonly array $sourceData,
        public readonly ?string $sourceLocale = null,
        public ?string $modelUpdatedAtSnapshot = null,
    ) {
        $this->onQueue(config('translation.queue.name', 'translations'));
        $this->onConnection(config('translation.queue.connection', 'default'));

        if ($this->modelUpdatedAtSnapshot === null) {
            $this->modelUpdatedAtSnapshot = $this->model->updated_at?->toIso8601String();
        }
    }

    public function handle(TranslationOrchestrator $orchestrator): void
    {
        Log::info('Translating model', ['model' => $this->model, 'sourceData' => $this->sourceData, 'sourceLocale' => $this->sourceLocale]);
        $orchestrator->handle(
            model: $this->model,
            sourceData: $this->sourceData,
            sourceLocale: $this->sourceLocale,
            modelUpdatedAtSnapshot: $this->modelUpdatedAtSnapshot
        );
    }

    /** Horizon / Telescope tags for observability */
    public function tags(): array
    {
        return [
            'translation',
            class_basename($this->model),
            'id:'.$this->model->getKey(),
        ];
    }
}
