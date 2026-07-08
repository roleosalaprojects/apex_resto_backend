<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example. The root permanently redirects to the shop
     * (routes/web.php) — this domain serves the shop directly.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(301);
        $response->assertRedirect('/shop');
    }
}
