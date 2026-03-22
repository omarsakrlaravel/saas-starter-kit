<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Sanctum\PersonalAccessToken;

class ApiKeysTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {
        PersonalAccessToken::query()->delete();
    }
}
