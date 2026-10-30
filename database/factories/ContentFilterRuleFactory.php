<?php

namespace Database\Factories;

use App\Models\ContentFilterRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentFilterRule>
 */
class ContentFilterRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phrase' => fake()->unique()->word(),
            'category' => 'profanity',
            'action' => 'censor',
            'is_active' => true,
        ];
    }
}
