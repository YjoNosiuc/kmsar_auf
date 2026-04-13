<?php

namespace Database\Factories;

use App\Models\College;
use App\Models\Research;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Research>
 */
class ResearchFactory extends Factory
{
    protected $model = Research::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Research $research) {
            if ($research->reference_number !== null && $research->reference_number !== '') {
                return;
            }

            $college = $research->mother_college_id
                ? College::query()->find($research->mother_college_id)
                : null;
            $code = $college?->code !== null && $college->code !== ''
                ? strtoupper((string) $college->code)
                : 'UNK';

            $suffix = fake()->unique()->numerify('####');

            $research->reference_number = sprintf(
                'AUF-%d-%s-%s',
                (int) now()->year,
                $code,
                $suffix
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 years', '-1 month');
        $estimated = (clone $start)->modify('+'.fake()->numberBetween(6, 24).' months');

        return [
            'reference_number' => null,
            'registration_type' => fake()->randomElement(['new', 'update']),
            'title' => strtoupper(fake()->unique()->sentence(6)),
            'primary_author_id' => User::factory(),
            'mother_college_id' => College::factory(),
            'other_college_id' => null,
            'research_classification' => strtoupper(Str::limit(fake()->lexify(str_repeat('?', 40)), 60, '')),
            'funding_agency' => fake()->boolean(40) ? strtoupper(fake()->company()) : null,
            'sdg_tags' => array_values(fake()->randomElements(range(1, 17), fake()->numberBetween(1, 5))),
            'expected_output' => array_values(fake()->randomElements(
                ['publication', 'patent', 'policy_brief', 'other'],
                fake()->numberBetween(1, 2)
            )),
            'expected_output_other' => null,
            'start_date' => $start->format('Y-m-d'),
            'estimated_completion_date' => $estimated->format('Y-m-d'),
            'status' => 'proposal',
            'approval_stage' => 'draft',
            'submitted_at' => null,
            'revision_count' => 0,
            'is_scopus_indexed' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_stage' => 'draft',
            'submitted_at' => null,
        ]);
    }

    public function deanReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_stage' => 'dean_review',
            'submitted_at' => now(),
        ]);
    }

    public function ovpriReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_stage' => 'ovpri_review',
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_stage' => 'approved',
            'submitted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'approval_stage' => 'rejected',
            'submitted_at' => now(),
        ]);
    }
}
