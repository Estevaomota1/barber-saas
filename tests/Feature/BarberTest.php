<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Barbershop;
use App\Models\Barber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarberTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(string $suffix = 'a'): array
    {
        $barbershop = Barbershop::create([
            'name' => 'Barbearia Teste',
            'email' => "barbearia{$suffix}@teste.com"
        ]);

        $user = User::factory()->create([
            'barbershop_id' => $barbershop->id
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return ['user' => $user, 'token' => $token, 'barbershop' => $barbershop];
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => "Bearer $token"];
    }

    public function test_user_can_list_barbers(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        Barber::create(['name' => 'Joao', 'phone' => '11999990001', 'barbershop_id' => $barbershop->id]);
        Barber::create(['name' => 'Pedro', 'phone' => '11999990002', 'barbershop_id' => $barbershop->id]);

        $response = $this->withHeaders($this->authHeaders($token))->getJson('/api/barbers');

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data' => ['data', 'current_page', 'total']]);

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_user_cannot_see_other_barbershop_barbers(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser('a');

        $other = Barbershop::create(['name' => 'Outra', 'email' => 'outra@teste.com']);
        Barber::create(['name' => 'Alheio', 'barbershop_id' => $other->id]);

        $response = $this->withHeaders($this->authHeaders($token))->getJson('/api/barbers');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total'));
    }

    public function test_list_barbers_fails_without_token(): void
    {
        $this->getJson('/api/barbers')->assertStatus(401);
    }

    public function test_user_can_create_barber(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/barbers', ['name' => 'Carlos', 'phone' => '11999990001']);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'data' => ['id', 'name', 'barbershop_id']]);

        $this->assertDatabaseHas('barbers', ['name' => 'Carlos']);
    }

    public function test_create_barber_fails_without_name(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/barbers', ['phone' => '11999990001'])
            ->assertStatus(422);
    }

    public function test_create_barber_fails_without_token(): void
    {
        $this->postJson('/api/barbers', ['name' => 'Carlos'])->assertStatus(401);
    }

    public function test_user_can_get_specific_barber(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $barber = Barber::create(['name' => 'Carlos', 'barbershop_id' => $barbershop->id]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson("/api/barbers/{$barber->id}")
            ->assertStatus(200);
    }

    public function test_user_cannot_get_barber_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser('a');

        $other = Barbershop::create(['name' => 'Outra', 'email' => 'outra@teste.com']);
        $barber = Barber::create(['name' => 'Alheio', 'barbershop_id' => $other->id]);

        $this->withHeaders($this->authHeaders($token))
            ->getJson("/api/barbers/{$barber->id}")
            ->assertStatus(404);
    }

    public function test_get_nonexistent_barber_returns_404(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/barbers/99999')
            ->assertStatus(404);
    }

    public function test_user_can_update_barber(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $barber = Barber::create(['name' => 'Carlos', 'barbershop_id' => $barbershop->id]);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson("/api/barbers/{$barber->id}", ['name' => 'Carlos Novo', 'phone' => '11999990099']);

        $response->assertStatus(200)->assertJsonPath('data.name', 'Carlos Novo');
    }

    public function test_user_cannot_update_barber_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser('a');

        $other = Barbershop::create(['name' => 'Outra', 'email' => 'outra@teste.com']);
        $barber = Barber::create(['name' => 'Alheio', 'barbershop_id' => $other->id]);

        $this->withHeaders($this->authHeaders($token))
            ->putJson("/api/barbers/{$barber->id}", ['name' => 'X'])
            ->assertStatus(404);
    }

    public function test_user_can_delete_barber(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $barber = Barber::create(['name' => 'Carlos', 'barbershop_id' => $barbershop->id]);

        $this->withHeaders($this->authHeaders($token))
            ->deleteJson("/api/barbers/{$barber->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('barbers', ['id' => $barber->id]);
    }

    public function test_user_cannot_delete_barber_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser('a');

        $other = Barbershop::create(['name' => 'Outra', 'email' => 'outra@teste.com']);
        $barber = Barber::create(['name' => 'Alheio', 'barbershop_id' => $other->id]);

        $this->withHeaders($this->authHeaders($token))
            ->deleteJson("/api/barbers/{$barber->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('barbers', ['id' => $barber->id]);
    }

    public function test_delete_nonexistent_barber_returns_404(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $this->withHeaders($this->authHeaders($token))
            ->deleteJson('/api/barbers/99999')
            ->assertStatus(404);
    }
}