<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Barbershop;
use App\Models\Client;
use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private function createAuthenticatedUser(string $suffix = 'a'): array
    {
        $barbershop = Barbershop::create(['name' => 'Barbearia Teste','email' => "barbearia{$suffix}@teste.com"]);

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

    private function createClient(int $barbershopId, string $name = 'Cliente Teste'): Client
    {
        return Client::create([
            'name' => $name,
            'phone' => '11999990001',
            'barbershop_id' => $barbershopId
        ]);
    }

    // =====================
    // INDEX
    // =====================

    public function test_user_can_list_appointments(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);
        Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-01 10:00:00']);
        Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-02 11:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/appointments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => ['data', 'current_page', 'total']
            ]);

        $this->assertEquals(2, $response->json('data.total'));
    }

    public function test_user_can_filter_appointments_by_date(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);
        Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-01 10:00:00']);
        Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-02 11:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/appointments?date=2026-05-01');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.total'));
    }

    public function test_user_cannot_see_other_barbershop_appointments(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $otherBarbershop = Barbershop::create(['name' => 'Outra Barbearia', 'email' => 'outra@teste.com']);
        $otherClient = $this->createClient($otherBarbershop->id, 'Cliente Alheio');
        Appointment::create(['client_id' => $otherClient->id, 'appointment_date' => '2026-05-01 10:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/appointments');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total'));
    }

    public function test_list_appointments_fails_without_token(): void
    {
        $response = $this->getJson('/api/appointments');
        $response->assertStatus(401);
    }

    // =====================
    // STORE
    // =====================

    public function test_user_can_create_appointment(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/appointments', [
                'client_id' => $client->id,
                'appointment_date' => '2026-05-01 10:00:00'
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'client_id', 'appointment_date']
            ]);

        $this->assertDatabaseHas('appointments', ['client_id' => $client->id]);
    }

    public function test_create_appointment_fails_with_duplicate_time(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);
        Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-01 10:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/appointments', [
                'client_id' => $client->id,
                'appointment_date' => '2026-05-01 10:00:00'
            ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Já existe um agendamento nesse horário']);
    }

    public function test_create_appointment_fails_with_client_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $otherBarbershop = Barbershop::create(['name' => 'Outra Barbearia', 'email' => 'outra@teste.com']);
        $otherClient = $this->createClient($otherBarbershop->id, 'Cliente Alheio');

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/appointments', [
                'client_id' => $otherClient->id,
                'appointment_date' => '2026-05-01 10:00:00'
            ]);

        $response->assertStatus(404);
    }

    public function test_create_appointment_fails_without_client_id(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/appointments', [
                'appointment_date' => '2026-05-01 10:00:00'
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_create_appointment_fails_without_date(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);

        $response = $this->withHeaders($this->authHeaders($token))
            ->postJson('/api/appointments', [
                'client_id' => $client->id
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_create_appointment_fails_without_token(): void
    {
        $response = $this->postJson('/api/appointments', [
            'client_id' => 1,
            'appointment_date' => '2026-05-01 10:00:00'
        ]);

        $response->assertStatus(401);
    }

    // =====================
    // SHOW
    // =====================

    public function test_user_can_get_specific_appointment(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);
        $appointment = Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-01 10:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson("/api/appointments/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['message', 'data' => ['id', 'client_id', 'appointment_date']]);
    }

    public function test_user_cannot_get_appointment_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $otherBarbershop = Barbershop::create(['name' => 'Outra Barbearia', 'email' => 'outra@teste.com']);
        $otherClient = $this->createClient($otherBarbershop->id, 'Cliente Alheio');
        $appointment = Appointment::create(['client_id' => $otherClient->id, 'appointment_date' => '2026-05-01 10:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson("/api/appointments/{$appointment->id}");

        $response->assertStatus(404);
    }

    public function test_get_nonexistent_appointment_returns_404(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/appointments/99999');

        $response->assertStatus(404);
    }

    // =====================
    // UPDATE
    // =====================

    public function test_user_can_update_appointment(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);
        $appointment = Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-01 10:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson("/api/appointments/{$appointment->id}", [
                'appointment_date' => '2026-05-03 14:00:00'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'appointment_date' => '2026-05-03 14:00:00']);
    }

    public function test_update_appointment_fails_with_duplicate_time(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);
        Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-01 10:00:00']);
        $appointment2 = Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-02 11:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson("/api/appointments/{$appointment2->id}", [
                'appointment_date' => '2026-05-01 10:00:00'
            ]);

        $response->assertStatus(400);
    }

    public function test_user_cannot_update_appointment_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $otherBarbershop = Barbershop::create(['name' => 'Outra Barbearia', 'email' => 'outra@teste.com']);
        $otherClient = $this->createClient($otherBarbershop->id, 'Cliente Alheio');
        $appointment = Appointment::create(['client_id' => $otherClient->id, 'appointment_date' => '2026-05-01 10:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson("/api/appointments/{$appointment->id}", [
                'appointment_date' => '2026-05-03 14:00:00'
            ]);

        $response->assertStatus(404);
    }

    // =====================
    // DESTROY
    // =====================

    public function test_user_can_delete_appointment(): void
    {
        ['token' => $token, 'barbershop' => $barbershop] = $this->createAuthenticatedUser();

        $client = $this->createClient($barbershop->id);
        $appointment = Appointment::create(['client_id' => $client->id, 'appointment_date' => '2026-05-01 10:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->deleteJson("/api/appointments/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Agendamento deletado com sucesso']);

        $this->assertDatabaseMissing('appointments', ['id' => $appointment->id]);
    }

    public function test_user_cannot_delete_appointment_from_other_barbershop(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $otherBarbershop = Barbershop::create(['name' => 'Outra Barbearia', 'email' => 'outra@teste.com']);
        $otherClient = $this->createClient($otherBarbershop->id, 'Cliente Alheio');
        $appointment = Appointment::create(['client_id' => $otherClient->id, 'appointment_date' => '2026-05-01 10:00:00']);

        $response = $this->withHeaders($this->authHeaders($token))
            ->deleteJson("/api/appointments/{$appointment->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_delete_nonexistent_appointment_returns_404(): void
    {
        ['token' => $token] = $this->createAuthenticatedUser();

        $response = $this->withHeaders($this->authHeaders($token))
            ->deleteJson('/api/appointments/99999');

        $response->assertStatus(404);
    }
}
