<?php

/*
|--------------------------------------------------------------------------
| Runtime Configuration Catalogue
|--------------------------------------------------------------------------
|
| The authoritative registry of the runtime settings an administrator may
| change after deployment. A key that is not declared here cannot be stored,
| edited or reset, and no client supplied key is ever accepted.
|
| Every entry declares:
|   key         the stable identifier of the setting
|   label       the display name
|   description what the setting controls
|   category    the group the setting is presented in
|   type        string | integer | boolean | select
|   default     the value used when no override is stored
|   rules       the server side validation rules for the value
|   scope       global | global_or_unit
|   options     the permitted values, for a select setting
|   editable    whether an administrator may change it
|
| Deployment and infrastructure configuration is deliberately NOT part of this
| catalogue. Those values are read from the environment and are listed, without
| their values, under the "deployment" section below.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Configuration categories
    |----------------------------------------------------------------------
    */

    'categories' => [
        'institution' => [
            'label' => 'Institutional Profile',
            'description' => 'Identity and presentation of the institution across the portal.',
        ],
        'documents' => [
            'label' => 'Document Repository',
            'description' => 'Defaults applied when a document is registered or uploaded.',
        ],
        'security' => [
            'label' => 'Session & Access',
            'description' => 'Boundaries that govern an authenticated session.',
        ],
        'workflow' => [
            'label' => 'Workflow & Routing',
            'description' => 'Default routing, due dates, and escalation parameters for institutional workflows.',
        ],
        'notifications' => [
            'label' => 'Notification Operations',
            'description' => 'Operational alert thresholds and reminder dispatch schedules.',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Runtime application settings
    |----------------------------------------------------------------------
    */

    'settings' => [

        'institution.display_name' => [
            'label' => 'Institution Display Name',
            'description' => 'Official institutional name shown in the interface, the browser title and the public portal.',
            'category' => 'institution',
            'type' => 'string',
            'default' => 'Wollo University',
            'rules' => ['required', 'string', 'max:200'],
            'scope' => 'global',
            'editable' => true,
        ],

        'institution.name_am' => [
            'label' => 'Institution Amharic Name',
            'description' => 'Official institutional Amharic name shown on institutional letterheads and portal headings.',
            'category' => 'institution',
            'type' => 'string',
            'default' => 'ወሎ ዩኒቨርሲቲ',
            'rules' => ['required', 'string', 'max:200'],
            'scope' => 'global',
            'editable' => true,
        ],

        'institution.approved_logo_path' => [
            'label' => 'Approved Institution Logo Path',
            'description' => 'Authoritative relative path to the officially approved institutional emblem or seal graphic stored in controlled institutional storage. Note: institution.logo_path is supported as a compatibility alias.',
            'category' => 'institution',
            'type' => 'string',
            'default' => null,
            'rules' => ['nullable', 'string', 'max:500'],
            'scope' => 'global',
            'editable' => true,
        ],

        'institution.letter_phone' => [
            'label' => 'Official Letter Phone',
            'description' => 'Standard telephone number printed in the institutional letter footer. Left blank until verified institutional contact is provided.',
            'category' => 'institution',
            'type' => 'string',
            'default' => null,
            'rules' => ['nullable', 'string', 'max:50'],
            'scope' => 'global',
            'editable' => true,
        ],

        'institution.letter_fax' => [
            'label' => 'Official Letter Fax',
            'description' => 'Standard fax number printed in the institutional letter footer. Left blank until verified institutional contact is provided.',
            'category' => 'institution',
            'type' => 'string',
            'default' => null,
            'rules' => ['nullable', 'string', 'max:50'],
            'scope' => 'global',
            'editable' => true,
        ],

        'institution.letter_pobox' => [
            'label' => 'Official Letter P.O. Box',
            'description' => 'Postal address printed in the institutional letter footer. Left blank until verified institutional contact is provided.',
            'category' => 'institution',
            'type' => 'string',
            'default' => null,
            'rules' => ['nullable', 'string', 'max:50'],
            'scope' => 'global',
            'editable' => true,
        ],

        'institution.letter_reply_notice_am' => [
            'label' => 'Letter Reply Notice (Amharic)',
            'description' => 'Amharic notice instructing correspondents to quote reference number and date.',
            'category' => 'institution',
            'type' => 'string',
            'default' => 'መልስ ሲፅፉ የደብዳቤያችንን ቁጥርና ቀን ይጠቅሱ',
            'rules' => ['required', 'string', 'max:255'],
            'scope' => 'global',
            'editable' => true,
        ],

        'institution.letter_reply_notice_en' => [
            'label' => 'Letter Reply Notice (English)',
            'description' => 'English notice instructing correspondents to quote reference number when replying.',
            'category' => 'institution',
            'type' => 'string',
            'default' => 'please quote our ref. number when replying',
            'rules' => ['required', 'string', 'max:255'],
            'scope' => 'global',
            'editable' => true,
        ],

        'documents.max_upload_size_mb' => [
            'label' => 'Maximum Upload Size',
            'description' => 'Largest document or version upload accepted, in megabytes.',
            'category' => 'documents',
            'type' => 'integer',
            'default' => 100,
            'rules' => ['required', 'integer', 'min:1', 'max:1024'],
            'scope' => 'global',
            'editable' => true,
        ],

        'documents.default_confidentiality_level_code' => [
            'label' => 'Default Classification Level',
            'description' => 'Classification applied when a document is registered without an explicit level. The public level is never permitted as a default. A unit scoped value overrides the global value for that organizational unit.',
            'category' => 'documents',
            'type' => 'select',
            'default' => 'internal',
            'options' => ['internal', 'confidential', 'restricted'],
            'rules' => ['required', 'string', 'in:internal,confidential,restricted'],
            'scope' => 'global_or_unit',
            'editable' => true,
        ],

        'session.lifetime_minutes' => [
            'label' => 'Session Idle Lifetime',
            'description' => 'Minutes of inactivity before an authenticated session is expired.',
            'category' => 'security',
            'type' => 'integer',
            'default' => 120,
            'rules' => ['required', 'integer', 'min:5', 'max:1440'],
            'scope' => 'global',
            'editable' => true,
        ],

        'data_imports.max_file_size_mb' => [
            'label' => 'Maximum Import File Size',
            'description' => 'Largest dataset file accepted for inspection, in megabytes. Distinct from the document upload limit, because an import file is a structured dataset rather than a repository record.',
            'category' => 'security',
            'type' => 'integer',
            'default' => 20,
            'rules' => ['required', 'integer', 'min:1', 'max:256'],
            'scope' => 'global',
            'editable' => true,
        ],

        'data_imports.temporary_file_retention_hours' => [
            'label' => 'Temporary Import File Retention',
            'description' => 'Hours an inspected import file is retained before it may be removed. A file is only removed once the import has been inspected and not executed, so a source file that produced an audit record is never deleted.',
            'category' => 'security',
            'type' => 'integer',
            'default' => 168,
            'rules' => ['required', 'integer', 'min:1', 'max:8760'],
            'scope' => 'global',
            'editable' => true,
        ],

        /*
        | A source managed invariant. It is displayed so the administrator can
        | confirm the numbering scheme in force, but it is never stored: the
        | scheme is implemented in the code and is not configurable.
        */
        'documents.reference_number_scheme' => [
            'label' => 'Reference Number Scheme',
            'description' => 'Pattern applied to the reference number of a registered document. Defined by the application and not configurable.',
            'category' => 'documents',
            'type' => 'string',
            'default' => 'WU/DOC/{YEAR}/{SEQUENCE}',
            'rules' => ['required', 'string', 'max:100'],
            'scope' => 'global',
            'editable' => false,
        ],

        'workflow.default_due_days' => [
            'label' => 'Default Task Due Days',
            'description' => 'Standard business days allowed for workflow task completion before marking overdue.',
            'category' => 'workflow',
            'type' => 'integer',
            'default' => 5,
            'rules' => ['required', 'integer', 'min:1', 'max:90'],
            'scope' => 'global',
            'editable' => true,
        ],

        'workflow.escalation_enabled' => [
            'label' => 'Automatic Workflow Escalation',
            'description' => 'Automatically notify department supervisor when assigned tasks exceed due date.',
            'category' => 'workflow',
            'type' => 'boolean',
            'default' => true,
            'rules' => ['required', 'boolean'],
            'scope' => 'global',
            'editable' => true,
        ],

        'notifications.overdue_reminders_enabled' => [
            'label' => 'Overdue Task Reminders',
            'description' => 'Dispatch automated reminders for pending correspondence and workflow assignments.',
            'category' => 'notifications',
            'type' => 'boolean',
            'default' => true,
            'rules' => ['required', 'boolean'],
            'scope' => 'global',
            'editable' => true,
        ],

        'notifications.daily_digest_time' => [
            'label' => 'Daily Digest Dispatch Time',
            'description' => 'Time of day (24-hour format HH:MM) when daily operational summaries are delivered.',
            'category' => 'notifications',
            'type' => 'string',
            'default' => '08:00',
            'rules' => ['required', 'string', 'regex:/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/'],
            'scope' => 'global',
            'editable' => true,
        ],

    ],

    /*
    |----------------------------------------------------------------------
    | Deployment and infrastructure configuration
    |----------------------------------------------------------------------
    |
    | These values are managed by the deployment environment. They are listed
    | so the administrator can confirm the boundary an instance is running
    | under, but they are never editable through the application.
    |
    | A sensitive entry exposes the key name only. Its value is never read,
    | returned or displayed.
    |
    */

    'deployment' => [
        ['key' => 'app.env', 'label' => 'Application Environment', 'value' => env('APP_ENV'), 'sensitive' => false],
        ['key' => 'app.debug', 'label' => 'Debug Mode', 'value' => env('APP_DEBUG'), 'sensitive' => false],
        ['key' => 'app.url', 'label' => 'API Base URL', 'value' => env('APP_URL'), 'sensitive' => false],
        ['key' => 'app.frontend_url', 'label' => 'Portal Base URL', 'value' => env('FRONTEND_URL'), 'sensitive' => false],
        ['key' => 'app.key', 'label' => 'Application Encryption Key', 'value' => null, 'sensitive' => true],
        ['key' => 'db.connection', 'label' => 'Database Driver', 'value' => env('DB_CONNECTION'), 'sensitive' => false],
        ['key' => 'db.database', 'label' => 'Database Schema', 'value' => env('DB_DATABASE'), 'sensitive' => false],
        ['key' => 'db.host', 'label' => 'Database Host', 'value' => env('DB_HOST'), 'sensitive' => false],
        ['key' => 'db.username', 'label' => 'Database Account', 'value' => env('DB_USERNAME'), 'sensitive' => true],
        ['key' => 'db.password', 'label' => 'Database Credential', 'value' => null, 'sensitive' => true],
        ['key' => 'session.driver', 'label' => 'Session Store', 'value' => env('SESSION_DRIVER'), 'sensitive' => false],
        ['key' => 'filesystems.default', 'label' => 'Document Storage Driver', 'value' => env('FILESYSTEM_DISK'), 'sensitive' => false],
        ['key' => 'aws.bucket', 'label' => 'Object Storage Bucket', 'value' => env('AWS_BUCKET'), 'sensitive' => false],
        ['key' => 'aws.secret_access_key', 'label' => 'Object Storage Credential', 'value' => null, 'sensitive' => true],
        ['key' => 'mail.default', 'label' => 'Mail Transport', 'value' => env('MAIL_MAILER'), 'sensitive' => false],
        ['key' => 'mail.from_address', 'label' => 'Mail Sender Address', 'value' => env('MAIL_FROM_ADDRESS'), 'sensitive' => false],
        ['key' => 'mail.password', 'label' => 'Mail Credential', 'value' => null, 'sensitive' => true],
        ['key' => 'queue.default', 'label' => 'Queue Backend', 'value' => env('QUEUE_CONNECTION'), 'sensitive' => false],
        ['key' => 'cache.default', 'label' => 'Cache Backend', 'value' => env('CACHE_STORE'), 'sensitive' => false],
        ['key' => 'log.default', 'label' => 'Log Channel', 'value' => env('LOG_CHANNEL'), 'sensitive' => false],
        ['key' => 'log.level', 'label' => 'Log Level', 'value' => env('LOG_LEVEL'), 'sensitive' => false],
        ['key' => 'cors.allowed_origins', 'label' => 'Allowed Origins', 'value' => env('CORS_ALLOWED_ORIGINS'), 'sensitive' => false],
    ],

    /*
    |----------------------------------------------------------------------
    | Settings that may be published without authentication
    |----------------------------------------------------------------------
    |
    | Only settings that are safe to expose on the public portal. No
    | administrative value and no secret is ever published here.
    |
    */

    'public' => [
        'institution.display_name',
    ],

];
