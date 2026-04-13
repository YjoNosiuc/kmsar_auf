<?php

namespace Database\Factories;

use App\Models\Research;
use App\Models\ResearchAuthor;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchAuthor>
 */
class ResearchAuthorFactory extends Factory
{
    protected $model = ResearchAuthor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $first = strtoupper(fake()->firstName());
        $last = strtoupper(fake()->lastName());
        $middle = fake()->boolean(50) ? strtoupper(fake()->lastName()) : null;
        $suffix = fake()->boolean(15) ? strtoupper(fake()->suffix()) : null;

        return [
            'research_id' => Research::factory(),
            'user_id' => null,
            'author_type' => fake()->optional(0.75)->randomElement(['student', 'employee']),
            'employee_number' => fake()->boolean(30) ? strtoupper(fake()->bothify('??####')) : null,
            'first_name' => $first,
            'last_name' => $last,
            'middle_name' => $middle,
            'suffix' => $suffix,
            'name' => TextNormalizer::upper(trim($first.' '.$last)),
            'college_id' => null,
            'college_text' => null,
            'program' => null,
            'program_id' => null,
            'affiliated_college_id' => null,
            'institution' => fake()->boolean(20) ? strtoupper(fake()->company()) : null,
            'email' => fake()->boolean(60) ? fake()->unique()->safeEmail() : null,
            'is_primary' => false,
            'can_edit' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
            'can_edit' => true,
        ]);
    }

    public function coAuthor(bool $canEdit = false): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => false,
            'can_edit' => $canEdit,
        ]);
    }

    public function linkedUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'employee_number' => $user->employee_number !== null
                ? TextNormalizer::upperNullable($user->employee_number)
                : null,
            'first_name' => $user->first_name !== null
                ? TextNormalizer::upper($user->first_name)
                : null,
            'last_name' => $user->last_name !== null
                ? TextNormalizer::upper($user->last_name)
                : null,
            'middle_name' => $user->middle_name !== null
                ? TextNormalizer::upper($user->middle_name)
                : null,
            'suffix' => $user->suffix !== null
                ? TextNormalizer::upper($user->suffix)
                : null,
            'name' => TextNormalizer::upper(trim(
                ($user->first_name !== null ? trim((string) $user->first_name) : '').
                ' '.
                ($user->last_name !== null ? trim((string) $user->last_name) : '')
            )),
        ]);
    }
}
