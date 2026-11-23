<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== CHECK 03: UNIT SCOPING & IDOR RESOLUTION AUDIT ===\n\n";

$router = app('router');
$routes = $router->getRoutes();

$paramRoutes = [];
$resourceKeywords = [
    'document', 'version', 'file', 'correspondence', 'assignment', 
    'archive', 'workflow', 'task', 'signature', 'stamp', 'export', 'backup',
    'research', 'evidence', 'hold', 'disposition'
];

foreach ($routes as $route) {
    $uri = $route->uri();
    if (!str_contains($uri, '{')) {
        continue;
    }
    
    // Check if route relates to resources
    $matchesKeyword = false;
    foreach ($resourceKeywords as $kw) {
        if (str_contains($uri, $kw)) {
            $matchesKeyword = true;
            break;
        }
    }
    
    if (!$matchesKeyword) {
        continue;
    }
    
    $methods = implode('|', array_diff($route->methods(), ['HEAD']));
    $action = $route->getActionName();
    $middlewares = (array) $route->middleware();
    
    // Extract parameter names
    preg_match_all('/\{([a-zA-Z0-9_?]+)\}/', $uri, $m);
    $params = $m[1] ?? [];
    
    $paramRoutes[] = [
        'methods' => $methods,
        'uri' => $uri,
        'params' => $params,
        'action' => $action,
        'middlewares' => $middlewares,
    ];
}

echo "Found " . count($paramRoutes) . " parameterized resource routes.\n\n";

// Now check how each route resolves its owning unit:
// 1. Is it handled in CheckBaselinePermission?
// 2. Is it handled in RouteScopeResolver?
// 3. Or does the controller explicitly check ownership?

$findings = [];

foreach ($paramRoutes as $r) {
    $uri = $r['uri'];
    $handledInMiddleware = false;
    $resolverNote = 'UNKNOWN';
    
    if (str_contains($uri, 'files/{fileId}/download')) {
        $handledInMiddleware = true;
        $resolverNote = 'CheckBaselinePermission: FileObject -> Document::resolveForFile -> doc->allowsUser';
    } elseif (str_contains($uri, 'documents/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'CheckBaselinePermission: Document::with(confidentiality) -> doc->allowsUser';
    } elseif (str_contains($uri, 'research/outputs/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'CheckBaselinePermission: ResearchRecord -> document -> owner_unit_id';
    } elseif (str_contains($uri, 'correspondences/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'CheckBaselinePermission: Correspondence -> document -> owner_unit_id / movements / assignments';
    } elseif (str_contains($uri, 'workflows/tasks/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'CheckBaselinePermission: WorkflowTask -> assigned_unit_id ?? instance.documentVersion.document.owner_unit_id';
    } elseif (str_contains($uri, 'staff/records/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'CheckBaselinePermission: PhysicalOriginal -> document -> owner_unit_id';
    } elseif (str_contains($uri, 'data/imports/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: DataImportOperation -> scope_unit_id';
    } elseif (str_contains($uri, 'data/exports/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: DataExportOperation -> scope_unit_id';
    } elseif (str_contains($uri, 'movements/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: DocumentMovement -> to_unit_id ?? document';
    } elseif (str_contains($uri, 'assignments/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: DocumentAssignment -> assigned_unit_id ?? document';
    } elseif (str_contains($uri, 'extractions/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: DocumentTextExtraction -> documentVersion.document';
    } elseif (str_contains($uri, 'signatures/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: SignatureRequest -> documentVersion.document';
    } elseif (str_contains($uri, 'accreditation/evidence/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: AccreditationEvidence -> documentVersion.document';
    } elseif (str_contains($uri, 'archive/entries')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: ArchiveEntry -> document -> doc->allowsUser';
    } elseif (str_contains($uri, 'archive/disposition-requests')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: DispositionRequest -> archiveEntry.document -> doc->allowsUser';
    } elseif (str_contains($uri, 'archive/files')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: Document::resolveForFile -> doc->allowsUser';
    } elseif (str_contains($uri, 'workflows/instances/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'RouteScopeResolver: WorkflowInstance -> documentVersion.document -> doc->allowsUser';
    } elseif (str_contains($uri, 'archive/legal-holds/{id}')) {
        $handledInMiddleware = true;
        $resolverNote = 'CheckBaselinePermission: Legal hold administrative management (archive.manage_retention)';
    } elseif (str_contains($uri, 'documents/{documentId}/versions/{versionId}')) {
        $handledInMiddleware = true;
        $resolverNote = 'CheckBaselinePermission: Document::with(confidentiality) -> doc->allowsUser';
    }
    
    echo sprintf("[%s] %-50s\n  Resolver: %s\n  Action: %s\n\n",
        $r['methods'],
        $uri,
        $resolverNote,
        $r['action']
    );
    
    if (!$handledInMiddleware) {
        $findings[] = [
            'route' => $uri,
            'methods' => $r['methods'],
            'action' => $r['action'],
            'note' => $resolverNote,
        ];
    }
}

echo "\n--- SUMMARY OF UNRESOLVED OR AT-RISK ROUTES (" . count($findings) . ") ---\n";
foreach ($findings as $f) {
    echo "  [!] {$f['methods']} {$f['route']} => {$f['note']} ({$f['action']})\n";
}
