<?php

namespace Database\Factories;

use App\Enums\ImportStatusEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Import>
 */
class ImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fileName = $this->faker->unique()->slug(2).'.xlsx';

        return [
            'file_name' => $fileName,
            'file_hash' => hash('sha256', $fileName.$this->faker->uuid()),
            'status' => ImportStatusEnum::PENDING,
            'row_errors' => null,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatusEnum::PROCESSING,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatusEnum::COMPLETED,
            'row_errors' => [],
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatusEnum::FAILED,
        ]);
    }

    /**
     * Pin the file hash so a test can re-upload "the same" file.
     */
    public function forHash(string $hash): static
    {
        return $this->state(fn (array $attributes) => [
            'file_hash' => $hash,
        ]);
    }
}
