<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('roles')->delete();

        DB::table('roles')->insert([
            0 => [
                'id' => 1,
                'guard_name' => 'web',
                'name' => 'admin',
                'description' => 'The admin user has full access to all features including the ability to access the admin panel.',
                'created_at' => '2017-11-21 16:23:22',
                'updated_at' => '2017-11-21 16:23:22',
            ],
        ]);

    }
}
