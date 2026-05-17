<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentForm>
 */
class DocumentFormFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_key' => fake()->unique()->bothify('form_????_####'),
            'name' => ucwords(fake()->words(3, true)),
            'document_type' => fake()->randomElement(['leave', 'procurement', 'activity', 'repair_request']),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'layout_columns' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
