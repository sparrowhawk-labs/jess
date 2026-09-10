<?php

namespace SparrowhawkLabs\Jess\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use SparrowhawkLabs\Jess\Services\DemoSessionManager;
use SparrowhawkLabs\Jess\Support\StateRecipe;

/**
 * The one entry point a jess signed link hits: resolve the recipe carried
 * in {token}, provision the demo DB, switch to it, log the recipe's user in,
 * and send the browser on to the real business screen. Everything past the
 * redirect is ordinary host-app code — it never learns jess exists.
 */
class JessStateController extends Controller
{
    public function __invoke(string $token, Request $request, DemoSessionManager $sessions): RedirectResponse
    {
        $recipe = StateRecipe::fromToken($token);

        $session = $sessions->provision($recipe, $this->returnTarget($request));
        $sessions->activate($session);

        if ($recipe->userId !== null) {
            Auth::loginUsingId($recipe->userId);
        }

        $cookieMinutes = (int) config('jess.cleanup_after_hours', 24) * 60;

        return redirect($recipe->redirectTo)
            ->cookie(Cookie::make('jess_session', $session->uuid, $cookieMinutes));
    }

    /**
     * The page the demo link was pressed on, taken from this request's
     * Referer — an external origin (a review/gate page) is fine, that is the
     * whole point. Only a well-formed absolute http(s) URL is accepted;
     * javascript:/data:/protocol-relative and anything else yields null so
     * the exit falls through to its safe defaults. This is the one and only
     * way a return target enters jess: it is bound to the session being
     * provisioned and consumed solely by that session's exit, never taken
     * from a query parameter (which would be an open redirect).
     *
     * Two referers are deliberately *not* recorded:
     * - same-origin (the link sat on one of the host app's own pages): the
     *   in-app recorder script already knows that page *and* its scroll
     *   position, and copes with a reverse-proxy base path — let it win;
     * - a bare origin (`https://gate.example/`, no path/query): that is what
     *   the browser's default `strict-origin-when-cross-origin` policy
     *   leaves of a cross-origin page URL, not a page anyone was on.
     *   Landing there would be worse than the fallbacks.
     */
    private function returnTarget(Request $request): ?string
    {
        $referer = trim((string) $request->headers->get('referer', ''));

        if ($referer === '' || strlen($referer) > 2048) {
            return null;
        }

        if (! preg_match('#^https?://#i', $referer) || filter_var($referer, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($referer);
        $origin = strtolower($parts['scheme'] . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : ''));

        if ($origin === strtolower($request->getSchemeAndHttpHost())) {
            return null;
        }

        if (($parts['path'] ?? '/') === '/' && ! isset($parts['query'])) {
            return null;
        }

        return $referer;
    }
}
