<?php

namespace Database\Factories;

use App\Enums\FileAccessLevel;
use App\Models\File;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'organization_id' => null,
            'uploaded_by_user_id' => User::factory(),
            'disk' => 'local',
            'path' => 'files/'.$this->faker->uuid().'.txt',
            'original_name' => $this->faker->word().'.txt',
            'mime_type' => 'text/plain',
            'size_bytes' => $this->faker->numberBetween(100, 1000000),
            'access_level' => FileAccessLevel::Private,
            'metadata' => null,
        ];
    }

    /**
     * Set the file access level to app public.
     */
    public function appPublic(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => FileAccessLevel::AppPublic,
        ]);
    }

    /**
     * Set the file as a personal file (no organization).
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => null,
        ]);
    }

    /**
     * Set the file as uploaded by a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_by_user_id' => $user->id,
        ]);
    }

    /**
     * Set the file as belonging to a specific organization.
     */
    public function forOrganization(Organization $org): static
    {
        return $this->state(fn (array $attributes) => [
            'organization_id' => $org->id,
        ]);
    }
}
