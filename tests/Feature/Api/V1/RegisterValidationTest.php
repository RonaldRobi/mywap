<?php

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create([
            'slug' => 'pkpim',
            'min_age' => 0,
            'max_age' => null,
            'fee_amount' => 50,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ahmad Firdaus',
            'email' => 'ahmad@example.test',
            'ic_number' => '900101011234',
            'phone' => '0123456789',
        ], $overrides);
    }

    public function test_email_is_normalized_to_lowercase(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'email' => 'Ahmad@Example.TEST',
        ]))->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'ahmad@example.test',
        ]);
    }

    public function test_duplicate_ic_returns_malay_message(): void
    {
        User::factory()->create(['ic_number' => '900101011234']);

        $this->postJson('/api/v1/auth/register', $this->payload([
            'email' => 'lain@example.test',
        ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.ic_number.0',
                'No. Kad Pengenalan ini sudah didaftarkan. Sila log masuk, atau hubungi admin jika anda terlupa No. Ahli.'
            );
    }

    public function test_duplicate_email_returns_malay_message(): void
    {
        User::factory()->create(['email' => 'ahmad@example.test']);

        $this->postJson('/api/v1/auth/register', $this->payload([
            'ic_number' => '910202025555',
        ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.email.0',
                'Emel ini sudah didaftarkan. Sila log masuk, atau gunakan emel lain.'
            );
    }

    public function test_unreadable_ic_without_dob_returns_malay_message(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'ic_number' => 'A1234567',
        ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.dob.0',
                'Tarikh lahir wajib diisi kerana No. IC tidak dapat dibaca. Sila pilih tarikh lahir.'
            );
    }

    public function test_invalid_ic_format_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'ic_number' => '123',
            'dob' => '1990-01-01',
        ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.ic_number.0',
                'No. Kad Pengenalan tidak sah. Masukkan 12 digit No. Kad Pengenalan (cth: 900101011234) atau No. Passport.'
            );
    }

    public function test_future_dob_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'ic_number' => 'A1234567',
            'dob' => now()->addYear()->toDateString(),
        ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.dob.0',
                'Tarikh lahir tidak boleh pada masa hadapan.'
            );
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'phone' => 'abc',
        ]))
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.phone.0',
                'No. telefon tidak sah. Gunakan nombor sahaja, contoh: 0123456789.'
            );
    }
}
