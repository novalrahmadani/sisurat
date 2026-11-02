<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
});

test('SISURAT home and information pages are accessible without an admin login', function (string $route, string $component) {
    $this->get(route($route))->assertOk()->assertInertia(fn (Assert $page) => $page->component($component)->where('auth.user', null));
})->with([['home', 'Welcome'], ['information', 'Information']]);
