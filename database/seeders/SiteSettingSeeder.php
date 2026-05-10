<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteSetting::create([
            'id' => 1,
            'site_email' => 'admin@townow.com',
            'site_phone' => '+1234567890',
            'site_address' => '123 Main St, Anytown, USA',
        ]);
    }
}
