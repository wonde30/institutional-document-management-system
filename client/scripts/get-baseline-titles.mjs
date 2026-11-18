import { execSync } from 'child_process';

const files = [
  'client/src/views/dashboard/DashboardView.vue',
  'client/src/views/users/UserManagementView.vue',
  'client/src/views/roles/RoleManagementView.vue',
  'client/src/views/exports/DataImportsExportsView.vue',
  'client/src/views/documents/DocumentListView.vue',
  'client/src/views/documents/DocumentCreateView.vue',
  'client/src/views/templates/TemplateBuilderView.vue',
  'client/src/views/tasks/WorkflowTasksView.vue',
  'client/src/views/notifications/NotificationsView.vue',
  'client/src/views/account/AccountProfileView.vue',
  'client/src/views/research/FinalizedResearchView.vue',
  'client/src/views/staff/StaffRecordsView.vue',
  'client/src/views/archive/ArchiveListView.vue',
  'client/src/views/accreditation/AccreditationEvidenceView.vue',
  'client/src/views/projects/ProjectsInnovationView.vue',
  'client/src/views/signatures/SignaturesStampsView.vue',
  'client/src/views/reports/ReportsView.vue',
  'client/src/views/organization/OrganizationUnitsView.vue',
];

console.log('View | Baseline h1 | Baseline subtitle');
console.log('-----|-------------|------------------');

files.forEach((file) => {
  try {
    const content = execSync(`git show 7d086b3:${file}`, {
      cwd: 'c:/Users/IT3/Desktop/DMS/client',
      encoding: 'utf8',
      maxBuffer: 10 * 1024 * 1024
    });

    const h1Match = content.match(/<h1[^>]*>\s*(?:<[^>]+>\s*)*([^<]+)</);
    const subtitleMatch = content.match(/<p[^>]*class="[^"]*text-(?:xs|sm)[^"]*"[^>]*>\s*([^<]+)</);

    const viewName = file.split('/').pop().replace('.vue', '');
    const h1 = h1Match ? h1Match[1].trim() : 'N/A';
    const subtitle = subtitleMatch ? subtitleMatch[1].trim().substring(0, 80) : 'N/A';

    console.log(`${viewName} | ${h1} | ${subtitle}`);
  } catch (err) {
    const viewName = file.split('/').pop().replace('.vue', '');
    console.log(`${viewName} | ERROR | ERROR`);
  }
});
