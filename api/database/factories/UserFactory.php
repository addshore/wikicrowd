<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'username' => $this->faker->userName(),
            'mw_userid' => $this->faker->randomNumber(8, true),
            'token' => 'abc',
            'token_secret' => '123',
        ];
    }

}
