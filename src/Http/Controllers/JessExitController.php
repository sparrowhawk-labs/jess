<?php

namespace SparrowhawkLabs\Jess\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The demo banner's "exit": drop the jess_session cookie so the very next
 * request resolves against the host's real DB again (the connection switch
 * is cookie-driven — no other state to tear down; the SQLite copy itself
 * is reaped later by jess:cleanup).
 */
class JessExitController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $to = (string) $request->query('to', '');

        // Same-app relative paths only — absolute URLs, protocol-relative
        // //host and backslash tricks all fall back to the configured
        // landing, so exit can never become an open redirect.
        $safe = str_starts_with($to, '/')
            && ! str_starts_with($to, '//')
            && ! str_contains($to, '\\');

        return redirect($safe ? $to : (string) config('jess.exit_redirect', '/'))
            ->withoutCookie('jess_session');
    }
}
