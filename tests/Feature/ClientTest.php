<?php 
namespace Tests\Feature;
use App\Models\User;
use App\Models\Barbershop;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(string $suffix = 'a'): array
    {
        $barbershop = Barbershop::create([
            'name' => 'Barbearia Teste',
            'email' => "barbearia{$suffix}@teste.com"
        ]);
        $user = User::factory()->create(['barbershop_id' => $barbershop->id]);
        $token = $user->createToken('auth_token')->plainTextToken;
        return ['user' => $user, 'token' => $token, 'barbershop' => $barbershop];
    }

    private function authHeaders(string $token): array
    {
        return ['Authorization' => "Bearer $token"];
    }

    public function test_user_can_list_clients(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();
        Client::create(['name' => 'Cliente 1', 'phone' => '11999990001', 'barbershop_id' => $barbershop->id]);
        $response = $this->withHeaders($this->authHeaders($token))->getJson('/api/clients');
        $response->assertStatus(200)->assertJsonStructure(['message', 'data' => ['data', 'current_page', 'total']]);
    }

    public function test_user_cannot_see_other_barbershop_clients(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser('a');
        $other = Barbershop::create(['name' => 'Outra', 'email' => 'outra@teste.com']);
        Client::create(['name' => 'Alheio', 'phone' => '11999990003', 'barbershop_id' => $other->id]);
        $response = $this->withHeaders($this->authHeaders($token))->getJson('/api/clients');
        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total'));
    }

    public function test_list_clients_fails_without_token(): void
    {
        $this->getJson('/api/clients')->assertStatus(401);
    }

    public function test_user_can_create_client(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();
        $response = $this->withHeaders($this->authHeaders($token))->postJson('/api/clients', ['name' => 'Joao Silva', 'phone' => '11999990001']);
        $response->assertStatus(201)->assertJsonStructure(['message', 'data' => ['id', 'name', 'phone', 'barbershop_id']]);
        $this->assertDatabaseHas('clients', ['name' => 'Joao Silva']);
    }

    public function test_create_client_fails_without_name(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();
        $this->withHeaders($this->authHeaders($token))->postJson('/api/clients', ['phone' => '11999990001'])->assertStatus(422);
    }

    public function test_create_client_fails_without_phone(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();
        $this->withHeaders($this->authHeaders($token))->postJson('/api/clients', ['name' => 'Joao'])->assertStatus(422);
    }

    public function test_create_client_fails_without_token(): void
    {
        $this->postJson('/api/clients', ['name' => 'Joao', 'phone' => '11999990001'])->assertStatus(401);
    }

    public function test_user_can_get_specific_client(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();
        $client = Client::create(['name' => 'Joao', 'phone' => '11999990001', 'barbershop_id' => $barbershop->id]);
        $this->withHeaders($this->authHeaders($token))->getJson("/api/clients/{$client->id}")->assertStatus(200);
    }

    public function test_user_cannot_get_client_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser('a');
        $other = Barbershop::create(['name' => 'Outra', 'email' => 'outra@teste.com']);
        $client = Client::create(['name' => 'Alheio', 'phone' => '11999990002', 'barbershop_id' => $other->id]);
        $this->withHeaders($this->authHeaders($token))->getJson("/api/clients/{$client->id}")->assertStatus(404);
    }

    public function test_get_nonexistent_client_returns_404(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();
        $this->withHeaders($this->authHeaders($token))->getJson('/api/clients/99999')->assertStatus(404);
    }

    public function test_user_can_update_client(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();
        $client = Client::create(['name' => 'Joao', 'phone' => '11999990001', 'barbershop_id' => $barbershop->id]);
        $response = $this->withHeaders($this->authHeaders($token))->putJson("/api/clients/{$client->id}", ['name' => 'Joao Novo', 'phone' => '11999990099']);
        $response->assertStatus(200)->assertJsonPath('data.name', 'Joao Novo');
    }

    public function test_user_cannot_update_client_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser('a');
        $other = Barbershop::create(['name' => 'Outra', 'email' => 'outra@teste.com']);
        $client = Client::create(['name' => 'Alheio', 'phone' => '11999990002', 'barbershop_id' => $other->id]);
        $this->withHeaders($this->authHeaders($token))->putJson("/api/clients/{$client->id}", ['name' => 'X', 'phone' => '11000000000'])->assertStatus(404);
    }

    public function test_user_can_delete_client(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();
        $client = Client::create(['name' => 'Joao', 'phone' => '11999990001', 'barbershop_id' => $barbershop->id]);
        $this->withHeaders($this->authHeaders($token))->deleteJson("/api/clients/{$client->id}")->assertStatus(200);
        $this->assertDatabaseMissing('clients', ['id' => $client->id]);
    }

    public function test_user_cannot_delete_client_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser('a');
        $other = Barbershop::create(['name' => 'Outra', 'email' => 'outra@teste.com']);
        $client = Client::create(['name' => 'Alheio', 'phone' => '11999990002', 'barbershop_id' => $other->id]);
        $this->withHeaders($this->authHeaders($token))->deleteJson("/api/clients/{$client->id}")->assertStatus(404);
        $this->assertDatabaseHas('clients', ['id' => $client->id]);
    }

    public function test_delete_nonexistent_client_returns_404(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();
        $this->withHeaders($this->authHeaders($token))->deleteJson('/api/clients/99999')->assertStatus(404);
    }
}


