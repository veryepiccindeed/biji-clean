<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'id' => 'notif-'.fake()->unique()->bothify('###'),
            'user_id' => User::factory(),
            'type' => 'system',
            'type_label' => 'Sistem',
            'title' => fake()->sentence(3),
            'message' => fake()->paragraph(),
            'data' => json_encode(['key' => 'value']),
            'is_read' => false,
            'read_at' => null,
        ];
    }
}
