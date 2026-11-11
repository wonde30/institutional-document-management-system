<?php

/*
|--------------------------------------------------------------------------
| Data Import and Export Catalogue
|--------------------------------------------------------------------------
|
| The explicit list of datasets that may be imported or exported. Only the
| operations declared here are exposed. There is no generic table access, no
| arbitrary SQL and no unrestricted model serialization.
|
| Every entry declares:
|   label       the display name
|   direction   import | export
|   format      the supported file format
|   permission  the registered capability required to perform the operation
|   scope       how the organizational scope of the operation is decided
|   fields      the field specification, an explicit allowlist
|   duplicate   reject | skip, how a duplicate record is handled on import
|   max_rows    the bounded number of records a single operation may carry
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Importable datasets
    |----------------------------------------------------------------------
    |
    | Organizational reference data only. Business records that carry a
    | registration workflow, a confidentiality classification or a version
    | history are deliberately NOT importable: an import would bypass that
    | workflow and the audit trail that belongs to it.
    |
    */

    'organization_units' => [
        'label' => 'Organizational Units',
        'description' => 'Creates organizational units beneath the active organizational unit.',
        'direction' => 'import',
        'format' => 'csv',
        'permission' => 'organization.manage',
        'scope' => 'active_unit',
        'duplicate' => 'reject',
        'max_rows' => 2000,
        'fields' => [
            'code' => ['required' => true, 'type' => 'string', 'max' => 64],
            'name' => ['required' => true, 'type' => 'string', 'max' => 200],
            'unit_type_code' => ['required' => true, 'type' => 'string', 'max' => 32],
            'parent_code' => ['required' => false, 'type' => 'string', 'max' => 64],
            'is_active' => ['required' => false, 'type' => 'boolean', 'default' => true],
        ],
        'required_headers' => ['code', 'name', 'unit_type_code'],
    ],

    'organization_memberships' => [
        'label' => 'Organizational Memberships',
        'description' => 'Attaches existing institutional accounts to the active organizational unit.',
        'direction' => 'import',
        'format' => 'csv',
        'permission' => 'organization.manage',
        'scope' => 'active_unit',
        'duplicate' => 'reject',
        'max_rows' => 2000,
        'fields' => [
            'user_email' => ['required' => true, 'type' => 'string', 'max' => 254],
            'position_title' => ['required' => false, 'type' => 'string', 'max' => 160],
            'starts_at' => ['required' => false, 'type' => 'date'],
            'ends_at' => ['required' => false, 'type' => 'date'],
        ],
        'required_headers' => ['user_email'],
    ],

    /*
    |----------------------------------------------------------------------
    | Exportable datasets
    |----------------------------------------------------------------------
    |
    | Every export is intersected with the caller's permitted records. A
    | requested filter narrows the result and can never broaden it. Only the
    | fields listed here are written to the file.
    |
    */

    'document_catalog' => [
        'label' => 'Document Catalogue',
        'description' => 'Document metadata. Document binaries and file objects are never exported.',
        'direction' => 'export',
        'format' => 'csv',
        'permission' => 'documents.view',
        'scope' => 'permitted_units',
        'max_rows' => 10000,
        'filters' => ['lifecycle', 'category_code', 'confidentiality_code', 'date_from', 'date_to'],
        'fields' => [
            'reference_number',
            'title',
            'summary',
            'category',
            'confidentiality',
            'owner_unit',
            'document_date',
            'registered_at',
            'lifecycle',
        ],
    ],

    'correspondence_registry' => [
        'label' => 'Correspondence Registry',
        'description' => 'Incoming and outgoing correspondence metadata.',
        'direction' => 'export',
        'format' => 'csv',
        'permission' => 'correspondence.view',
        'scope' => 'permitted_units',
        'max_rows' => 10000,
        'filters' => ['purpose_code', 'type_code', 'date_from', 'date_to'],
        'fields' => [
            'reference_number',
            'purpose_code',
            'type',
            'priority',
            'external_reference',
            'correspondence_date',
            'receiving_unit',
            'received_at',
            'requested_due_at',
        ],
    ],

    'organization_directory' => [
        'label' => 'Organizational Directory',
        'description' => 'Organizational units and their members.',
        'direction' => 'export',
        'format' => 'csv',
        'permission' => 'organization.view',
        'scope' => 'permitted_units',
        'max_rows' => 10000,
        'filters' => ['unit_type_code'],
        'fields' => [
            'unit_code',
            'unit_name',
            'unit_type',
            'parent_unit_code',
            'member_name',
            'member_email',
            'member_position',
        ],
    ],

    'audit_events' => [
        'label' => 'Security Audit Ledger',
        'description' => 'The security audit trail. The free-form detail payload is never exported.',
        'direction' => 'export',
        'format' => 'csv',
        'permission' => 'audit.view',
        'scope' => 'global',
        'max_rows' => 10000,
        'filters' => ['action', 'date_from', 'date_to'],
        'fields' => [
            'occurred_at',
            'action',
            'target_type',
            'target_id',
            'actor_name',
            'ip_address',
        ],
    ],

];
