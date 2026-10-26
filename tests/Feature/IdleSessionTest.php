<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class IdleSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $user = new User();
        $user->setRawAttributes(['id' => 1, 'name' => 'Petugas', 'email' => 'petugas@example.test']);
        $this->actingAs($user);
    }

    public function test_timeout_is_two_hours(): void
    {
        $this->assertSame(120, config('session.lifetime'));
    }

    public function test_activity_before_timeout_renews_current_session(): void
    {
        $this->withSession(['last_activity_at' => now()->subMinutes(119)->timestamp]);
        $this->postJson(route('session.activity'))->assertNoContent()
            ->assertSessionHas('last_activity_at', now()->timestamp);
        $this->assertAuthenticated();
        $this->travel(119)->minutes();
        $this->postJson(route('session.activity'))->assertNoContent();
        $this->assertAuthenticated();
    }

    public function test_exactly_two_hours_of_inactivity_expires_session(): void
    {
        $this->withSession(['last_activity_at' => now()->subHours(2)->timestamp, 'private_data' => 'old']);
        $this->postJson(route('session.activity'))->assertUnauthorized()
            ->assertSessionMissing('private_data')->assertSessionMissing('last_activity_at');
        $this->assertGuest();
    }

    public function test_expired_page_request_redirects_to_login(): void
    {
        $this->withSession(['last_activity_at' => now()->subHours(3)->timestamp]);
        $this->get(route('dashboard'))->assertRedirect(route('login', ['expired' => 1]));
        $this->assertGuest();
    }

    public function test_existing_session_gets_activity_timestamp(): void
    {
        $this->postJson(route('session.activity'))->assertNoContent()
            ->assertSessionHas('last_activity_at', now()->timestamp);
    }

    public function test_guest_cannot_keep_a_session_authenticated(): void
    {
        Auth::logoutCurrentDevice();
        $this->postJson(route('session.activity'))->assertUnauthorized();
        $this->assertGuest();
    }
}
