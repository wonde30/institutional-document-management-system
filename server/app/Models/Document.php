<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $reference_number
 * @property string $title
 * @property string|null $summary
 * @property int|null $category_id
 * @property int|null $confidentiality_id
 * @property int $owner_unit_id
 * @property int|null $custodian_id
 * @property int|null $created_by
 * @property string|null $document_date
 * @property \Carbon\Carbon|null $registered_at
 * @property string $lifecycle
 * @property int $lock_version
 * @property \App\Models\ConfidentialityLevel|null $confidentiality
 * @property \App\Models\OrganizationUnit|null $ownerUnit
 * @property \App\Models\User|null $creator
 * @property \App\Models\DocumentVersion|null $latestVersion
 */
class Document extends Model
{
    use HasFactory;

    public static function getHighConfidentialityIds(): array
    {
        $request = app()->bound('request') ? app('request') : null;
        if ($request && $request instanceof \Illuminate\Http\Request && $request->attributes->has('__high_conf_ids')) {
            return $request->attributes->get('__high_conf_ids');
        }

        $ids = DB::table('confidentiality_levels')
            ->whereIn('code', ['confidential', 'restricted', 'secret', 'top_secret'])
            ->pluck('id')
            ->all();

        if ($request && $request instanceof \Illuminate\Http\Request) {
            $request->attributes->set('__high_conf_ids', $ids);
        }

        return $ids;
    }

    public static function clearConfidentialityCache(): void
    {
        if (app()->bound('request') && ($req = app('request')) && $req instanceof \Illuminate\Http\Request) {
            $req->attributes->remove('__high_conf_ids');
        }
    }

    protected $table = 'documents';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'reference_number',
        'title',
        'summary',
        'category_id',
        'confidentiality_id',
        'owner_unit_id',
        'custodian_id',
        'created_by',
        'document_date',
        'registered_at',
        'lifecycle',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'registered_at' => 'datetime',
            'lock_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<ConfidentialityLevel, $this>
     */
    public function confidentiality(): BelongsTo
    {
        return $this->belongsTo(ConfidentialityLevel::class, 'confidentiality_id');
    }

