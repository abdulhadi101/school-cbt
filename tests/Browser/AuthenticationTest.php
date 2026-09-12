<?php

test('login page loads with form elements', function (): void {
    visit('/login')
        ->assertSee('Email')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertSee('Log in')
        ->assertSee('Forgot your password?')
        ->assertNoJavaScriptErrors();
});

test('login page has proper labels', function (): void {
    visit('/login')
        ->assertSee('Email')
        ->assertSee('Password')
        ->assertSee('Remember me')
        ->assertNoJavaScriptErrors();
});

test('login page has submit button', function (): void {
    visit('/login')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});
