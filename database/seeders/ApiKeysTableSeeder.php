<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Wave\ApiKey;

class ApiKeysTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {
        ApiKey::query()->delete();
    }
}
