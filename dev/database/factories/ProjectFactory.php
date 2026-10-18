<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return ['name' => 'Pernikahan '.$this->faker->firstName(), 'couple_name' => $this->faker->firstName().' & '.$this->faker->firstName(), 'event_date' => $this->faker->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'), 'venue' => 'Gedung '.$this->faker->city(), 'timezone' => 'Asia/Makassar', 'status' => 'persiapan'];
    }
}
