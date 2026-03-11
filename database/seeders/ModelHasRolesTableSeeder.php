<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModelHasRolesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('model_has_roles')->delete();

        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        $adminUser = DB::table('users')->where('email', 'admin@demo.com')->first();

        if ($adminRole && $adminUser) {
            DB::table('model_has_roles')->insert([
                0 => [
                    'role_id' => $adminRole->id,
                    'model_type' => 'user',
                    'model_id' => $adminUser->id,
                ],
            ]);
        }

    }
}
