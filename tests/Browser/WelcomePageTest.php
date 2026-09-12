<?php

test('welcome page loads in a browser', function (): void {
    visit('/')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();
});
