<?php

namespace Database\Factories;

use App\Core\Models\Modifier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Core\Models\Modifier>
 */
class ModifierFactory extends Factory
{
    protected $model = Modifier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['size', 'smothing', 'customize_modifires']);

        return [
            'type' => $type,
            'name_json' => [
                'en' => $this->faker->word(),
                'ar' => $this->faker->word(),
            ],
            'max_level' => null,
            'price' => $this->faker->randomFloat(2, 0, 20),
            'is_active' => true,
        ];
    }
}
