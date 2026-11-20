<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the login form to a guest', function () {
    // `/` is the login page: the app has no separate /login GET route.
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
});

it('signs a user in with their password', function () {
    $user = User::factory()->create(['password' => 'admin123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'admin123'])
        ->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password and keeps the guest a guest', function () {
    $user = User::factory()->create(['password' => 'admin123']);

    $this->post('/login', ['email' => $user->email, 'password' => 'nope'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('requires an email and a password', function () {
    $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
});

it('signs the user out again', function () {
    $this->actingAs(User::factory()->create());

    $this->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});

it('will not open the books without an authenticated user', function () {
    // The login route is named "login" but lives at "/", which is where the
    // auth middleware sends guests.
    $this->get('/journal-entries')->assertRedirect('/');
    $this->get('/audit-log')->assertRedirect('/');
    $this->get('/reports/trial-balance')->assertRedirect('/');
    $this->post('/journal-entries')->assertRedirect('/');
    $this->get('/reports/ledger')->assertRedirect('/');
});
