<?php

namespace Tests\Feature;

use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        City::create([
            'name' => 'Dharan',
            'slug' => 'dharan',
            'status' => 'active',
            'default_delivery_fee' => 40.00,
            'estimated_delivery_minutes' => 30,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
