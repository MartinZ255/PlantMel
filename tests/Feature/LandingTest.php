<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_landing_page()
    {
        $response = $this->get(route('landing'));

        $response->assertStatus(200);
    }
}
