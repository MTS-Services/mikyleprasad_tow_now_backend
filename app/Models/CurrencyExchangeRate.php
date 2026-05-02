<?php

namespace App\Models;

use App\Enums\CurrencyRateSource;
use App\Services\Currency\ExchangeRateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyExchangeRate extends Model
{
    protected $table = 'currency_rates';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'created_at' => 'datetime',
            'rate' => 'string',
            'source' => CurrencyRateSource::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CurrencyExchangeRate $model): void {
            if ($model->created_at === null) {
                $model->created_at = now();
            }
        });

        static::created(function (CurrencyExchangeRate $model): void {
            app(ExchangeRateService::class)->forgetCacheForPair(
                (int) $model->base_currency_id,
                (int) $model->quote_currency_id
            );
        });
    }

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function quoteCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'quote_currency_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
