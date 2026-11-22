<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Record a system audit event in the `audit_events` table.
     */
    public static function log(
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $details = null,
        ?int $actorId = null,
        ?Request $request = null
    ): AuditEvent {
        $req = $request ?: request();
        $actor = $actorId ?: Auth::id();
        $now = now()->format('Y-m-d H:i:s.u');

        return AuditEvent::create([
            'actor_id' => $actor,
            'action' => $action,
            'target_type' => $targetType ?: 'system',
            'target_id' => $targetId ?: '0',
            'request_id' => (string) Str::uuid(),
            'occurred_at' => $now,
            'ip_address' => $req ? $req->ip() : '127.0.0.1',
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 500) : 'System/Internal',
            'details_json' => static::sanitizeValues($details),
        ]);
    }

    /**
     * Record an authentication lifecycle audit event.
     */
    public static function recordAuthEvent(
        string $action,
        ?int $actorId = null,
        string $targetType = 'user',
        string $targetId = '0',
        ?array $details = null,
        ?Request $request = null
    ): AuditEvent {
        return static::log(
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            details: $details,
            actorId: $actorId,
            request: $request
        );
    }

    /**
     * Record a document-related audit event with standardized metadata.
     */
    public static function recordDocumentEvent(
        string $action,
        int $actorId,
        int $documentId,
        ?array $details = null,
        ?Request $request = null
    ): AuditEvent {
        return static::log(
            action: $action,
            targetType: 'document',
            targetId: (string) $documentId,
            details: $details,
            actorId: $actorId,
            request: $request
        );
    }

    /**
     * Redact sensitive credentials, secrets, and authorization tokens.
     */
    public static function sanitizeValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $redactedKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            'remember_token',
            'secret',
            'access_token',
            'api_key',
            'cookie',
            'session_id',
            'xsrf_token',
            'csrf_token',
        ];

        foreach ($values as $key => $val) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;
            foreach ($redactedKeys as $rk) {
                if ($lowerKey === $rk || str_contains($lowerKey, $rk)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($val)) {
                $values[$key] = static::sanitizeValues($val);
            }
        }

        return $values;
    }
}
