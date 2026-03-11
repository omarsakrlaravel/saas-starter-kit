<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('users')->delete();

        DB::table('users')->insert([
            0 => [
                'name' => 'Wave Admin',
                'email' => 'admin@demo.com',
                'username' => 'admin',
                'avatar' => 'demo/default.png',
                'password' => Hash::make('admin'),
                'email_verified_at' => '2017-11-21 16:07:22',
                'remember_token' => '4oXDVo48Lm1pc4j7NkWI9cMO4hv5OIEJFMrqjSCKQsIwWMGRFYDvNpdioBfo',
                'created_at' => '2017-11-21 16:07:22',
                'updated_at' => '2018-09-22 23:34:02',
                'trial_ends_at' => null,
                'verification_code' => null,
                'verified' => 1,
            ],
        ]);

    }
}
