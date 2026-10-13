<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_user()
    {
        $password = 'password123';
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => $password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'name', 'email']);
    }

    public function test_login_with_wrong_password_returns_422()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correctpassword'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_with_sanctum_requires_token()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(204);
    }

    public function test_protected_endpoints_return_401_without_login()
    {
        $response = $this->getJson('/api/me');
        $response->assertStatus(401);

        $response = $this->getJson('/api/events');
        $response->assertStatus(401);

        $response = $this->postJson('/api/events', []);
        $response->assertStatus(401);
    }
}
