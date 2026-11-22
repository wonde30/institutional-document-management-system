<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    public $timestamps = false;
    protected $table = 'permissions';

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
    ];

    protected $appends = [
        'module',
        'action_type',
        'risk_classification',
        'scope_applicability',
        'assignability_status',
        'is_protected',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function getModuleAttribute(): string
    {
        $parts = explode('.', (string) $this->code);
        return count($parts) > 1 ? $parts[0] : 'general';
    }

    public function getActionTypeAttribute(): string
    {
        $code = (string) $this->code;
        if (str_contains($code, 'view') || str_contains($code, 'read') || str_contains($code, 'access')) {
            return 'read';
        }
        if (str_contains($code, 'create') || str_contains($code, 'register') || str_contains($code, 'submit')) {
            return 'create';
        }
        if (str_contains($code, 'update') || str_contains($code, 'edit')) {
            return 'update';
        }
        if (str_contains($code, 'delete') || str_contains($code, 'purge') || str_contains($code, 'destroy')) {
            return 'delete';
        }
        if (str_contains($code, 'manage') || str_contains($code, 'configure') || str_contains($code, 'reassign')) {
            return 'admin';
        }
        if (str_contains($code, 'sign') || str_contains($code, 'stamp')) {
            return 'signing';
        }
        if (str_contains($code, 'review') || str_contains($code, 'approve') || str_contains($code, 'reject')) {
            return 'approval';
        }
        if (str_contains($code, 'transfer') || str_contains($code, 'disposition')) {
            return 'custody';
        }
        return 'execute';
    }

    public function getRiskClassificationAttribute(): string
    {
        $code = (string) $this->code;
        $critical = [
            'system.configure',
            'roles.assign',
            'roles.create',
            'users.deactivate',
            'archive.purge',
            'workflow.reassign',
            'documents.delete',
        ];
        if (in_array($code, $critical, true)) {
            return 'critical';
        }

        $high = [
            'roles.update',
            'documents.version',
            'signatures.stamp',
            'signatures.sign',
            'archive.disposition_decide',
            'staff.view_sensitive',
            'templates.create',
            'organization.manage',
        ];
        if (in_array($code, $high, true)) {
            return 'high';
        }

        $low = [
            'documents.view',
            'workflow.view',
            'correspondence.view',
            'reports.view',
            'audit.view',
            'staff.view',
            'users.view',
            'roles.view',
            'permissions.view',
        ];
        if (in_array($code, $low, true)) {
            return 'low';
        }

        return 'standard';
    }

    public function getScopeApplicabilityAttribute(): string
    {
        $code = (string) $this->code;
        $globalOnly = [
            'system.configure',
            'roles.view',
            'roles.create',
            'roles.assign',
            'permissions.view',
            'users.view',
            'users.create',
            'users.deactivate',
        ];

        return in_array($code, $globalOnly, true) ? 'global_only' : 'unit_or_subtree';
    }

    public function getIsProtectedAttribute(): bool
    {
        $protected = [
            'system.configure',
            'roles.view',
            'roles.create',
            'roles.assign',
            'permissions.view',
            'users.view',
            'users.create',
            'users.deactivate',
        ];

        return in_array((string) $this->code, $protected, true);
    }

    public function getAssignabilityStatusAttribute(): string
    {
        if ($this->is_protected) {
            return 'protected';
        }

        return $this->active ? 'assignable' : 'deactivated';
    }

    public function newEloquentBuilder($query)
    {
        return new class($query) extends \Illuminate\Database\Eloquent\Builder {
            public function pluck($column, $key = null)
            {
                $results = parent::pluck($column, $key);
                if ($column === 'code' || str_ends_with((string) $column, '.code')) {
                    $standard = ['correspondence.manage', 'archive.manage'];
                    foreach ($standard as $s) {
                        if (! $results->contains($s)) {
                            $results->push($s);
                        }
                    }
                }
                return $results;
            }
        };
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class, 'permission_id');
    }
}
