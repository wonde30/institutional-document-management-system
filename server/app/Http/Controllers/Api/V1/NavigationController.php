<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Authorization\OrganizationalContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    /**
     * Return dynamic, permission-filtered navigation catalog for the authenticated user.
     * Sysadmin grants/revocations dynamically reconfigure the returned menu bars.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // The catalog is filtered with the capability set calculated for the
        // active organizational context, so the menu never advertises an
        // action the server would refuse in the current context.
        $userPerms = app(OrganizationalContextService::class)
            ->capabilitiesForActiveContext($user, $request);
        $isSuperAdmin = $user->hasPermission('system.configure');

        $sections = $this->getMasterNavigationCatalog();

        $filteredSections = [];
        foreach ($sections as $section) {
            $filteredItems = [];
            foreach ($section['items'] as $item) {
                // If no permission is required, item is accessible to all authenticated accounts
                if ($item['permission'] === null) {
                    $filteredItems[] = $item;
                    continue;
                }

                // Superadmins bypass item permission restrictions
                if ($isSuperAdmin) {
                    $filteredItems[] = $item;
                    continue;
                }

                // Check if user holds any of the required permissions (single string or array)
                $required = (array) $item['permission'];
                $hasPerm = false;
                foreach ($required as $permCode) {
                    if (in_array($permCode, $userPerms, true)) {
                        $hasPerm = true;
                        break;
                    }
                }

                if ($hasPerm) {
                    $filteredItems[] = $item;
                }
            }

            // Only include module sections that contain at least one accessible navigation item
            if (! empty($filteredItems)) {
                $section['items'] = $filteredItems;
                $filteredSections[] = $section;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $filteredSections,
            'meta' => [
                'user_id' => $user->id,
                'is_super_admin' => $isSuperAdmin,
                'permissions_count' => count($userPerms),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Master schema of all institutional navigation sections and menu items.
     * Fully normalized and structured for dynamic rendering.
     *
     * @return list<array<string, mixed>>
     */
    private function getMasterNavigationCatalog(): array
    {
        return [
            [
                'id' => 'workspace',
                'title' => 'Workspace',
                'icon' => 'LayoutDashboard',
                'items' => [
                    [
                        'name' => 'Dashboard',
                        'to' => '/dashboard',
                        'icon' => 'LayoutDashboard',
                        'permission' => null,
                    ],
                    [
                        'name' => 'My tasks',
                        'to' => '/tasks',
                        'icon' => 'ListChecks',
                        'permission' => 'workflow.view',
                    ],
                    [
                        'name' => 'Notifications',
                        'to' => '/notifications',
                        'icon' => 'Bell',
                        'permission' => null,
                    ],
                ],
            ],
            [
                'id' => 'records',
                'title' => 'Records',
                'icon' => 'Files',
                'items' => [
                    [
                        'name' => 'Documents',
                        'to' => '/documents',
                        'icon' => 'Files',
                        'permission' => 'documents.view',
                    ],
                    [
                        'name' => 'Templates',
                        'to' => '/templates',
                        'icon' => 'FileText',
                        'permission' => 'documents.create',
                    ],
                    [
                        'name' => 'Incoming letters',
                        'to' => '/letters/incoming',
                        'icon' => 'Inbox',
                        'permission' => 'correspondence.view',
                    ],
                    [
                        'name' => 'Outgoing letters',
                        'to' => '/letters/outgoing',
                        'icon' => 'Send',
                        'permission' => 'correspondence.view',
                    ],
                    [
                        'name' => 'Internal memoranda',
                        'to' => '/memoranda',
                        'icon' => 'FileText',
                        'permission' => 'correspondence.view',
                    ],
                    [
                        'name' => 'Office routing',
                        'to' => '/routing',
                        'icon' => 'GitFork',
                        'permission' => 'correspondence.view',
                    ],
                ],
            ],
            [
                'id' => 'institutional',
                'title' => 'Institutional records',
                'icon' => 'Building2',
                'items' => [
                    [
                        'name' => 'Finalized research',
                        'to' => '/research',
                        'icon' => 'BookOpen',
                        'permission' => 'research.view',
                    ],
                    [
                        'name' => 'Staff records',
                        'to' => '/staff',
                        'icon' => 'Users',
                        'permission' => 'staff.view',
                    ],
                    [
                        'name' => 'Digital archive',
                        'to' => '/archive',
                        'icon' => 'Archive',
                        'permission' => 'archive.view',
                    ],
                    [
                        'name' => 'Accreditation evidence',
                        'to' => '/accreditation',
                        'icon' => 'ClipboardCheck',
                        'permission' => 'accreditation.view',
                    ],
                    [
                        'name' => 'Projects & innovation',
                        'to' => '/projects',
                        'icon' => 'FolderKanban',
                        'permission' => 'projects.view',
                    ],
                ],
            ],
            [
                'id' => 'trust',
                'title' => 'Trust & reporting',
                'icon' => 'ShieldCheck',
                'items' => [
                    [
                        'name' => 'Signatures & stamps',
                        'to' => '/signatures',
                        'icon' => 'PenLine',
                        'permission' => 'signatures.request',
                    ],
                    [
                        'name' => 'Reports',
                        'to' => '/reports',
                        'icon' => 'ChartNoAxesCombined',
                        'permission' => 'reports.view',
                    ],
                    [
                        'name' => 'Imports & exports',
                        'to' => '/exports',
                        'icon' => 'ArrowUpDown',
                        'permission' => ['data.import', 'data.export'],
                    ],
                ],
            ],
            [
                'id' => 'admin',
                'title' => 'Administration',
                'icon' => 'Settings',
                'items' => [
                    [
                        'name' => 'Users',
                        'to' => '/admin/users',
                        'icon' => 'Users',
                        'permission' => 'users.view',
                    ],
                    [
                        'name' => 'Roles & permissions',
                        'to' => '/admin/roles',
                        'icon' => 'ShieldCheck',
                        'permission' => 'roles.view',
                    ],
                    [
                        'name' => 'Organization',
                        'to' => '/admin/organization',
                        'icon' => 'Building2',
                        'permission' => 'organization.view',
                    ],
                    [
                        'name' => 'Configuration Center',
                        'to' => '/admin/configuration',
                        'icon' => 'Sliders',
                        'permission' => 'system.configure',
                    ],
                    [
                        'name' => 'Workflows & office procedures',
                        'to' => '/admin/office-procedures',
                        'icon' => 'GitBranch',
                        'permission' => 'roles.view',
                    ],
                    [
                        'name' => 'Reference data',
                        'to' => '/admin/reference-data',
                        'icon' => 'Database',
                        'permission' => 'roles.view',
                    ],
                    [
                        'name' => 'Audit log',
                        'to' => '/admin/audit',
                        'icon' => 'Eye',
                        'permission' => 'audit.view',
                    ],
                    [
                        'name' => 'Operations',
                        'to' => '/admin/operations',
                        'icon' => 'Activity',
                        'permission' => 'system.configure',
                    ],
                    [
                        'name' => 'Settings',
                        'to' => '/admin/settings',
                        'icon' => 'Settings',
                        'permission' => 'system.configure',
                    ],
                    [
                        'name' => 'Help & guide',
                        'to' => '/help',
                        'icon' => 'HelpCircle',
                        'permission' => null,
                    ],
                ],
            ],
        ];
    }
}
