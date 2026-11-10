<?php

namespace App\Http\Resources;

use App\Services\Authorization\OrganizationalContextService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $context = app(OrganizationalContextService::class)->buildContext($this->resource, $request);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'authz_version' => $this->authz_version,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'primary_membership' => $this->whenLoaded('primaryMembershipRecord', function () {
                $pm = $this->primaryMembershipRecord;
                if (! $pm || ! $pm->membership) return null;
                return [
                    'id' => $pm->membership->id,
                    'unit_id' => $pm->membership->unit_id,
                    'unit_name' => $pm->membership->unit?->name,
                    'unit_code' => $pm->membership->unit?->code,
                    'position_title' => $pm->membership->position_title,
                ];
            }),
            'roles' => $this->whenLoaded('userRoles', function () {
                return $this->userRoles->map(function ($ur) {
                    return [
                        'role_id' => $ur->role_id,
                        'role_code' => $ur->role?->code,
                        'role_name' => $ur->role?->name,
                        'scope_id' => $ur->scope_id,
                        'scope_kind' => $ur->scope?->kind,
                        'scope_unit_id' => $ur->scope?->unit_id,
                        'scope_unit_name' => $ur->scope?->unit?->name,
                        'expires_at' => $ur->expires_at?->toISOString(),
                    ];
                });
            }),
            'direct_permissions' => $this->whenLoaded('userPermissions', function () {
                return $this->userPermissions->map(function ($up) {
                    return [
                        'id' => $up->id,
                        'permission_id' => $up->permission_id,
                        'permission_code' => $up->permission?->code,
                        'permission_name' => $up->permission?->name,
                        'scope_id' => $up->scope_id,
                        'scope_kind' => $up->scope?->kind,
                        'scope_unit_id' => $up->scope?->unit_id,
                        'scope_unit_name' => $up->scope?->unit?->name,
                        'expires_at' => $up->expires_at?->toISOString(),
                    ];
                });
            }),

            /*
            | The capability set published to the client is calculated for the
            | active organizational context only. It is never the union of every
            | scoped grant the user holds. The server remains the authority:
            | every record scoped request is re-evaluated against the owning
            | unit or document.
            */
            'permissions' => $context['permissions'],
            'permissions_global' => $context['permissions_global'],
            'permissions_all_scopes' => $context['permissions_all_scopes'],
            'active_unit' => $context['active_unit'],
            'active_unit_id' => $context['active_unit_id'],
            'available_units' => $context['available_units'],
            'unit_selection_required' => $context['unit_selection_required'],
        ];
    }
}
