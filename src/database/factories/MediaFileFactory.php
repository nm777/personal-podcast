<?php

namespace Database\Factories;

use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaFile>
 */
class MediaFileFactory extends Factory
{
    protected $model = MediaFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'file_path' => 'media/'.$this->faker->uuid().'.mp3',
            'file_hash' => $this->faker->sha256(),
            'mime_type' => $this->faker->randomElement(['audio/mpeg', 'audio/mp4', 'audio/wav', 'video/mp4']),
            'filesize' => $this->faker->numberBetween(1000000, 50000000),
            'duration' => $this->faker->numberBetween(60, 7200),
        ];
    }
}
