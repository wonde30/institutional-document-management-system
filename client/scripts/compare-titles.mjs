import fs from 'fs';
import { execSync } from 'child_process';

const files = [
  { path: 'client/src/views/dashboard/DashboardView.vue', name: 'DashboardView' },
  { path: 'client/src/views/documents/DocumentListView.vue', name: 'DocumentListView' },
  { path: 'client/src/views/documents/DocumentCreateView.vue', name: 'DocumentCreateView' },
  { path: 'client/src/views/templates/TemplateBuilderView.vue', name: 'TemplateBuilderView' },
  { path: 'client/src/views/notifications/NotificationsView.vue', name: 'NotificationsView' },
  { path: 'client/src/views/account/AccountProfileView.vue', name: 'AccountProfileView' },
  { path: 'client/src/views/research/FinalizedResearchView.vue', name: 'FinalizedResearchView' },
  { path: 'client/src/views/staff/StaffRecordsView.vue', name: 'StaffRecordsView' },
  { path: 'client/src/views/archive/ArchiveListView.vue', name: 'ArchiveListView' },
  { path: 'client/src/views/accreditation/AccreditationEvidenceView.vue', name: 'AccreditationEvidenceView' },
  { path: 'client/src/views/projects/ProjectsInnovationView.vue', name: 'ProjectsInnovationView' },
  { path: 'client/src/views/signatures/SignaturesStampsView.vue', name: 'SignaturesStampsView' },
  { path: 'client/src/views/reports/ReportsView.vue', name: 'ReportsView' },
  { path: 'client/src/views/organization/OrganizationUnitsView.vue', name: 'OrganizationUnitsView' },
];

console.log('View | Baseline h1 | Current title | Baseline subtitle | Current subtitle | Match');
console.log('-----|-------------|---------------|-------------------|------------------|------');

files.forEach(({ path, name }) => {
  try {
    // Get baseline
    const baselineContent = execSync(`git show 7d086b3:${path}`, {
      cwd: 'c:/Users/IT3/Desktop/DMS/client',
      encoding: 'utf8'
    });
    const baselineH1 = baselineContent.match(/<h1[^>]*>\s*(?:<[^>]+>\s*)*([^<]+)/)?.[1]?.trim() || 'N/A';
    const baselineSubtitle = baselineContent.match(/<p[^>]*class="[^"]*text-(?:xs|sm)[^"]*"[^>]*>\s*([^<]+)/)?.[1]?.trim().substring(0, 50) || 'N/A';

    // Get current
    const currentContent = fs.readFileSync(`c:/Users/IT3/Desktop/DMS/${path}`, 'utf8');
    const currentTitle = currentContent.match(/<PageHeader\s+title="([^"]+)"/)?.[1] || 'NO PAGEHEADER';
    const currentSubtitle = currentContent.match(/subtitle="([^"]+)"/)?.[1]?.substring(0, 50) || 'N/A';

    const titleMatch = baselineH1 === currentTitle ? '✓' : '✗';
    const subtitleMatch = baselineSubtitle === currentSubtitle ? '✓' : '✗';
    const match = titleMatch === '✓' && subtitleMatch === '✓' ? '✓✓' : titleMatch + subtitleMatch;

    console.log(`${name} | ${baselineH1.substring(0, 30)} | ${currentTitle.substring(0, 30)} | ${baselineSubtitle.substring(0, 30)} | ${currentSubtitle.substring(0, 30)} | ${match}`);
  } catch (err) {
    console.log(`${name} | ERROR | ERROR | ERROR | ERROR | ✗`);
  }
});
