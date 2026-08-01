<?php

namespace SparrowhawkLabs\Jess;

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\ServiceProvider;
use SparrowhawkLabs\Jess\Console\CleanupCommand;
use SparrowhawkLabs\Jess\Console\InstallCommand;
use SparrowhawkLabs\Jess\FragmentRegistry;
use SparrowhawkLabs\Jess\Http\Middleware\SwitchDemoConnection;
use SparrowhawkLabs\Jess\Services\DemoSessionManager;

class JessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/jess.php', 'jess');

        $this->app->singleton(FragmentRegistry::class);
        $this->app->singleton(DemoSessionManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'jess');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'jess');

        // Inert unless explicitly enabled — no route, no middleware, no
        // exception handling registered at all in the disabled state.
        if (config('jess.enabled')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/jess.php');
            $this->registerMiddleware();
            $this->registerExpiredLinkPage();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                CleanupCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/jess.php' => config_path('jess.php'),
            ], 'jess-config');

            $this->publishes([
                __DIR__ . '/../README.md' => base_path('docs/jess/README.md'),
            ], 'jess-docs');

            $this->publishes([
                __DIR__ . '/../resources/lang' => $this->app->langPath('vendor/jess'),
            ], 'jess-lang');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/jess'),
            ], 'jess-views');
        }
    }

    /**
     * Both the group membership and the priority entry must go through the
     * Kernel, not the Router: every Kernel group/priority mutation calls
     * syncMiddlewareToRouter(), which overwrites the Router's groups with
     * the Kernel's own — a router-level pushMiddlewareToGroup() would be
     * silently wiped by the very next sync.
     *
     * The priority entry itself exists because being at the *end* of the
     * 'web' group is not enough: Laravel's middleware priority sorts
     * `Authenticate` ahead of any unlisted group middleware, so on an
     * `auth`-protected landing route the auth check would run while
     * `database.default` still points at the host's real DB — a demo-only
     * userId can never resolve there, and every signed link bounces to
     * /login. Insert SwitchDemoConnection just before the auth middleware
     * (and thereby after EncryptCookies, which must decrypt the
     * jess_session cookie first). The host's own bootstrap/app.php
     * middleware config is applied when the Kernel is resolved — before
     * providers boot — so neither registration is overwritten by it.
     * Idempotent: a host that already lists SwitchDemoConnection in a
     * custom priority() is left untouched.
     */
    private function registerMiddleware(): void
    {
        $this->callAfterResolving(HttpKernelContract::class, function ($kernel) {
            if ($kernel instanceof HttpKernel) {
                $kernel->addToMiddlewarePriorityBefore(
                    [AuthenticatesRequests::class, \Illuminate\Auth\Middleware\Authenticate::class],
                    SwitchDemoConnection::class,
                );
                $kernel->appendMiddlewareToGroup('web', SwitchDemoConnection::class);
            } else {
                // Custom kernel not derived from the framework's: fall back
                // to the router-level push (pre-priority behavior — auth
                // landing routes then need a manual priority() declaration).
                $this->app['router']->pushMiddlewareToGroup('web', SwitchDemoConnection::class);
            }
        });
    }

    /**
     * A signed jess link that's expired or been tampered with throws
     * Laravel's built-in InvalidSignatureException (plain 403 by default).
     * Replace that with a plain-language explanation, scoped to the jess
     * route only — everything else keeps default handling. Text follows the
     * app's current locale (`app()->getLocale()`, driven by `APP_LOCALE` /
     * `config('app.locale')`) via jess's own translation files
     * (`jess::messages.*`, English + Japanese shipped; publish
     * `--tag=jess-lang` to add more or override wording).
     */
    private function registerExpiredLinkPage(): void
    {
        $this->app->make(ExceptionHandler::class)->renderable(
            function (InvalidSignatureException $e, $request) {
                if (! $request->routeIs('jess.state')) {
                    return null;
                }

                $ttl = (int) config('jess.signed_url_ttl', 60);
                $locale = e(app()->getLocale());

                return response(
                    "<!doctype html><html lang=\"{$locale}\"><head><meta charset=\"utf-8\">"
                    . '<title>' . e(__('jess::messages.expired_title')) . '</title></head>'
                    . '<body style="font-family: sans-serif; padding: 2rem; max-width: 40rem;">'
                    . '<h1>' . e(__('jess::messages.expired_heading')) . '</h1>'
                    . '<p>' . e(__('jess::messages.expired_body', ['ttl' => $ttl])) . '</p>'
                    . '</body></html>',
                    403,
                );
            }
        );
    }
}