    public function ownerUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'owner_unit_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderByDesc('version_number');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'document_id')->ofMany('version_number', 'max');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'document_tags');
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'source_id');
    }

    public function relations(): HasMany
    {
        return $this->outgoingRelations();
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'target_id');
    }

    public function relatedDocuments(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_relations', 'source_id', 'target_id')
            ->withPivot('relation_type_id');
    }

    public function grants(): HasMany
    {
        return $this->hasMany(DocumentGrant::class, 'document_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class, 'document_id');
    }

    public function releaseEvents(): HasMany
    {
        return $this->hasMany(DocumentReleaseEvent::class, 'document_id');
    }

    public function correspondence(): HasOne
    {
        return $this->hasOne(Correspondence::class, 'document_id');
    }

    public function archiveEntry(): HasOne
    {
        return $this->hasOne(ArchiveEntry::class, 'document_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(DocumentMovement::class, 'document_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DocumentAssignment::class, 'document_id');
    }

    public function physicalOriginals(): HasMany
    {
        return $this->hasMany(PhysicalOriginal::class, 'document_id');
    }

    public function legalHolds(): BelongsToMany
    {
        return $this->belongsToMany(LegalHold::class, 'legal_hold_documents', 'document_id', 'hold_id');
    }

    public function assignmentResponses(): HasMany
    {
        return $this->hasMany(AssignmentResponse::class, 'response_document_id');
    }

    public function fulfilledAssignments(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentAssignment::class,
            'assignment_responses',
            'response_document_id',
            'assignment_id'
        )->withPivot(['relationship_role', 'is_fulfilling', 'linked_by', 'linked_at']);
    }

    /**
     * Resolve the owning Document for a given FileObject ID across all supported attachment types.
     */
    public static function resolveForFile(int $fileId): ?self
    {
        // 1. Primary or attachment file linked through DocumentVersion
        $versionFile = VersionFile::with(['documentVersion.document.confidentiality'])
            ->where('file_object_id', $fileId)
            ->first();

        if ($versionFile?->documentVersion?->document) {
            return $versionFile->documentVersion->document;
        }

        // 2. Correspondence dispatch evidence file
        $dispatch = CorrespondenceDispatch::with([
            'documentVersion.document.confidentiality',
            'participant.correspondence.document.confidentiality'
        ])
            ->where('evidence_file_id', $fileId)
            ->first();

        if ($dispatch?->documentVersion?->document) {
            return $dispatch->documentVersion->document;
        }
        if ($dispatch?->participant?->correspondence?->document) {
            return $dispatch->participant->correspondence->document;
        }

        // 3. Digital signature signed or source file
        $signature = DigitalSignature::with(['request.documentVersion.document.confidentiality'])
            ->where(function ($q) use ($fileId) {
                $q->where('signed_file_id', $fileId)
                  ->orWhere('source_file_id', $fileId);
            })
            ->first();

        if ($signature?->request?->documentVersion?->document) {
            return $signature->request->documentVersion->document;
        }

        // 4. File derivative (e.g., thumbnail or converted scan)
        $derivative = FileDerivative::where('result_file_id', $fileId)->first();
        if ($derivative && (int) $derivative->source_file_id !== $fileId) {
            return self::resolveForFile((int) $derivative->source_file_id);
        }

        // 5. Stamp application source or result file
        $stampApp = StampApplication::with(['documentVersion.document.confidentiality'])
            ->where(function ($q) use ($fileId) {
                $q->where('result_file_id', $fileId)
                  ->orWhere('source_file_id', $fileId);
            })
            ->first();

        if ($stampApp?->documentVersion?->document) {
            return $stampApp->documentVersion->document;
        }

        return null;
    }

    /**
     * Check whether a user possesses elevated security clearance for high-confidentiality records.
     * Clearance is strictly granted via staff.view_sensitive (documents.delete is NOT clearance).
     */
    public function hasConfidentialClearance(User $user, int $ownerUnitId): bool
    {
        return $user->hasPermission('staff.view_sensitive', $ownerUnitId)
            || $user->hasPermission('staff.view_sensitive');
    }

    /**
     * Determine whether a user is authorized to perform an operation on this document.
     */
    public function allowsUser(?User $user, string $permission): bool
    {
        if (! $user || $user->status !== 'active') {
            return false;
        }

        $ownerUnitId = (int) $this->owner_unit_id;

        // Check if document has elevated confidentiality (confidential, restricted, secret, top_secret)
        $confCode = $this->confidentiality?->code;
        if (! $confCode && $this->confidentiality_id) {
            $confCode = DB::table('confidentiality_levels')->where('id', $this->confidentiality_id)->value('code');
        }
        $isHighConfidentiality = in_array($confCode, ['confidential', 'restricted', 'secret', 'top_secret'], true);

        // 1. Super admin / system configure capability (reusing confidentiality clearance check)
        if ($user->hasPermission('system.configure')) {
            if (! $isHighConfidentiality || $this->hasConfidentialClearance($user, $ownerUnitId)) {
                return true;
            }
        }

        // 2. Owner unit check
        if ($user->hasPermission($permission, $ownerUnitId)) {
            if (! $isHighConfidentiality || $this->hasConfidentialClearance($user, $ownerUnitId)) {
                return true;
            }
        }

        // 3. Active explicit discretionary grant in document_grants for this specific permission
        $permId = DB::table('permissions')->where('code', $permission)->value('id');
        $userUnitIds = $user->memberships()->pluck('unit_id')->filter()->all();

        $hasActiveGrant = $permId ? DB::table('document_grants')
            ->where('document_id', $this->id)
            ->where('permission_id', $permId)
            ->where(function ($gq) use ($user, $userUnitIds) {
                $gq->where('user_id', $user->id);
                if (! empty($userUnitIds)) {
                    $gq->orWhereIn('unit_id', $userUnitIds);
                }
            })
            ->where(function ($gq) {
                $gq->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists() : false;

        if ($hasActiveGrant) {
            return true;
        }

        // 4. Archive management: when a document has an archive record or is retired,
        // users holding authorized 'archive.view' permission (cross-unit read) can view non-confidential
        // archived records, or high-confidentiality records if holding clearance.
        // Transfer, retention, and disposal operations are NOT cross-unit and must be checked against the owning unit.
        if ($permission === 'archive.view') {
            $isArchived = $this->lifecycle === 'retired'
                || ($this->relationLoaded('archiveEntry') ? $this->archiveEntry !== null : DB::table('archive_entries')->where('document_id', $this->id)->exists());

            if ($isArchived) {
                $permittedUnits = $user->getPermittedUnitIds('archive.view');
                $hasArchivePerm = ($permittedUnits === null) || ! empty($permittedUnits);

                if ($hasArchivePerm) {
                    if (! $isHighConfidentiality || $this->hasConfidentialClearance($user, $ownerUnitId)) {
                        return true;
                    }
                }
            }
        }

        // SEC-002: Creator / Custodian intrinsic access applies ONLY to approved read operations
        $isReadOnlyOp = in_array($permission, ['documents.view', 'documents.download', 'documents.share'], true);
        if ($isReadOnlyOp && ($this->created_by === $user->id || $this->custodian_id === $user->id)) {
            return true;
        }

        // Mutating/destructive operations (update, version, delete) NEVER transfer to receiving units or assignees
        if (! $isReadOnlyOp) {
            return false;
        }

        // 5. Active receiving unit via latest institutional movement
        $latestMovement = DB::table('document_movements')
            ->where('document_id', $this->id)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if ($latestMovement && $user->hasPermission($permission, (int) $latestMovement->to_unit_id)) {
            return true;
        }

        // 6. Direct user assignment (active: unexpired and not cancelled/completed/returned)
        $hasActiveUserAssignment = DB::table('document_assignments')
            ->where('document_id', $this->id)
            ->where('assigned_user_id', $user->id)
            ->where(function ($aq) {
                $aq->whereNull('due_at')->orWhere('due_at', '>=', now());
            })
            ->whereNotExists(function ($eq) {
                $eq->select(DB::raw(1))
                   ->from('assignment_events')
                   ->whereColumn('assignment_events.assignment_id', 'document_assignments.id')
                   ->whereIn('action', ['cancelled', 'completed', 'returned']);
            })
            ->exists();

        if ($hasActiveUserAssignment) {
            $hasBasePerm = $user->hasPermission($permission, null)
                || $user->memberships()->pluck('unit_id')->contains(fn ($uId) => $user->hasPermission($permission, (int) $uId));
            if ($hasBasePerm) {
                return true;
            }
        }

        // 7. Unit-level assignment (active)
        $activeUnitAssignmentIds = DB::table('document_assignments')
            ->where('document_id', $this->id)
            ->whereNotNull('assigned_unit_id')
            ->where(function ($aq) {
                $aq->whereNull('due_at')->orWhere('due_at', '>=', now());
            })
            ->whereNotExists(function ($eq) {
                $eq->select(DB::raw(1))
                   ->from('assignment_events')
                   ->whereColumn('assignment_events.assignment_id', 'document_assignments.id')
                   ->whereIn('action', ['cancelled', 'completed', 'returned']);
            })
            ->pluck('assigned_unit_id')
            ->filter()
            ->all();

        foreach ($activeUnitAssignmentIds as $aUnitId) {
            if ($user->hasPermission($permission, (int) $aUnitId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scope a query to only include documents accessible by the given user for a specific capability.
     *
     * Confinement rules:
     * 1. Unauthenticated or inactive users access nothing.
     * 2. System administrators carrying 'system.configure' have global authoritative access.
     * 3. Permitted units are evaluated (ownership, movements, assignments, discretionary grants).
     * 4. High-confidentiality records (confidential, restricted, secret, top_secret) are excluded
     *    unless the user possesses 'staff.view_sensitive' in the owning unit (or globally), OR is the
     *    creator/custodian, OR holds an active direct document_grant, OR has an active assignment.
     */
    public function scopeAccessibleBy(Builder $query, ?User $user, string $permission = 'documents.view'): Builder
    {
        if (! $user || $user->status !== 'active') {
            return $query->whereRaw('1 = 0');
        }

        // Global system administrator has authoritative access across all records
        if ($user->hasPermission('system.configure')) {
            return $query;
        }

        $permittedUnitIds = $user->getPermittedUnitIds($permission);
        $userId = $user->id;

        // If user holds no units for this permission and not global, check personal grants/assignments/creator only
        if ($permittedUnitIds !== null && empty($permittedUnitIds)) {
            return $query->where(function (Builder $q) use ($userId, $permission) {
                $q->where('documents.created_by', $userId)
                  ->orWhere('documents.custodian_id', $userId)
                  ->orWhereHas('assignments', fn ($aq) => $aq->where('assigned_user_id', $userId))
                  ->orWhereHas('grants', function ($gq) use ($userId, $permission) {
                      $gq->where('user_id', $userId)
                         ->whereHas('permission', fn ($pq) => $pq->where('code', $permission))
                         ->where(fn ($sq) => $sq->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                  });
            });
        }

        $sensitiveUnitIds = $user->getPermittedUnitIds('staff.view_sensitive');
        $hasGlobalSensitive = ($sensitiveUnitIds === null);
        $sensitiveUnitIds = $sensitiveUnitIds ?? [];

        $highConfIds = static::getHighConfidentialityIds();

        // Parity rule for archive.view: cross-unit read for archived records with clearance boundary
        if ($permission === 'archive.view') {
            $permittedArchiveUnits = $user->getPermittedUnitIds('archive.view');
            $hasArchiveView = ($permittedArchiveUnits === null) || ! empty($permittedArchiveUnits);

            if (! $hasArchiveView) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $q) use ($user, $highConfIds) {
                $q->where(function ($sub) {
                    $sub->where('documents.lifecycle', 'retired')
                        ->orWhereExists(function ($eq) {
                            $eq->select(DB::raw(1))
                               ->from('archive_entries')
                               ->whereColumn('archive_entries.document_id', 'documents.id');
                        });
                });

                if (! empty($highConfIds)) {
                    $q->where(function (Builder $confQ) use ($highConfIds, $user) {
                        $confQ->whereNotIn('documents.confidentiality_id', $highConfIds)
                              ->orWhere(function (Builder $clearQ) use ($user) {
                                  $sensitiveUnits = $user->getPermittedUnitIds('staff.view_sensitive');
                                  if ($sensitiveUnits === null) {
                                      $clearQ->whereRaw('1 = 1');
                                  } elseif (! empty($sensitiveUnits)) {
                                      $clearQ->whereIn('documents.owner_unit_id', $sensitiveUnits);
                                  } else {
                                      $clearQ->whereRaw('1 = 0');
                                  }
                              });
                    });
                }
            });
        }

        return $query->where(function (Builder $q) use (
            $permittedUnitIds,
            $userId,
            $highConfIds,
            $hasGlobalSensitive,
            $sensitiveUnitIds
        ) {
            // 1. Departmental / Ownership confinement
            $q->where(function (Builder $unitQ) use ($permittedUnitIds, $userId) {
                if ($permittedUnitIds === null) {
                    $unitQ->whereRaw('1 = 1');
                } else {
                    $unitQ->whereIn('documents.owner_unit_id', $permittedUnitIds)
                          ->orWhereHas('movements', fn ($mq) => $mq->whereIn('to_unit_id', $permittedUnitIds))
                          ->orWhereHas('assignments', fn ($aq) => $aq->whereIn('assigned_unit_id', $permittedUnitIds)->orWhere('assigned_user_id', $userId))
                          ->orWhereHas('grants', fn ($gq) => $gq->where(fn ($sq) => $sq->where('user_id', $userId)->orWhereIn('unit_id', $permittedUnitIds))->where(fn ($sq) => $sq->whereNull('expires_at')->orWhere('expires_at', '>', now())));
                }
            });

            // 2. Elevated Confidentiality boundary
            if (! empty($highConfIds)) {
                $q->where(function (Builder $confQ) use ($highConfIds, $hasGlobalSensitive, $sensitiveUnitIds, $userId) {
                    $confQ->whereNotIn('documents.confidentiality_id', $highConfIds)
                          ->orWhere(function (Builder $elevatedQ) use ($hasGlobalSensitive, $sensitiveUnitIds, $userId) {
                              if ($hasGlobalSensitive) {
                                  $elevatedQ->whereRaw('1 = 1');
                              } else {
                                  $elevatedQ->where(function (Builder $sq) use ($sensitiveUnitIds, $userId) {
                                      if (! empty($sensitiveUnitIds)) {
                                          $sq->whereIn('documents.owner_unit_id', $sensitiveUnitIds);
                                      }
                                      $sq->orWhere('documents.created_by', $userId)
                                         ->orWhere('documents.custodian_id', $userId)
                                         ->orWhereHas('assignments', fn ($aq) => $aq->where('assigned_user_id', $userId))
                                         ->orWhereHas('grants', function ($gq) use ($userId) {
                                             $gq->where('user_id', $userId)
                                                ->where(fn ($sub) => $sub->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                                         });
                                  });
                              }
                          });
                });
            }
        });
    }
}
