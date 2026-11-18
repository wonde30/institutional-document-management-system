import { execSync } from 'child_process';
import fs from 'fs';

const fixes = [
  {
    file: 'src/views/users/UserManagementView.vue',
    title: 'User Accounts & Staff Directory',
    subtitle: 'Dynamic database-driven RBAC roster, scoped roles, single-user direct permission overrides, and lifecycle status'
  },
  {
    file: 'src/views/rbac/RoleManagementView.vue',
    title: 'Scoped Role Management',
    subtitle: 'Configure institutional roles, assignable capability bundles, and multi-unit authorization scopes without code changes'
  },
  {
    file: 'src/views/admin/DataImportsExportsView.vue',
    title: 'Data Imports & Structured Exports',
    subtitle: 'Run a validated batch import against an authorised organizational unit, or generate a structured export of the records you are permitted to reach.'
  },
  {
    file: 'src/views/documents/DocumentListView.vue',
    title: 'Document Repository',
    subtitle: 'Search, view, and manage institutional records with version tracking and scoped security classifications'
  },
  {
    file: 'src/views/documents/DocumentCreateView.vue',
    title: 'Register New Institutional Record',
    subtitle: 'Create authentic Wollo University letters matching institutional standards or register external document records.'
  },
  {
    file: 'src/views/templates/TemplateBuilderView.vue',
    title: 'Institutional Document Templates',
    subtitle: 'Standardized letterheads, memoranda, directives, and verified document templates.'
  },
  {
    file: 'src/views/workflows/WorkflowTasksView.vue',
    title: 'Workflow Tasks & Decision Approvals',
    subtitle: 'Review, approve, or return institutional documents and correspondence directives undergoing routing policy decisions.'
  },
  {
    file: 'src/views/notifications/NotificationsView.vue',
    title: 'Notifications & Institutional Activity',
    subtitle: 'Authorized event notifications, document dispatches, and pending workflow assignments.'
  },
  {
    file: 'src/views/account/AccountProfileView.vue',
    title: 'Account & Security Profile',
    subtitle: 'Manage your institutional identity, authentication credentials, and display preferences.'
  },
  {
    file: 'src/views/research/FinalizedResearchView.vue',
    title: 'Finalized Faculty Research Repository',
    subtitle: 'Departmental publication of research completed and selected through external university processes.'
  },
  {
    file: 'src/views/staff/StaffRecordsView.vue',
    title: 'Staff Records & Physical Original Custody',
    subtitle: 'Dual tracking of electronic staff dossiers alongside physical paper original locations for institutional compliance.'
  },
  {
    file: 'src/views/archive/ArchiveListView.vue',
    title: 'Digital Archive & Long-Term Preservation',
    subtitle: 'Statutory archival repository, retention schedules, legal holds, and historical preservation for auditable institutional memory.'
  },
  {
    file: 'src/views/accreditation/AccreditationEvidenceView.vue',
    title: 'Accreditation & Quality Assurance',
    subtitle: 'Map frozen document versions to official national HERQA quality standards with traceable audit reviews.'
  },
  {
    file: 'src/views/projects/ProjectsInnovationView.vue',
    title: 'Projects & Innovation',
    subtitle: 'Registered project proposals, innovation grants, and active institutional initiatives.'
  },
  {
    file: 'src/views/signatures/SignaturesStampsView.vue',
    title: 'Signatures & Stamps',
    subtitle: 'Cryptographic signing requests, authorized signers, and departmental office seal registries.'
  },
  {
    file: 'src/views/reports/ReportsView.vue',
    title: 'Executive Reports & Statistical Summaries',
    subtitle: 'Institutional analytics, operational correspondence audit, document lifecycle diagnostics, and management dashboards.'
  },
  {
    file: 'src/views/organization/OrganizationUnitsView.vue',
    title: 'Institutional Organization Structure',
    subtitle: 'Hierarchical structure of colleges, directorates, departments, and academic units across Wollo University campuses.'
  },
  {
    file: 'src/views/search/GlobalSearchView.vue',
    title: 'Repository & Registry Search Portal',
    subtitle: 'Query official documents, incoming/outgoing letters, and finalized research within your authorized scope.'
  }
];

console.log('Restoring baseline titles to all PageHeader views...\n');

let fixed = 0;
let errors = 0;

fixes.forEach(({ file, title, subtitle }) => {
  try {
    let content = fs.readFileSync(`client/${file}`, 'utf8');
    
    // Replace PageHeader title and subtitle
    const pageHeaderRegex = /<PageHeader[^>]*title="[^"]*"[^>]*subtitle="[^"]*"[^>]*>/;
    const match = content.match(pageHeaderRegex);
    
    if (match) {
      const newPageHeader = match[0]
        .replace(/title="[^"]*"/, `title="${title}"`)
        .replace(/subtitle="[^"]*"/, `subtitle="${subtitle}"`);
      
      content = content.replace(match[0], newPageHeader);
      fs.writeFileSync(`client/${file}`, content, 'utf8');
      console.log(`✓ Fixed: ${file.split('/').pop()}`);
      fixed++;
    } else {
      console.log(`⚠ No PageHeader found in ${file.split('/').pop()}`);
      errors++;
    }
  } catch (e) {
    console.log(`✗ Error fixing ${file.split('/').pop()}: ${e.message}`);
    errors++;
  }
});

console.log(`\nFixed: ${fixed}, Errors: ${errors}`);
process.exit(errors > 0 ? 1 : 0);
