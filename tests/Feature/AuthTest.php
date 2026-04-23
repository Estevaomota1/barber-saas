<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Barbershop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test user registration
     */
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'João Silva',
            'email' => 'joao@email.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'barbershop_name' => 'Barbearia do João'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'barbershop_id'],
                'token'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'joao@email.com'
        ]);
    }

    /**
     * Test registration fails with invalid email
     */
    public function test_registration_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'João Silva',
            'email' => 'invalid-email',
            'password' => '123456',
            'password_confirmation' => '123456',
            'barbershop_name' => 'Barbearia do João'
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    /**
     * Test registration fails with duplicate email
     */
    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'joao@email.com'
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'João Silva',
            'email' => 'joao@email.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'barbershop_name' => 'Barbearia do João'
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test user can login
     */
    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'joao@email.com',
            'password' => bcrypt('123456')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@email.com',
            'password' => '123456'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'email'],
                'token'
            ]);
    }

    /**
     * Test login fails with wrong password
     */
    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'joao@email.com',
            'password' => bcrypt('123456')
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'joao@email.com',
            'password' => 'wrong-password'
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test login fails with non-existent email
     */
    public function test_login_fails_with_non_existent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@email.com',
            'password' => '123456'
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test user can get their profile
     */
    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email']
            ]);
    }

    /**
     * Test getting profile fails without token
     */
    public function test_get_profile_fails_without_token(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    /**
     * Test user can logout
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logout realizado com sucesso']);

        // Try to use the token again (should fail)
        $response = $this->withHeaders([
            'Authorization' => "Bearer $token"
        ])->getJson('/api/me');

        $response->assertStatus(401);
    }

    /**
     * Test logout fails without token
     */
    public function test_logout_fails_without_token(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }
}