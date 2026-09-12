<?php

test('welcome page has login button', function (): void {
    visit('/')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});

test('welcome page has Laravel logo', function (): void {
    visit('/')
        ->assertSee('Laravel')
        ->assertNoJavaScriptErrors();
});

test('welcome page loads quickly', function (): void {
    $start = microtime(true);
    visit('/');
    $duration = microtime(true) - $start;
    
    expect($duration)->toBeLessThan(5.0);
});
