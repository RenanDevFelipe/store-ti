<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_the_application(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/');

        $response->assertStatus(200);
    }
}
