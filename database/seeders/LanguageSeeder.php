<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    /**
     * Idempotent — safe to run multiple times via `php artisan db:seed --class=LanguageSeeder`.
     * Uses upsert so existing rows are updated, new rows are inserted.
     *
     * Add or remove locales to match your product's requirements.
     * Set is_active = false to disable auto-translation for a locale.
     */
    public function run(): void
    {
        $now = now();

        // $languages = [
        //     // is_default = true  →  this is the source/native language of the app
        //     ['locale' => 'en',    'name' => 'English',              'native_name' => 'English',           'country_code' => 'US', 'is_rtl' => false, 'is_active' => true,  'is_default' => true,  'sort_order' => 0],
        //     ['locale' => 'ar',    'name' => 'Arabic',               'native_name' => 'العربية',            'country_code' => 'SA', 'is_rtl' => true,  'is_active' => true,  'is_default' => false, 'sort_order' => 1],
        //     ['locale' => 'fr',    'name' => 'French',               'native_name' => 'Français',           'country_code' => 'FR', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 2],
        //     ['locale' => 'de',    'name' => 'German',               'native_name' => 'Deutsch',            'country_code' => 'DE', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 3],
        //     ['locale' => 'es',    'name' => 'Spanish',              'native_name' => 'Español',            'country_code' => 'ES', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 4],
        //     ['locale' => 'zh-CN', 'name' => 'Chinese (Simplified)', 'native_name' => '中文（简体）',        'country_code' => 'CN', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 5],
        //     ['locale' => 'ja',    'name' => 'Japanese',             'native_name' => '日本語',             'country_code' => 'JP', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 6],
        //     ['locale' => 'ko',    'name' => 'Korean',               'native_name' => '한국어',             'country_code' => 'KR', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 7],
        //     ['locale' => 'tr',    'name' => 'Turkish',              'native_name' => 'Türkçe',             'country_code' => 'TR', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 8],
        //     ['locale' => 'pt',    'name' => 'Portuguese',           'native_name' => 'Português',          'country_code' => 'PT', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 9],
        //     ['locale' => 'ru',    'name' => 'Russian',              'native_name' => 'Русский',            'country_code' => 'RU', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 10],
        //     ['locale' => 'hi',    'name' => 'Hindi',                'native_name' => 'हिन्दी',             'country_code' => 'IN', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 11],
        //     ['locale' => 'bn',    'name' => 'Bengali',              'native_name' => 'বাংলা',              'country_code' => 'BD', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 12],
        //     ['locale' => 'ur',    'name' => 'Urdu',                 'native_name' => 'اردو',               'country_code' => 'PK', 'is_rtl' => true,  'is_active' => true,  'is_default' => false, 'sort_order' => 13],
        //     ['locale' => 'id',    'name' => 'Indonesian',           'native_name' => 'Bahasa Indonesia',   'country_code' => 'ID', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 14],
        //     ['locale' => 'ms',    'name' => 'Malay',                'native_name' => 'Bahasa Melayu',      'country_code' => 'MY', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 15],
        // ];

        // Set Language right now only english, arabic, hebrew and spanish
        $languages = [
            ['locale' => 'en',    'name' => 'English',              'native_name' => 'English',           'country_code' => 'US', 'is_rtl' => false, 'is_active' => true,  'is_default' => true,  'sort_order' => 0],
            ['locale' => 'ar',    'name' => 'Arabic',               'native_name' => 'العربية',            'country_code' => 'SA', 'is_rtl' => true,  'is_active' => true,  'is_default' => false, 'sort_order' => 1],
            // ['locale' => 'es',    'name' => 'Spanish',              'native_name' => 'Español',            'country_code' => 'ES', 'is_rtl' => false, 'is_active' => true,  'is_default' => false, 'sort_order' => 2],
            ['locale' => 'he',    'name' => 'Hebrew',               'native_name' => 'עברית',              'country_code' => 'IL', 'is_rtl' => true,  'is_active' => true,  'is_default' => false, 'sort_order' => 3],
            ['locale' => 'zh-cn', 'name' => 'Chinese (Simplified)', 'native_name' => '简体中文', 'country_code' => 'CN', 'is_rtl' => false, 'is_active' => true, 'is_default' => false, 'sort_order' => 4],
        ];

        $rows = array_map(fn ($row) => array_merge($row, [
            'created_at' => $now,
            'updated_at' => $now,
        ]), $languages);

        DB::table('languages')->upsert(
            $rows,
            uniqueBy: ['locale'],
            update: ['name', 'native_name', 'country_code', 'is_rtl', 'is_active', 'is_default', 'sort_order', 'updated_at'],
        );

        // Bust the language cache so Language::allActive() returns fresh data
        Language::clearCache();

        $this->command->info('Languages seeded ('.count($languages).' locales).');
    }
}
