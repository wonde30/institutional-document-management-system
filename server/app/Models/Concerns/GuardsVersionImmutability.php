<?php

namespace App\Models\Concerns;

use RuntimeException;

/**
 * Trait GuardsVersionImmutability
 *
 * Implements dual-layer immutability for institutional document versions and file bindings:
 * 1. Application Layer: Eloquent updating/deleting event listeners throw a RuntimeException.
 * 2. Database Engine Layer: MySQL triggers signal SQLSTATE 45000 unless session variable
 *    `@dms_allow_version_mutation` is explicitly set to 1.
 *
 * SECURITY NOTICE: The MySQL session variable is an application-level safeguard against accidental
 * application or SQL mutation. It is not an authorization boundary against privileged users with
 * direct DBA or root database access.
 */
trait GuardsVersionImmutability
{
    /**
     * Nesting depth counter for authorized mutation scopes.
     */
    protected static int $mutationDepth = 0;

    /**
     * Boot the immutability guard listeners on the model.
     */
    public static function bootGuardsVersionImmutability(): void
    {
        static::updating(function ($model) {
            if (static::$mutationDepth <= 0) {
                throw new RuntimeException(
                    class_basename($model) . ' records are strictly append-only and immutable. Updates are rejected.'
                );
            }
        });

        static::deleting(function ($model) {
            if (static::$mutationDepth <= 0) {
                throw new RuntimeException(
                    class_basename($model) . ' records are strictly append-only and immutable. Deletions are rejected.'
                );
            }
        });
    }

    /**
     * Determine if mutation is currently allowed in memory.
     */
    public static function isMutationAllowed(): bool
    {
        return static::$mutationDepth > 0;
    }

    /**
     * Temporarily allow mutations for administrative maintenance, migration backfills, or testing.
     * Safely handles nested invocations, targets the model's active database connection, and guarantees
     * session reset in finally.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function allowMutation(callable $callback): mixed
    {
        $connection = (new static)->getConnection();
        static::$mutationDepth++;

        if (static::$mutationDepth === 1) {
            $connection->statement('SET @dms_allow_version_mutation = 1');
        }

        try {
            return $callback();
        } finally {
            static::$mutationDepth = max(0, static::$mutationDepth - 1);

            if (static::$mutationDepth === 0) {
                try {
                    $connection->statement('SET @dms_allow_version_mutation = 0');
                } catch (\Throwable) {
                    // Suppress connection-loss errors during teardown
                }
            }
        }
    }

    /**
     * Explicitly reset mutation permission to 0 on the model connection.
     * Useful for queue workers between jobs or request teardown.
     */
    public static function resetMutationProtection(): void
    {
        static::$mutationDepth = 0;
        try {
            (new static)->getConnection()->statement('SET @dms_allow_version_mutation = 0');
        } catch (\Throwable) {
            // Suppress connection-loss errors during teardown
        }
    }
}
