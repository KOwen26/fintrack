<?php

namespace Database\Seeders;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            ['name' => 'BCA', 'slug' => 'bca', 'type' => ProviderType::Bank->value],
            ['name' => 'Mandiri', 'slug' => 'mandiri', 'type' => ProviderType::Bank->value],
            ['name' => 'BNI', 'slug' => 'bni', 'type' => ProviderType::Bank->value],
            ['name' => 'BRI', 'slug' => 'bri', 'type' => ProviderType::Bank->value],
            ['name' => 'CIMB Niaga', 'slug' => 'cimb-niaga', 'type' => ProviderType::Bank->value],
            ['name' => 'Jenius', 'slug' => 'jenius', 'type' => ProviderType::DigitalBank->value],
            ['name' => 'GoPay', 'slug' => 'gopay', 'type' => ProviderType::EWallet->value],
            ['name' => 'OVO', 'slug' => 'ovo', 'type' => ProviderType::EWallet->value],
            ['name' => 'Dana', 'slug' => 'dana', 'type' => ProviderType::EWallet->value],
            ['name' => 'ShopeePay', 'slug' => 'shopeepay', 'type' => ProviderType::EWallet->value],
            ['name' => 'LinkAja', 'slug' => 'linkaja', 'type' => ProviderType::EWallet->value],
        ];

        foreach ($providers as $provider) {
            DB::table('providers')->updateOrInsert(
                ['slug' => $provider['slug']],
                [
                    ...$provider,
                    'status' => ProviderStatus::Active->value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
