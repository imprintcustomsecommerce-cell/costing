<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * What the system shows when a request cannot be answered normally.
 *
 * The admin screens are limited to ADMIN and SUPER ADMIN, and settings and
 * accounts to SUPER ADMIN alone, so a sales account meets this page in the
 * ordinary course of using the system. It is not an error to be apologised
 * for, and it must not be a dead end.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    private function sales(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(Role::firstOrCreate(['name' => 'SALES / STAFF', 'guard_name' => 'web']));
        $this->actingAs($user);

        return $user;
    }

    public function test_a_sales_account_is_refused_the_admin_screens(): void
    {
        $this->sales();

        foreach (['/admin/products', '/admin/materials', '/admin/audit-logs'] as $screen) {
            $this->get($screen)->assertForbidden();
        }
    }

    public function test_a_sales_account_is_refused_settings_and_accounts(): void
    {
        $this->sales();

        foreach (['/admin/settings', '/admin/users'] as $screen) {
            $this->get($screen)->assertForbidden();
        }
    }

    public function test_the_refusal_says_who_is_signed_in_and_what_role_they_have(): void
    {
        $user = $this->sales();

        $this->get('/admin/settings')
            ->assertForbidden()
            ->assertSee('That screen is not for your account')
            ->assertSee($user->name)
            ->assertSee('SALES / STAFF');
    }

    public function test_the_refusal_offers_a_way_back(): void
    {
        $this->sales();

        // A dead end is what sends people to ring somebody. The page carries
        // the screens this account can actually use.
        $this->get('/admin/products')
            ->assertForbidden()
            ->assertSee(route('dashboard'))
            ->assertSee(route('quotations.index'));
    }

    public function test_the_sales_account_still_reaches_its_own_work(): void
    {
        $this->sales();

        // The point of the refusal is that everything else keeps working.
        $this->get('/dashboard')->assertOk();
        $this->get('/quotations')->assertOk();
    }

    public function test_a_guest_is_sent_to_sign_in_rather_than_refused(): void
    {
        // Not signed in is not the same as not allowed: there is nothing to
        // explain to somebody who has not said who they are yet.
        $this->get('/admin/settings')->assertRedirect(route('login'));
    }

    public function test_a_missing_page_is_explained_rather_than_dumped(): void
    {
        $this->sales();

        $this->get('/no-such-screen')
            ->assertNotFound()
            ->assertSee('That page is not here')
            ->assertSee(route('dashboard'));
    }

    public function test_a_missing_record_says_what_is_missing(): void
    {
        $this->sales();

        // A quotation that never existed is not the same as a mistyped
        // address, and the page carries whichever message the code gave.
        $this->get('/quotations/999999')->assertNotFound()->assertSee('That page is not here');
    }

    public function test_a_guest_meeting_a_missing_page_is_offered_the_sign_in(): void
    {
        $this->get('/no-such-screen')
            ->assertNotFound()
            ->assertSee(route('login'))
            ->assertDontSee(route('dashboard'));
    }

    public function test_the_server_error_page_touches_neither_session_nor_database(): void
    {
        // The likeliest cause of a 500 is the database being unreachable, and
        // sessions live in the database. Rendering this page must not need
        // either, or the fault becomes unreadable.
        $page = view('errors.500', ['exception' => new \RuntimeException('leaked internals')])->render();

        $this->assertStringContainsString('Something went wrong at our end', $page);
        $this->assertStringNotContainsString('leaked internals', $page);
    }

    public function test_the_error_pages_never_extend_the_app_layout(): void
    {
        // layouts.app reads auth()->user()->name unguarded, so an error page
        // built on it would fatal exactly when the session has gone.
        foreach (['403', '404', '500', 'shell'] as $code) {
            $this->assertStringNotContainsString(
                "@extends('layouts.app')",
                file_get_contents(resource_path("views/errors/{$code}.blade.php")),
                "errors/{$code} must not depend on the app layout"
            );
        }
    }
}
