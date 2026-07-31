<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use SparrowhawkLabs\Jess\Facades\Jess;

class JessTestUser extends Authenticatable
{
    protected $table = 'users';

    public $timestamps = false;
}

beforeEach(function () {
    config(['auth.providers.users.model' => JessTestUser::class]);

    // Stand-in for "the host app's actual /cart route" — plain host code,
    // no awareness of jess at all.
    Route::middleware('web')->get('/cart', function () {
        return response()->json([
            'authenticated_as' => Auth::id(),
            'name' => DB::table('users')->where('id', 1)->value('name'),
        ]);
    });

    Jess::fragment('user:repeat_customer', function () {
        DB::table('users')->where('id', 1)->update([
            'name' => 'Alice',
            'is_repeat_customer' => 1,
        ]);
    });
});

test('a signed jess link provisions, switches, logs in, and lands on the target screen', function () {
    $url = Jess::link(['user:repeat_customer'], '/cart', userId: 1);

    $this->get($url)->assertRedirect('/cart');

    // The redirect target is a *second* request — this is what proves the
    // Cookie-driven SwitchDemoConnection middleware (not just the initial
    // controller hit) keeps the demo DB active.
    $this->get('/cart')
        ->assertOk()
        ->assertJson(['authenticated_as' => 1, 'name' => 'Alice']);
});

test('a second signed link hit with a stale demo cookie still provisions a findable session', function () {
    // First visit: provisions session A, whose cookie the browser keeps.
    $this->get(Jess::link(['user:repeat_customer'], '/cart', userId: 1))
        ->assertRedirect('/cart');

    $firstUuid = DB::connection('testing')->table('jess_demo_sessions')->value('uuid');

    // Second signed-link hit with session A's cookie still attached — the
    // middleware activates A's demo copy *before* the controller
    // provisions session B. B's ledger row must land on the host DB, or
    // the redirect's follow-up request silently falls back to the real DB
    // (the alternating success/failure pattern observed in the wild).
    $this->withCookie('jess_session', $firstUuid)
        ->get(Jess::link(['user:repeat_customer'], '/cart', userId: 1))
        ->assertRedirect('/cart');

    $uuids = DB::connection('testing')->table('jess_demo_sessions')->orderBy('id')->pluck('uuid');
    expect($uuids)->toHaveCount(2);

    // The follow-up request carrying session B's cookie sees B's demo data.
    $this->withCookie('jess_session', (string) $uuids->last())
        ->get('/cart')
        ->assertOk()
        ->assertJson(['authenticated_as' => 1, 'name' => 'Alice']);
});

test('a demo login survives landing on an auth-middleware route', function () {
    // nadi (first host-app integration): the host's real DB also has a users
    // table, but without the demo user — if Authenticate sorts ahead of
    // SwitchDemoConnection, the auth check resolves the session's userId
    // against the host DB, finds nothing, and 302s every request to /login.
    DB::connection('testing')->statement(
        'CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, is_repeat_customer INTEGER DEFAULT 0)'
    );

    Route::middleware(['web', 'auth'])->get('/account', function () {
        return response()->json(['authenticated_as' => Auth::id()]);
    });
    Route::middleware('web')->get('/login', fn () => 'login')->name('login');

    $this->get(Jess::link(['user:repeat_customer'], '/account', userId: 1))
        ->assertRedirect('/account');

    $uuid = DB::connection('testing')->table('jess_demo_sessions')->value('uuid');

    // Make the follow-up behave like the genuinely fresh request it would be
    // in a real app: the default connection starts back on the host DB and
    // the guard's cached user is gone, so authentication must re-resolve
    // through whatever connection is active when Authenticate runs.
    config(['database.default' => 'testing']);
    DB::purge((string) config('jess.connection'));
    Auth::forgetGuards();

    $this->withCookie('jess_session', $uuid)
        ->get('/account')
        ->assertOk()
        ->assertJson(['authenticated_as' => 1]);
});

test('a tampered link is rejected with a plain-language message in the app locale (default: English)', function () {
    $url = Jess::link(['user:repeat_customer'], '/cart', userId: 1) . 'x';

    $this->get($url)
        ->assertStatus(403)
        ->assertSee('no longer valid', false);
});

test('the expired-link page follows config(app.locale) — Japanese when set', function () {
    config(['app.locale' => 'ja']);

    $url = Jess::link(['user:repeat_customer'], '/cart', userId: 1) . 'x';

    $this->get($url)
        ->assertStatus(403)
        ->assertSee('無効です', false);
});

test('an expired link is rejected the same way', function () {
    $this->travelTo(now()->subMinutes(120));
    $url = Jess::link(['user:repeat_customer'], '/cart', userId: 1);
    $this->travelBack();

    $this->get($url)->assertStatus(403);
});
