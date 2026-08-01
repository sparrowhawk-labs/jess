<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use SparrowhawkLabs\Jess\Facades\Jess;

beforeEach(function () {
    // Stand-in for an ordinary host-app HTML page (full document, so the
    // </body> injection point exists).
    Route::middleware('web')->get('/page', function () {
        return response('<html><body><h1>Shop</h1></body></html>');
    });

    Route::middleware('web')->get('/api-ish', function () {
        return response()->json(['ok' => true]);
    });

    Jess::fragment('user:repeat_customer', function () {
        DB::table('users')->where('id', 1)->update(['name' => 'Alice']);
    });
});

function enterDemo(): string
{
    test()->get(Jess::link(['user:repeat_customer'], '/page', userId: 1))
        ->assertRedirect('/page');

    return (string) DB::connection('testing')->table('jess_demo_sessions')->value('uuid');
}

test('a demo-session page gets the banner with recipe label and exit link', function () {
    $uuid = enterDemo();

    $this->withCookie('jess_session', $uuid)
        ->get('/page')
        ->assertOk()
        ->assertSee('id="jess-banner"', false)
        ->assertSee('user:repeat_customer')
        ->assertSee(route('jess.exit'), false);
});

test('a non-demo page gets the origin recorder script, not the banner', function () {
    $this->get('/page')
        ->assertOk()
        ->assertSee('jess_origin', false)
        ->assertDontSee('id="jess-banner"', false);
});

test('JSON responses are never touched', function () {
    $uuid = enterDemo();

    $this->withCookie('jess_session', $uuid)
        ->get('/api-ish')
        ->assertOk()
        ->assertDontSee('jess-banner', false)
        ->assertDontSee('jess_origin', false);
});

test('Livewire partial updates are never touched', function () {
    $this->withHeaders(['X-Livewire' => 'true'])
        ->get('/page')
        ->assertOk()
        ->assertDontSee('jess_origin', false);
});

test('jess.banner=false disables all injection', function () {
    config(['jess.banner' => false]);
    $uuid = enterDemo();

    $this->withCookie('jess_session', $uuid)
        ->get('/page')
        ->assertOk()
        ->assertDontSee('jess-banner', false);

    $this->get('/page')->assertDontSee('jess_origin', false);
});

test('exit drops the demo cookie and honors a same-app ?to path', function () {
    $this->get('/jess/exit?to=/admin/features')
        ->assertRedirect('/admin/features')
        ->assertCookieExpired('jess_session');
});

test('exit rejects foreign or protocol-relative targets and falls back to exit_redirect', function () {
    config(['jess.exit_redirect' => '/home']);

    $this->get('/jess/exit?to=https://evil.example/phish')->assertRedirect('/home');
    $this->get('/jess/exit?to=//evil.example')->assertRedirect('/home');
    $this->get('/jess/exit')->assertRedirect('/home');
});

test('after exit, the next request is back on the host DB', function () {
    $uuid = enterDemo();

    // While the cookie is live the demo world is visible…
    $this->withCookie('jess_session', $uuid)
        ->get('/page')
        ->assertSee('id="jess-banner"', false);

    // …after exit (cookie gone), the same URL renders as a plain host page.
    // (withCookie persists for the whole test, so drop it explicitly — the
    // real browser loses it via JessExitController's withoutCookie.)
    $this->defaultCookies = [];
    config(['database.default' => 'testing']);
    DB::purge((string) config('jess.connection'));

    $this->get('/page')
        ->assertOk()
        ->assertDontSee('id="jess-banner"', false)
        ->assertSee('jess_origin', false);
});
