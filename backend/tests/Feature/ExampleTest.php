<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The API health probe responds successfully.
     *
     * This replaces the default Laravel scaffold assertion against `GET /`,
     * which does not apply to this API-only backend (there is no `/` web
     * route, and stancl/tenancy registers tenant routes on the root path).
     */
    public function test_the_api_health_probe_returns_a_successful_response(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }
}
