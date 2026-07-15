<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/api/products')->assertRedirect('/login');
    }

    public function test_checkout_result_is_public(): void
    {
        $this->get('/checkout/resultado?status=success&link=example')->assertOk();
    }

    public function test_admin_can_log_in_and_out(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ]);

        $this->postJson('/login', [
            'email' => 'admin@example.com',
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertAuthenticated();

        $this->postJson('/logout')->assertOk();

        $this->assertGuest();
    }
}
