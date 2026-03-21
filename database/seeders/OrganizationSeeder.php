<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OrganizationSeeder extends Seeder
{
    /**
     * Seed a test organization with an owner, members, and an active subscription.
     */
    public function run(): void
    {
        $now = now();
        $password = Hash::make('password');

        // Create the owner user
        $ownerId = DB::table('users')->insertGetId([
            'name' => 'Org Owner',
            'email' => 'owner@test.com',
            'username' => 'orgowner',
            'avatar' => 'demo/default.png',
            'password' => $password,
            'email_verified_at' => $now,
            'verified' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create member users
        $member1Id = DB::table('users')->insertGetId([
            'name' => 'Member One',
            'email' => 'member1@test.com',
            'username' => 'member1',
            'avatar' => 'demo/default.png',
            'password' => $password,
            'email_verified_at' => $now,
            'verified' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $member2Id = DB::table('users')->insertGetId([
            'name' => 'Member Two',
            'email' => 'member2@test.com',
            'username' => 'member2',
            'avatar' => 'demo/default.png',
            'password' => $password,
            'email_verified_at' => $now,
            'verified' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Create the organization (boot method auto-adds owner as member)
        $organization = Organization::firstOrCreate(
            ['slug' => 'acme-inc'],
            [
                'name' => 'Acme Inc',
                'owner_user_id' => $ownerId,
            ],
        );

        // Add members to the organization
        $organization->members()->attach([
            $member1Id => [
                'role' => 'member',
                'status' => 'active',
                'invited_by' => $ownerId,
                'invited_at' => $now,
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $member2Id => [
                'role' => 'member',
                'status' => 'active',
                'invited_by' => $ownerId,
                'invited_at' => $now,
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // Create active subscription for the organization (Premium plan, 5 seats)
        $premiumPlan = DB::table('plans')->where('name', 'Premium')->first();

        DB::table('subscriptions')->insert([
            'user_id' => $ownerId,
            'type' => 'default',
            'billable_type' => 'organization',
            'billable_id' => $organization->id,
            'plan_id' => $premiumPlan->id,
            'stripe_id' => 'sub_seed_'.uniqid(),
            'stripe_status' => 'active',
            'stripe_price' => 'price_seed_monthly',
            'cycle' => 'month',
            'quantity' => 5,
            'last_payment_at' => $now,
            'next_payment_at' => now()->addMonth(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Set current_organization_id for all org members
        DB::table('users')
            ->whereIn('id', [$ownerId, $member1Id, $member2Id])
            ->update(['current_organization_id' => $organization->id]);

        // Assign 'registered' role to all seeded users
        $registeredRole = DB::table('roles')->where('name', 'registered')->first();
        if ($registeredRole) {
            $entries = collect([$ownerId, $member1Id, $member2Id])->map(fn ($userId) => [
                'role_id' => $registeredRole->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $userId,
            ])->all();

            DB::table('model_has_roles')->insert($entries);
        }
    }
}
