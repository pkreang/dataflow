<?php

namespace Database\Factories;

use App\Models\DocumentForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentFormField>
 */
class DocumentFormFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => DocumentForm::factory(),
            'field_key' => fake()->unique()->bothify('field_????'),
            'label' => ucwords(fake()->words(2, true)),
            'field_type' => 'text',
            'is_required' => false,
            'is_searchable' => false,
            'sort_order' => 1,
            'col_span' => 1,
        ];
    }

    /**
     * @param  array<int, string>  $options
     */
    public function select(array $options = ['A', 'B', 'C']): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => 'select',
            'options' => $options,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }
}
