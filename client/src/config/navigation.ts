import {
  LayoutDashboard,
  ListChecks,
  Bell,
  Files,
  Inbox,
  Send,
  FileText,
  GitFork,
  BookOpen,
  Users,
  Archive,
  ClipboardCheck,
  FolderKanban,
  PenLine,
  ChartNoAxesCombined,
  ArrowUpDown,
  Building2,
  ShieldCheck,
  GitBranch,
  Database,
  Eye,
  Activity,
  Settings,
  Sliders,
  HelpCircle,
  type LucideIcon,
} from 'lucide-vue-next'

export interface NavigationItem {
  name: string
  to: string
  icon: LucideIcon | string
  permission: string | string[] | null
  badgeKey?: string
}

export interface NavigationSection {
  id?: string
  title: string
  icon?: LucideIcon | string
  items: NavigationItem[]
}

export const navIconMap: Record<string, LucideIcon> = {
  LayoutDashboard,
  ListChecks,
  Bell,
  Files,
  Inbox,
  Send,
  FileText,
  GitFork,
  BookOpen,
  Users,
  Archive,
  ClipboardCheck,
  FolderKanban,
  PenLine,
  ChartNoAxesCombined,
  ArrowUpDown,
  Building2,
  ShieldCheck,
  GitBranch,
  Database,
  Eye,
  Activity,
  Settings,
  Sliders,
  HelpCircle,
}

export function resolveNavIcon(icon: string | LucideIcon | undefined): LucideIcon {
  if (!icon) return LayoutDashboard
  if (typeof icon === 'string') {
    return navIconMap[icon] || LayoutDashboard
  }
  return icon
}

export const navigationConfig: NavigationSection[] = [
  {
    id: 'workspace',
    title: 'Workspace',
    icon: LayoutDashboard,
    items: [
      {
        name: 'Dashboard',
        to: '/dashboard',
        icon: LayoutDashboard,
        permission: null,
      },
      {
        name: 'My tasks',
        to: '/tasks',
        icon: ListChecks,
        permission: 'workflow.view',
      },
      {
        name: 'Notifications',
        to: '/notifications',
        icon: Bell,
        permission: null,
      },
    ],
  },
  {
    id: 'records',
    title: 'Records',
    icon: Files,
    items: [
      {
        name: 'Documents',
        to: '/documents',
        icon: Files,
        permission: 'documents.view',
      },
      {
        name: 'Templates',
        to: '/templates',
        icon: FileText,
        permission: 'documents.create',
      },
      {
        name: 'Incoming letters',
        to: '/letters/incoming',
        icon: Inbox,
        permission: 'correspondence.view',
      },
      {
        name: 'Outgoing letters',
        to: '/letters/outgoing',
        icon: Send,
        permission: 'correspondence.view',
      },
      {
        name: 'Internal memoranda',
        to: '/memoranda',
        icon: FileText,
        permission: 'correspondence.view',
      },
      {
        name: 'Office routing',
        to: '/routing',
        icon: GitFork,
        permission: 'correspondence.view',
      },
    ],
  },
  {
    id: 'institutional',
    title: 'Institutional records',
    icon: Building2,
    items: [
      {
        name: 'Finalized research',
        to: '/research',
        icon: BookOpen,
        permission: 'research.view',
      },
      {
        name: 'Staff records',
        to: '/staff',
        icon: Users,
        permission: 'staff.view',
      },
      {
        name: 'Digital archive',
        to: '/archive',
        icon: Archive,
        permission: 'archive.view',
      },
      {
        name: 'Accreditation evidence',
        to: '/accreditation',
        icon: ClipboardCheck,
        permission: 'accreditation.view',
      },
      {
        name: 'Projects & innovation',
        to: '/projects',
        icon: FolderKanban,
        permission: 'projects.view',
      },
    ],
  },
  {
    id: 'trust',
    title: 'Trust & reporting',
    icon: ShieldCheck,
    items: [
      {
        name: 'Signatures & stamps',
        to: '/signatures',
        icon: PenLine,
        permission: 'signatures.request',
      },
      {
        name: 'Reports',
        to: '/reports',
        icon: ChartNoAxesCombined,
        permission: 'reports.view',
      },
      {
        name: 'Imports & exports',
        to: '/exports',
        icon: ArrowUpDown,
        permission: ['data.import', 'data.export'],
      },
    ],
  },
  {
    id: 'admin',
    title: 'Administration',
    icon: Settings,
    items: [
      {
        name: 'Users',
        to: '/admin/users',
        icon: Users,
        permission: 'users.view',
      },
      {
        name: 'Roles & permissions',
        to: '/admin/roles',
        icon: ShieldCheck,
        permission: 'roles.view',
      },
      {
        name: 'Organization',
        to: '/admin/organization',
        icon: Building2,
        permission: 'organization.view',
      },
      {
        name: 'Configuration Center',
        to: '/admin/configuration',
        icon: Sliders,
        permission: 'system.configure',
      },
      {
        name: 'Workflows & office procedures',
        to: '/admin/office-procedures',
        icon: GitBranch,
        permission: 'roles.view',
      },
      {
        name: 'Reference data',
        to: '/admin/reference-data',
        icon: Database,
        permission: 'roles.view',
      },
      {
        name: 'Audit log',
        to: '/admin/audit',
        icon: Eye,
        permission: 'audit.view',
      },
      {
        name: 'Operations',
        to: '/admin/operations',
        icon: Activity,
        permission: 'system.configure',
      },
      {
        name: 'Settings',
        to: '/admin/settings',
        icon: Settings,
        permission: 'system.configure',
      },
      {
        name: 'Help & guide',
        to: '/help',
        icon: HelpCircle,
        permission: null,
      },
    ],
  },
]
