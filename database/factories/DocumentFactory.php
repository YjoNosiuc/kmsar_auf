<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Research;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stored = strtoupper(Str::uuid()->toString()).'.PDF';

        return [
            'research_id' => Research::factory(),
            'uploaded_by' => User::factory(),
            'original_filename' => strtoupper(fake()->lexify('????????').'.PDF'),
            'stored_filename' => $stored,
            'disk_path' => 'documents/'.$stored,
            'external_link' => null,
            'mime_type' => 'application/pdf',
            'file_size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'research_status_at_upload' => strtoupper('proposal'),
            'version' => fake()->numberBetween(1, 5),
        ];
    }

    public function externalLink(string $url): static
    {
        return $this->state(fn (array $attributes) => [
            'external_link' => $url,
            'stored_filename' => null,
            'disk_path' => null,
            'mime_type' => 'text/plain',
            'file_size_bytes' => 0,
        ]);
    }
}
