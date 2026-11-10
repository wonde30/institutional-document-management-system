<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Contracts\HasAbilities;

class LegacyTransitionToken extends Model implements HasAbilities
{
    protected $table = 'sessions';
    public $timestamps = false;
    public $exists = true;

    public $tokenable;
    public $created_at;
    public $expires_at;

    public function save(array $options = []): bool
    {
        return true;
    }

    /**
     * Find a legacy custom transition token from the baseline `sessions` table.
     */
    public static function findToken($token): ?static
    {
        if (! is_string($token) || strlen($token) !== 60) {
            return null;
        }

        $lifetimeSeconds = config('session.lifetime', 120) * 60;
        $minActivity = time() - $lifetimeSeconds;

        $session = DB::table('sessions')
            ->where('id', $token)
            ->where('last_activity', '>=', $minActivity)
            ->first();

        if (! $session || ! $session->user_id) {
            return null;
        }

        $user = User::find($session->user_id);
        if (! $user) {
            return null;
        }

        // Validate authorization version if stored in session payload
        if (! empty($session->payload)) {
            $payloadData = @unserialize(base64_decode($session->payload));
            if (is_array($payloadData) && isset($payloadData['authz_version'])) {
                if ((int) $payloadData['authz_version'] !== (int) $user->authz_version) {
                    return null;
                }
            }
        }

        $instance = new static();
        $instance->tokenable = $user;
        $instance->created_at = Carbon::now();
        $instance->expires_at = Carbon::now()->addMinutes(120);

        return $instance;
    }

    public function can($ability): bool
    {
        return true;
    }

    public function cant($ability): bool
    {
        return false;
    }
}
