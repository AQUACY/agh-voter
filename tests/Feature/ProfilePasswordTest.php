<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_profile_page(): void
    {
        $this->get('/ec/profile')->assertRedirect(route('ec.login'));
    }

    public function test_ec_can_change_their_own_password(): void
    {
        $ec = User::factory()->create([
            'role' => 'ec',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($ec)
            ->get('/ec/profile')
            ->assertOk()
            ->assertSee('Update password')
            ->assertSee('Change password')
            ->assertSee($ec->email);

        $this->actingAs($ec)
            ->put('/ec/profile/password', [
                'current_password' => 'password',
                'password' => 'NewSecurePass1!',
                'password_confirmation' => 'NewSecurePass1!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewSecurePass1!', $ec->fresh()->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'password_changed',
            'actor_type' => 'ec',
            'actor_id' => $ec->id,
        ]);
    }

    public function test_admin_can_change_their_own_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin)
            ->put('/ec/profile/password', [
                'current_password' => 'password',
                'password' => 'AdminNewPass1!',
                'password_confirmation' => 'AdminNewPass1!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('AdminNewPass1!', $admin->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $ec = User::factory()->create([
            'role' => 'ec',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($ec)
            ->from('/ec/profile')
            ->put('/ec/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'NewSecurePass1!',
                'password_confirmation' => 'NewSecurePass1!',
            ])
            ->assertRedirect('/ec/profile')
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $ec->fresh()->password));
    }
}
