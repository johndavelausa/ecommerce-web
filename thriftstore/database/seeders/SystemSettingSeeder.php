<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'logo_path' => 'defaults/logo.png',
            'background_path' => 'defaults/background.jpg',
            'gcash_qr_path' => 'defaults/gcash-qr.png',
            'gcash_number' => '09XXXXXXXXX',
        ];

        foreach ($defaults as $key => $value) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
