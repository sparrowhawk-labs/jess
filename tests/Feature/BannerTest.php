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

function enterDemo(?string $referer = null): string
{
    test()->get(Jess::link(['user:repeat_customer'], '/page', userId: 1), $referer === null ? [] : ['Referer' => $referer])
        ->assertRedirect('/page');

    return (string) DB::connection('testing')->table('jess_demo_sessions')->latest('id')->value('uuid');
}

function recordedReturn(string $uuid): ?string
{
    return DB::connection('testing')->table('jess_demo_sessions')->where('uuid', $uuid)->value('return_to');
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

test('the banner sits bottom-right by default and follows jess.banner_position', function () {
    $uuid = enterDemo();

    $this->withCookie('jess_session', $uuid)
        ->get('/page')
        ->assertSee('position:fixed;bottom:14px;right:14px;', false);

    config(['jess.banner_position' => 'top-left']);

    $this->withCookie('jess_session', $uuid)
        ->get('/page')
        ->assertSee('position:fixed;top:14px;left:14px;', false);
});

test('landing records the Referer — external origins included — as the session return target', function () {
    $uuid = enterDemo('https://gate.example/reviews/p0-2.html?x=1');

    expect(recordedReturn($uuid))->toBe('https://gate.example/reviews/p0-2.html?x=1');
});

test('exit returns to the page the demo link was pressed on, and only for that session', function () {
    $uuid = enterDemo('http://127.0.0.1:7890/Users/me/docs/visualized/review.html');

    // The recorded target wins over anything the banner script may pass in ?to.
    $this->withCookie('jess_session', $uuid)
        ->get('/jess/exit?to=/somewhere-else')
        ->assertRedirect('http://127.0.0.1:7890/Users/me/docs/visualized/review.html')
        ->assertCookieExpired('jess_session');

    // A different (unknown) session cookie gets no such target.
    config(['jess.exit_redirect' => '/home']);
    $this->withCookie('jess_session', 'not-a-real-session')
        ->get('/jess/exit')
        ->assertRedirect('/home');
});

test('with no Referer nothing is recorded and exit falls back to exit_redirect', function () {
    config(['jess.exit_redirect' => '/home']);
    $uuid = enterDemo();

    expect(recordedReturn($uuid))->toBeNull();

    $this->withCookie('jess_session', $uuid)
        ->get('/jess/exit')
        ->assertRedirect('/home')
        ->assertCookieExpired('jess_session');
});

test('a Referer that is not an absolute http(s) URL is ignored', function () {
    foreach (['javascript:alert(1)', 'data:text/html,hi', '//evil.example/x', 'ftp://files.example/', '/relative', 'http://', 'not a url'] as $bad) {
        expect(recordedReturn(enterDemo($bad)))->toBeNull("Referer [{$bad}] must not be recorded");
    }
});

test('a same-origin Referer is left to the in-app recorder (URL + scroll position)', function () {
    $uuid = enterDemo('http://localhost/admin/features');

    expect(recordedReturn($uuid))->toBeNull();

    $this->withCookie('jess_session', $uuid)
        ->get('/page')
        ->assertSee('jess_origin', false);
});

test('a bare-origin Referer (what strict-origin-when-cross-origin leaves) is treated as unknown', function () {
    expect(recordedReturn(enterDemo('http://127.0.0.1:7890/')))->toBeNull();
    expect(recordedReturn(enterDemo('https://gate.example')))->toBeNull();
    // …but a root page with a query string is a real page.
    expect(recordedReturn(enterDemo('https://gate.example/?review=p0-2')))->toBe('https://gate.example/?review=p0-2');
});

test('the banner only wires the in-app origin recorder when no return target was recorded', function () {
    $withReturn = enterDemo('https://gate.example/review.html');

    $this->withCookie('jess_session', $withReturn)
        ->get('/page')
        ->assertSee('id="jess-banner"', false)
        ->assertDontSee('jess_origin', false);

    $withoutReturn = enterDemo();

    $this->withCookie('jess_session', $withoutReturn)
        ->get('/page')
        ->assertSee('id="jess-banner"', false)
        ->assertSee('jess_origin', false);
});

test('exit strips the app base path from ?to when served under a prefix (no /new/new)', function () {
    // Simulate a reverse proxy mounting the app under /new (X-Forwarded-Prefix → getBaseUrl()).
    $this->withServerVariables(['HTTP_X_FORWARDED_PREFIX' => '/new'])
        ->get('/jess/exit?to=/new/manuals')
        ->assertRedirect('http://localhost/new/manuals'); // base path once — not /new/new/manuals
});
