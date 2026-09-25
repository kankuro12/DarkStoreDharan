<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'store_name', 'value' => 'DarkStore', 'type' => 'text', 'group' => 'general'],
            ['key' => 'store_icon', 'value' => null, 'type' => 'image', 'group' => 'general'],
            ['key' => 'store_favicon', 'value' => null, 'type' => 'image', 'group' => 'general'],
            ['key' => 'default_meta_title', 'value' => 'DarkStore - Instant Local Fulfillment', 'type' => 'text', 'group' => 'seo'],
            ['key' => 'default_meta_description', 'value' => 'DarkStore delivers groceries and daily essentials in 15-30 minutes directly from neighborhood micro-warehouses.', 'type' => 'textarea', 'group' => 'seo'],
            ['key' => 'default_meta_image', 'value' => null, 'type' => 'image', 'group' => 'seo'],
            ['key' => 'contact_page_content', 'value' => 'Contact us at support@darkstore.np or call 123-456-7890.', 'type' => 'textarea', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
