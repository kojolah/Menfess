<?php

namespace Database\Factories;

use App\Models\ContentFilterException;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentFilterException>
 */
class ContentFilterExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phrase' => fake()->unique()->words(2, true),
            'reason' => 'Known benign phrase',
            'is_active' => true,
        ];
    }
}
