<?php

declare(strict_types=1);

return [
    'privacy' => [
        'type' => 'privacy',
        'title' => 'Privacy & Data Protection Policy',
        'subtitle' => 'Institutional Guidelines for Authorized Employees & Administrative Stakeholders',
        'effective_date' => '2026-09-01',
        'last_updated' => '2026-11-18',
        'statutory_basis' => 'Federal Democratic Republic of Ethiopia Proclamation No. 1152/2019',
        'sections' => [
            [
                'heading' => 'Institutional Scope & Governance Boundary',
                'content' => 'This Privacy Policy governs the processing, retention, and access management of administrative records, official letters, curricula, and staff employment data processed within the Wollo University Administrative and Document Management System (WU-DMS). Operated under the authority of the President\'s Office and the Information Technology Directorate, WU-DMS applies strictly to official university business.',
            ],
            [
                'heading' => 'Role-Based Access Boundaries & Zero Trust Scope',
                'content' => 'User privileges are strictly partitioned into Global, Unit, and Subtree authorization scopes. Staff officers may only access records formally assigned to their designated department or office. Cross-departmental document access requires formal dispatch, assignment, or written administrative authorization.',
            ],
            [
                'heading' => 'Stateful Session Security & Browser Privacy',
                'content' => 'Authentication is enforced via encrypted, HTTP-only, SameSite session cookies powered by Laravel Sanctum. No persistent authentication tokens, cryptographic secrets, or sensitive document payloads are stored in the browser\'s localStorage or sessionStorage.',
            ],
            [
                'heading' => 'Tamper-Evident Audit Logging & Telemetry',
                'content' => 'In compliance with Ethiopian National Archive and Library Agency (NALA) standards and Higher Education Relevance and Quality Agency (HERQA) criteria, all actions—including document uploads, views, version modifications, routing dispatches, and role reassignments—are recorded in an immutable, append-only audit trail with actor identity, timestamp, and IP address telemetry.',
            ],
            [
                'heading' => 'Institutional Data Protection Officer Contact',
                'content' => 'For inquiries concerning document classification, privacy audits, or data rights, contact the Information Technology Directorate, Kombolcha Institute of Technology (KIoT) / Dessie Campus, Wollo University, Ethiopia.',
            ],
        ],
    ],
    'terms' => [
        'type' => 'terms',
        'title' => 'Terms of Use & Administrative Service Agreement',
        'subtitle' => 'Standard Operating Obligations for Wollo University Authorized Users',
        'effective_date' => '2026-09-01',
        'last_updated' => '2026-11-18',
        'statutory_basis' => 'Proclamation No. 1072/2018 (Federal Archives and Library) & Senate Legislation',
        'sections' => [
            [
                'heading' => 'Authorized User Acceptance & Institutional Identity',
                'content' => 'Access to the Wollo University Document Management System is strictly restricted to active academic, administrative, and management staff with verified institutional credentials (@wu.edu.et). Utilizing the system constitutes formal acceptance of university information security bylaws and administrative codes of conduct.',
            ],
            [
                'heading' => 'Account Non-Transferability & Credential Integrity',
                'content' => 'User credentials, passwords, and cryptographic signing keys are personal, non-transferable, and subject to regular security rotation. Sharing institutional accounts or allowing unauthorized third-party access is a severe violation of university policy subject to administrative and legal sanctions.',
            ],
            [
                'heading' => 'Cryptographic Digital Signatures & Non-Repudiation',
                'content' => 'Digital signatures and authorized institutional seals placed upon official correspondence, Senate decisions, and academic records carry full administrative validity under Ethiopian Electronic Transactions Proclamation. Signatures applied to finalized records are immutable and subject to non-repudiation audit verification.',
            ],
            [
                'heading' => 'Statutory Physical Originals & Archival Retention',
                'content' => 'In accordance with National Proclamation No. 1072/2018, the digitization and registration of paper correspondence does not exempt offices from the secure physical preservation of wet-ink originals. Physical originals must be filed in designated departmental archive bays and indexed with corresponding WU-DMS reference sequences.',
            ],
            [
                'heading' => 'System Availability & Operational SLA',
                'content' => 'WU-DMS is engineered for 99.9% uptime across semester operational periods. Routine maintenance windows and system backup drills are scheduled outside core academic and administrative hours in coordination with the ICT Infrastructure & Systems Directorate.',
            ],
        ],
    ],
];
