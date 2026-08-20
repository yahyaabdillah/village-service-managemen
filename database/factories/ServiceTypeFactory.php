<?php

namespace Database\Factories;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceTypeFactory extends Factory
{
    protected $model = ServiceType::class;

    public function definition(): array
    {
        $name = $this->faker->words(3, true);

        return ['name' => $name, 'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(100, 999), 'description' => $this->faker->sentence(), 'is_active' => true, 'sort_order' => 0];
    }
}
