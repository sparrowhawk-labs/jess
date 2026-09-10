<?php

namespace SparrowhawkLabs\Jess\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use SparrowhawkLabs\Jess\Exceptions\FragmentExecutionException;
use SparrowhawkLabs\Jess\FragmentRegistry;
use SparrowhawkLabs\Jess\Support\DemoSession;
use SparrowhawkLabs\Jess\Support\StateRecipe;

/**
 * Provisions per-session demo SQLite copies and switches the runtime default
 * DB connection to point at them. The switch mechanism (purge + config swap,
 * connection name held fixed) was verified in spike/ — see PLAN.md リスク§1.
 */
class DemoSessionManager
{
    /**
     * The host app's own default connection name, captured before any
     * activate() can have swapped the runtime default (this singleton is
     * resolved by the middleware/controller ahead of any switch). The
     * session ledger (jess_demo_sessions) must always be read and written
     * on this connection: when a stale demo-session cookie is still live,
     * SwitchDemoConnection swaps database.default to the old demo copy
     * *before* a new signed-link hit provisions — following the runtime
     * default there would write the new session's ledger row into a demo
     * SQLite copy, where the next request's find() can never see it.
     */
    private readonly string $hostConnection;

    public function __construct(
        private readonly FragmentRegistry $fragments,
    ) {
        $this->hostConnection = (string) config('database.default');
    }

    /**
     * @param  string|null  $returnTo  Already-validated absolute http(s) URL
     *                                 the exit should land on for this session.
     */
    public function provision(StateRecipe $recipe, ?string $returnTo = null): DemoSession
    {
        $templatePath = (string) config('jess.template_db_path');

        if ($templatePath === '' || ! is_file($templatePath)) {
            throw new RuntimeException(
                "jess.template_db_path is not configured or the file is missing: [{$templatePath}]"
            );
        }

        $storageDir = $this->storageDir();

        if (! is_dir($storageDir) && ! mkdir($storageDir, 0755, true) && ! is_dir($storageDir)) {
            throw new RuntimeException("Could not create jess demo DB storage directory: [{$storageDir}]");
        }

        $uuid = (string) Str::uuid();
        $targetPath = $storageDir . '/' . $uuid . '.sqlite';

        if (! copy($templatePath, $targetPath)) {
            throw new RuntimeException("Could not copy jess template DB to [{$targetPath}]");
        }

        $connection = (string) config('jess.connection', 'jess_demo');

        // Apply fragments against the new file while it's the default
        // connection (host Seeders reference no connection name), then
        // restore the host default — provisioning itself must not leave
        // global state switched (switching is activate()'s job).
        // Fragments run unguarded: the fixed-ID pattern the docs mandate
        // (`updateOrCreate(['id' => KNOWN_ID], ...)`) silently loses the id
        // on any normally-guarded model (auto-increment row instead), which
        // makes the recipe's userId login fail with no error. Fragments only
        // ever write to the isolated demo copy, so disabling mass-assignment
        // protection here costs nothing.
        $this->withDemoConnection($connection, $targetPath, function () use ($recipe) {
            Model::unguarded(function () use ($recipe) {
                foreach ($recipe->fragments as $name) {
                    // Let an unregistered-name lookup fail as itself (a config/
                    // typo error, not a runtime failure) — only the closure's
                    // own execution gets wrapped with fragment-attributed context.
                    $callback = $this->fragments->get($name);

                    try {
                        $callback();
                    } catch (\Throwable $e) {
                        throw FragmentExecutionException::forFragment($name, $e);
                    }
                }
            });
        });

        $recipeLabel = implode(',', $recipe->fragments);
        $expiresAt = now()->addHours((int) config('jess.cleanup_after_hours', 24));

        DB::connection($this->hostConnection)->table('jess_demo_sessions')->insert([
            'uuid' => $uuid,
            'recipe' => $recipeLabel,
            'demo_db_path' => $targetPath,
            'return_to' => $returnTo,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new DemoSession($uuid, $recipeLabel, $targetPath, $expiresAt, $returnTo);
    }

    /**
     * Look up a previously provisioned, still-unexpired session by uuid —
     * used by the per-request middleware to resolve the Cookie it receives
     * back into a DemoSession worth activate()-ing.
     */
    public function find(string $uuid): ?DemoSession
    {
        $row = DB::connection($this->hostConnection)->table('jess_demo_sessions')
            ->where('uuid', $uuid)
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null) {
            return null;
        }

        return new DemoSession(
            $row->uuid,
            $row->recipe,
            $row->demo_db_path,
            new \DateTimeImmutable($row->expires_at),
            $row->return_to,
        );
    }

    /**
     * Point the runtime default connection at this session's SQLite file for
     * the remainder of the request. Intended for a per-request middleware
     * (Phase 3) that resolves a session identifier (e.g. from a Cookie) on
     * every request — unlike provision(), this does not restore the prior
     * default; the switch is meant to last for the rest of the request.
     */
    public function activate(DemoSession $session): void
    {
        $connection = (string) config('jess.connection', 'jess_demo');
        $this->configureConnection($connection, $session->sqlitePath);
        config(['database.default' => $connection]);
    }

    private function withDemoConnection(string $connection, string $path, Closure $callback): void
    {
        $this->configureConnection($connection, $path);
        config(['database.default' => $connection]);

        try {
            $callback();
        } finally {
            DB::purge($connection);
            config(['database.default' => $this->hostConnection]);
        }
    }

    private function configureConnection(string $connection, string $path): void
    {
        DB::purge($connection);
        config([
            "database.connections.{$connection}" => [
                'driver' => 'sqlite',
                'database' => $path,
                'prefix' => '',
            ],
        ]);
    }

    private function storageDir(): string
    {
        $configured = (string) config('jess.demo_db_storage_path');

        return str_starts_with($configured, '/')
            ? $configured
            : storage_path($configured);
    }
}
