<?php

namespace SparrowhawkLabs\Jess\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use SparrowhawkLabs\Jess\Services\DemoSessionManager;
use SparrowhawkLabs\Jess\Support\DemoSession;

/**
 * Runs on every 'web' request (only while jess.enabled). Reads the demo
 * session cookie left by JessStateController and re-activates that
 * session's DB connection, so a *redirected* request — not just the initial
 * signed-link hit — keeps seeing the demo data. Silently no-ops (host app
 * behaves normally) when there's no cookie or the session has expired.
 *
 * On the way out it also injects jess's banner assets into full-page HTML
 * responses (see injectBannerAssets) — the demo banner while a session is
 * active, the origin recorder/restorer everywhere else.
 */
class SwitchDemoConnection
{
    public function __construct(
        private readonly DemoSessionManager $sessions,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $session = null;
        $uuid = $request->cookie('jess_session');

        if (is_string($uuid) && $uuid !== '') {
            $session = $this->sessions->find($uuid);

            if ($session !== null) {
                $this->sessions->activate($session);
            }
        }

        $response = $next($request);

        $this->injectBannerAssets($request, $response, $session);

        return $response;
    }

    /**
     * Append jess's UI to full-page HTML responses, just before </body>:
     *
     * - demo session active  → the corner banner (recipe label + exit link)
     * - no demo session      → a tiny recorder script that keeps "current
     *   URL + scroll position" in sessionStorage, so exiting a demo later
     *   can land the user exactly where they were before entering — plus
     *   the scroll restorer/toast for when they arrive back via that exit.
     *
     * Only plain GET requests with a successful text/html Response carrying
     * a </body> tag are touched — JSON, redirects, streams, downloads and
     * Livewire partial updates all pass through untouched.
     */
    private function injectBannerAssets(Request $request, mixed $response, ?DemoSession $session): void
    {
        if (! config('jess.banner', true)) {
            return;
        }

        if (! $request->isMethod('GET') || $request->headers->has('X-Livewire')) {
            return;
        }

        // Strict Response check excludes JsonResponse, redirects, streamed
        // and binary responses in one go.
        if (get_class($response) !== Response::class || ! $response->isSuccessful()) {
            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', 'text/html');

        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();

        if (! is_string($content)) {
            return;
        }

        $bodyClose = strripos($content, '</body>');

        if ($bodyClose === false) {
            return;
        }

        $assets = $session !== null
            ? view('jess::banner', ['recipe' => $session->recipe, 'hasReturn' => $session->returnTo !== null])->render()
            : view('jess::scripts')->render();

        $response->setContent(
            substr($content, 0, $bodyClose) . $assets . substr($content, $bodyClose)
        );
    }
}
