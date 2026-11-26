<?php

declare(strict_types=1);

return [
    [
        'key' => 'repository',
        'title' => 'Document Repository',
        'description' => 'Centralized storage with deterministic reference sequences (WU/DOC/YEAR/SEQ), categorical tagging, version releases, and secure file handling.',
        'icon' => 'FileText',
        'route' => '/documents',
        'order' => 1,
        'highlights' => [
            'Deterministic reference numbering',
            'Categorical metadata tagging',
            'Multi-revision version control',
        ],
    ],
    [
        'key' => 'correspondence',
        'title' => 'Correspondence Registry',
        'description' => 'Incoming letters with external reference capture, priority assignment, required actions, and outgoing dispatch records linked to source correspondence.',
        'icon' => 'Send',
        'route' => '/correspondence',
        'order' => 2,
        'highlights' => [
            'External reference tracking',
            'Priority-based SLA dispatch',
            'Direct dispatch chain-of-custody',
        ],
    ],
    [
        'key' => 'routing',
        'title' => 'Inter-Office Movement',
        'description' => 'Controlled office-to-office document forwarding, formal movement acknowledgments, routing instructions, and multi-actor communication history.',
        'icon' => 'Layers',
        'route' => '/routing',
        'order' => 3,
        'highlights' => [
            'Inter-unit document movement',
            'Actionable routing instructions',
            'Signed movement receipt audit',
        ],
    ],
    [
        'key' => 'archive',
        'title' => 'Archive & Retention',
        'description' => 'Structured transfer of inactive records, retention schedule monitoring, archival bay/shelf tracking, and authorized record disposal workflows.',
        'icon' => 'Archive',
        'route' => '/archive',
        'order' => 4,
        'highlights' => [
            'Statutory retention schedules',
            'Physical bay/shelf location indexing',
            'Legal hold preservation lock',
        ],
    ],
    [
        'key' => 'accreditation',
        'title' => 'Accreditation Evidence',
        'description' => 'HERQA national quality assurance standard mapping, frozen version evidence dossiers, review acceptance trails, and exportable institutional compilations.',
        'icon' => 'Award',
        'route' => '/accreditation',
        'order' => 5,
        'highlights' => [
            '10 National HERQA standards',
            'Immutable version freeze dossiers',
            'Formal academic peer-review cycle',
        ],
    ],
    [
        'key' => 'signatures',
        'title' => 'Signatures & Seals',
        'description' => 'Cryptographic RSA key-pair signatures, institutional stamp artwork authorization, non-repudiation audit trails, and formal verification certificates.',
        'icon' => 'FileCheck',
        'route' => '/signatures',
        'order' => 6,
        'highlights' => [
            'RSA cryptographic digital signatures',
            'Authorized stamp artwork placement',
            'Non-repudiation verification ledger',
        ],
    ],
    [
        'key' => 'research',
        'title' => 'Research & Projects',
        'description' => 'Institutional repository for finalized faculty research publications, grant project proposals, innovation milestones, and academic outputs.',
        'icon' => 'BookOpen',
        'route' => '/research',
        'order' => 7,
        'highlights' => [
            'Finalized publication repository',
            'Faculty co-authorship attribution',
            'Grant progress report tracking',
        ],
    ],
    [
        'key' => 'governance',
        'title' => 'Multi-Unit Scoped RBAC',
        'description' => 'Fine-grained dynamic permissions bounded by Global, Unit, or Subtree administrative scope. Cross-department privacy guarantees.',
        'icon' => 'ShieldCheck',
        'route' => '/rbac/roles',
        'order' => 8,
        'highlights' => [
            'Global, Unit & Subtree scoping',
            'Contextual active unit switching',
            'Append-only tamper-proof telemetry',
        ],
    ],
];
