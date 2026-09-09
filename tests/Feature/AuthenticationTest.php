<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_active_user_can_login_and_password_is_not_exposed(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass', 'is_active' => true]);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['password' => 'secret-pass', 'is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sales_staff_can_open_and_create_quotations(): void
    {
        $role = Role::create(['name' => 'SALES / STAFF', 'guard_name' => 'web']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        $this->actingAs($user);

        $this->get('/dashboard')->assertOk()->assertSee('New quotation')->assertDontSee('New product');
        $this->get('/quotations/create')->assertOk();
    }
}
