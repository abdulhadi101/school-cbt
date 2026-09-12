<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can login with valid credentials', function (): void {
    // Create user in database
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);

    // Visit login page and fill form
    $page = visit('/login')
        ->fill('email', 'test@example.com')
        ->fill('password', 'password')
        ->pressAndWaitFor('Log in', 2);

    // Assert redirected to dashboard
    $page->assertPathIs('/dashboard')
        ->assertSee('Dashboard')
        ->assertSee('Test User')
        ->assertNoJavaScriptErrors();
});

test('user sees error with invalid credentials', function (): void {
    // Visit login page and submit invalid credentials
    $page = visit('/login')
        ->fill('email', 'wrong@example.com')
        ->fill('password', 'wrongpassword')
        ->pressAndWaitFor('Log in', 2);

    // Assert error message appears
    $page->assertSee('These credentials do not match our records')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
});

test('user can logout', function (): void {
    // Create user
    $user = User::create([
        'name' => 'Logout User',
        'email' => 'logout@example.com',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
    ]);

    // Login
    $page = visit('/login')
        ->fill('email', 'logout@example.com')
        ->fill('password', 'password')
        ->pressAndWaitFor('Log in', 2);

    // Click on profile dropdown and logout
    $page->click('Logout User')
        ->pressAndWaitFor('Log Out', 2);

    // Assert redirected to home/login page
    $page->assertPathIs('/')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});
