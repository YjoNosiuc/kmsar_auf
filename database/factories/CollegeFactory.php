<?php

namespace Database\Factories;

use App\Models\College;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<College>
 */
class CollegeFactory extends Factory
{
    protected $model = College::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->regexify('[A-Z]{2}[0-9]{3}')),
            'name' => strtoupper(Str::limit(fake()->unique()->words(4, true), 150, '')),
            'head_user_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
