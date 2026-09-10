<?php

namespace SparrowhawkLabs\Jess\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SparrowhawkLabs\Jess\Services\DemoSessionManager;

/**
 * The demo banner's "exit": drop the jess_session cookie so the very next
 * request resolves against the host's real DB again (the connection switch
 * is cookie-driven — no other state to tear down; the SQLite copy itself
 * is reaped later by jess:cleanup).
 *
 * Landing, in order of preference:
 *   1. the session's recorded return target — the page the demo link was
 *      pressed on (Referer at landing, may be another origin);
 *   2. `?to=` — the same-app path the banner read back from the origin
 *      recorder (only offered when 1. is absent);
 *   3. config('jess.exit_redirect').
 */
class JessExitController extends Controller
{
    public function __invoke(Request $request, DemoSessionManager $sessions): RedirectResponse
    {
        $uuid = $request->cookie('jess_session');
        $session = is_string($uuid) && $uuid !== '' ? $sessions->find($uuid) : null;

        if ($session?->returnTo !== null) {
            // Validated at record time (absolute http(s) only) and bound to
            // this very session — not user-supplied on this request.
            return redirect()->away($session->returnTo)->withoutCookie('jess_session');
        }

        $to = (string) $request->query('to', '');

        // Same-app relative paths only — absolute URLs, protocol-relative
        // //host and backslash tricks all fall back to the configured
        // landing, so exit can never become an open redirect.
        $safe = str_starts_with($to, '/')
            && ! str_starts_with($to, '//')
            && ! str_contains($to, '\\');

        // The banner records `location.pathname`, which already carries the
        // app's base path when the host is served under a prefix (reverse
        // proxy with X-Forwarded-Prefix, e.g. `/new`). redirect() prepends
        // that base again, yielding `/new/new/…` → 404. Strip a leading base
        // path so the target is app-relative before generating the URL.
        $base = rtrim($request->getBaseUrl(), '/');
        if ($safe && $base !== '' && ($to === $base || str_starts_with($to, $base . '/'))) {
            $to = substr($to, strlen($base)) ?: '/';
        }

        return redirect($safe ? $to : (string) config('jess.exit_redirect', '/'))
            ->withoutCookie('jess_session');
    }
}
