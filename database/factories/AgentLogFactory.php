<?php

namespace Database\Factories;

use App\Models\AgentLog;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentLog>
 */
class AgentLogFactory extends Factory
{
    protected $model = AgentLog::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'message_id' => null,
            'model' => 'claude-haiku-4-5-20251001',
            'request' => ['messages' => []],
            'response' => ['content' => []],
            'tokens_input' => fake()->numberBetween(100, 1000),
            'tokens_output' => fake()->numberBetween(50, 400),
            'estimated_cost' => fake()->randomFloat(4, 0.001, 0.05),
        ];
    }
}
