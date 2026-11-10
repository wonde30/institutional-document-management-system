<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    use HasFactory;

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
        $dispatch = CorrespondenceDispatch::with(['documentVersion.document.confidentiality'])
            ->where('evidence_file_id', $fileId)
            ->first();

        if ($dispatch?->documentVersion?->document) {
            return $dispatch->documentVersion->document;
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

        return null;
    }

    /**
     * Determine whether a user is authorized to perform an operation on this document.
     */
    public function allowsUser(?User $user, string $permission): bool
    {
        if (! $user || $user->status !== 'active') {
            return false;
        }

        // 1. Super admin always has authoritative access
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $ownerUnitId = (int) $this->owner_unit_id;

        // Check if document has elevated confidentiality (confidential, restricted, secret, top_secret)
        $confCode = $this->confidentiality?->code;
        if (! $confCode && $this->confidentiality_id) {
            $confCode = DB::table('confidentiality_levels')->where('id', $this->confidentiality_id)->value('code');
        }
        $isHighConfidentiality = in_array($confCode, ['confidential', 'restricted', 'secret', 'top_secret'], true);

        // 2. Owner unit check
        if ($user->hasPermission($permission, $ownerUnitId)) {
            if (! $isHighConfidentiality) {
                return true;
            }

            // High confidentiality documents require elevated clearance: leadership, controller, records officer, or sensitive view grant
            $hasElevatedClearance = $user->hasRole('department_head')
                || $user->hasRole('document_controller')
                || $user->hasRole('records_officer')
                || $user->hasPermission('staff.view_sensitive', $ownerUnitId);

            if ($hasElevatedClearance) {
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
}
