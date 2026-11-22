import json
import os

with open(r'c:\Users\IT3\Desktop\DMS\server\models_complete_audit.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Define domain groupings
domains = {
    "Domain 1: Identity, Organization & Access Control": [
        "User", "OrganizationUnit", "UnitType", "OrganizationMembership", "PrimaryMembership",
        "Role", "Permission", "RolePermission", "UserRole", "UserPermission",
        "AuthorizationScope", "LegacyTransitionToken"
    ],
    "Domain 2: Repository, Core Documents & Files": [
        "Document", "DocumentCategory", "ConfidentialityLevel", "DocumentVersion", "VersionFile",
        "FileObject", "DocumentRelation", "DocumentRelationType", "DocumentTag", "Tag",
        "DocumentComment", "DocumentGrant", "DocumentMovement", "MovementReceipt",
        "DocumentReleaseEvent", "DocumentTextExtraction", "DocumentTemplate", "TemplateVersion",
        "FileDerivative", "FileIntegrityCheck", "DigitizationRecord"
    ],
    "Domain 3: Workflow & Routing": [
        "WorkflowDefinition", "WorkflowVersion", "WorkflowState", "WorkflowTransition",
        "WorkflowInstance", "WorkflowTask", "WorkflowTaskParticipant", "WorkflowTaskDecision",
        "WorkflowEvent", "WorkflowDecisionEvent", "RoutingPolicy"
    ],
    "Domain 4: Correspondence & External Parties": [
        "Correspondence", "CorrespondenceType", "CorrespondenceProcedure",
        "CorrespondenceParticipant", "CommunicationParty", "ExternalParty", "ExternalPartyContact",
        "CorrespondenceDispatch", "DispatchMethod", "OfficeProcedureVersion", "NumberingPolicy"
    ],
    "Domain 5: Assignments & Tasks": [
        "DocumentAssignment", "AssignmentParticipant", "AssignmentResponse", "AssignmentEvent",
        "Priority"
    ],
    "Domain 6: Archive, Retention & Legal Holds": [
        "ArchiveCollection", "ArchiveEntry", "ArchiveAction", "DispositionDecision",
        "DispositionExecution", "DispositionRequest", "RetentionPolicy", "RetentionPolicyVersion",
        "LegalHold", "LegalHoldDocument"
    ],
    "Domain 7: Staff HR & Physical Records": [
        "StaffProfile", "StaffEmploymentRecord", "StaffQualification", "QualificationType",
        "StaffCertificate", "StaffDocument", "HrRecordType", "PhysicalOriginal",
        "PhysicalStorageLocation", "PhysicalCustodyEvent", "PhysicalDispositionTarget"
    ],
    "Domain 8: Academic Research, Projects & Publications": [
        "Project", "ProjectType", "ProjectProposal", "ProjectProposalMember",
        "ProjectProposalWorkflow", "ProjectProgressReport", "ProjectPublication",
        "ResearchRecord", "ResearchAuthor", "ResearchPublication", "Publication",
        "PublicationAuthor"
    ],
    "Domain 9: Accreditation Frameworks & Cycles": [
        "AccreditationFramework", "AccreditationFrameworkVersion", "AccreditationStandard",
        "AccreditationCycle", "AccreditationReview", "AccreditationEvidence"
    ],
    "Domain 10: Digital Signatures & Stamps": [
        "SignatureRequest", "DigitalSignature", "SigningIdentity", "SignatureVerification",
        "StampDefinition", "StampVersion", "StampAuthorization", "StampApplication"
    ],
    "Domain 11: Audit, Notifications, System Operations & Transfer": [
        "AuditEvent", "NotificationEvent", "NotificationDelivery", "UserNotification",
        "ExportJob", "ImportBatch", "ImportRow", "BackupRun", "RestoreDrill",
        "IdempotencyRecord", "DataExportOperation", "DataImportOperation", "DataImportRowError",
        "SystemSetting", "ReferenceSequence", "Institution", "OutboxEvent"
    ]
}

# Verify all models accounted for
all_models = set(data.keys())
grouped_models = set()
for d_name, m_list in domains.items():
    for m in m_list:
        grouped_models.add(m)

missing = all_models - grouped_models
if missing:
    print("Warning: Missing models from domains:", missing)
else:
    print(f"All {len(all_models)} models accounted for across {len(domains)} domains.")

def format_model(m):
    cname = m['class']
    lines = []
    lines.append(f"### Model: `{cname}`")
    
    # 1. Class & Table
    comp = f" | Composite PK: `{m['composite_key']}`" if m.get('composite_key') else ""
    lines.append(f"- **(1) Table & PK:** table=`{m['table']}`, PK=`{m['primary_key']}`, incrementing=`{m['incrementing']}`, keyType=`{m['key_type']}`{comp}")
    
    # 2. Fillable / Guarded
    if m.get('fillable'):
        lines.append(f"- **(2) Fillable:** `[{', '.join(m['fillable'])}]`")
    elif m.get('guarded') == ['*']:
        lines.append(f"- **(2) Guarded:** `['*']` (No fillable)")
    elif m.get('guarded') == []:
        lines.append(f"- **(2) Guarded:** `[]` (Unguarded)")
    else:
        lines.append(f"- **(2) Guarded:** `[{', '.join(m.get('guarded', []))}]`")

    # 3. Casts
    casts = m.get('casts', {})
    if isinstance(casts, dict):
        casts_str = ", ".join([f"`{k}`: `{v}`" for k, v in casts.items() if k != 'id'])
    else:
        casts_str = ""
    lines.append(f"- **(3) Casts:** {casts_str if casts_str else 'none (except default PK id: int)'}")

    # 4. Relations
    rels = m.get('relations', {})
    if isinstance(rels, dict) and rels:
        lines.append(f"- **(4) Relationships ({len(rels)}):**")
        for rname, rinfo in rels.items():
            rtype = rinfo.get('type')
            rmodel = rinfo.get('related_model')
            fk = rinfo.get('foreign_key')
            lk = rinfo.get('local_or_owner_key')
            pivot = f", pivot=`{rinfo.get('pivot_table')}`" if rinfo.get('pivot_table') else ""
            lines.append(f"  - `{rname}()`: **{rtype}** -> `{rmodel}` (FK=`{fk}`, Local/OwnerKey=`{lk}`{pivot})")
    else:
        lines.append(f"- **(4) Relationships:** None")

    # 5. SoftDeletes
    lines.append(f"- **(5) SoftDeletes:** {'Yes' if m.get('uses_soft_deletes') else 'No'}")

    # 6. Eager $with
    lines.append(f"- **(6) Eager Loading ($with):** {m.get('with') if m.get('with') else 'None'}")

    # 7. Scopes
    actual_scopes = [s for s in m.get('scopes', []) if s.get('method') in ['scopeAccessibleBy']]
    if actual_scopes:
        scopes_str = ", ".join([f"`{s['name']}` ({s['type']})" for s in actual_scopes])
        lines.append(f"- **(7) Scopes:** {scopes_str}")
    else:
        lines.append(f"- **(7) Scopes:** None")

    # 8. Boot & Events
    boot_items = []
    if m.get('boot_methods'):
        boot_items.append(f"methods: `[{', '.join(m['boot_methods'])}]`")
    if m.get('events'):
        boot_items.append(f"events: `[{', '.join(m['events'])}]`")
    if m.get('uses_guards_version'):
        boot_items.append("uses `GuardsVersionImmutability` (updating/deleting events throw RuntimeException unless depth > 0)")
    lines.append(f"- **(8) Boot / Events:** {'; '.join(boot_items) if boot_items else 'None'}")

    # 9. Lock version
    if m.get('has_lock_version'):
        lines.append(f"- **(9) Optimistic Locking (lock_version):** YES (`{', '.join(m['lock_version_details'])}`)")
    else:
        lines.append(f"- **(9) Optimistic Locking (lock_version):** None")

    # 10. N+1 Risks
    risks = m.get('n1_risks', [])
    if risks:
        lines.append(f"- **(10) N+1 Risks / Custom Query Methods:**")
        for r in risks:
            lines.append(f"  - WARNING: `{r.get('method')}`: {r.get('risk')}")
    else:
        lines.append(f"- **(10) N+1 Risks / Custom Query Methods:** None identified")

    return "\n".join(lines)

# Write out per-domain summaries
os.makedirs(r'c:\Users\IT3\Desktop\DMS\server\domain_summaries', exist_ok=True)
for d_idx, (d_name, m_list) in enumerate(domains.items(), 1):
    output_lines = [f"## {d_name} ({len(m_list)} Models)\n"]
    for m_name in m_list:
        output_lines.append(format_model(data[m_name]))
        output_lines.append("")
    filepath = rf'c:\Users\IT3\Desktop\DMS\server\domain_summaries\domain_{d_idx}.md'
    with open(filepath, 'w', encoding='utf-8') as out:
        out.write("\n".join(output_lines))
    print(f"Wrote {filepath} ({len(m_list)} models)")
