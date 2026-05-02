<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class RetranslateProductCommand extends Command
{
    protected $signature = 'products:retranslate {product : Numeric product id from the products table}';

    protected $description = 'Re-run the translation pipeline for a product (writes to product_translations via Google when configured)';

    public function handle(): int
    {
        $id = (int) $this->argument('product');
        $product = Product::query()->with('user')->findOrFail($id);

        $locale = $product->user?->locale ?? config('app.locale', 'en');

        $product->autoTranslate([
            'name' => $product->name,
            'description' => (string) ($product->description ?? ''),
        ], $locale);

        $this->info(sprintf(
            'Translation pipeline triggered for product %d (source locale: %s).',
            $product->id,
            $locale
        ));

        return self::SUCCESS;
    }
}
