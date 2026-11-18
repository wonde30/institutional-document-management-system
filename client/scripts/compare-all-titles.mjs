import fs from 'fs';
import { execSync } from 'child_process';

const views = [
  'src/views/dashboard/DashboardView.vue',
  'src/views/users/UserManagementView.vue',
  'src/views/rbac/RoleManagementView.vue',
  'src/views/admin/DataImportsExportsView.vue',
  'src/views/documents/DocumentListView.vue',
  'src/views/documents/DocumentCreateView.vue',
  'src/views/templates/TemplateBuilderView.vue',
  'src/views/workflows/WorkflowTasksView.vue',
  'src/views/notifications/NotificationsView.vue',
  'src/views/account/AccountProfileView.vue',
  'src/views/research/FinalizedResearchView.vue',
  'src/views/staff/StaffRecordsView.vue',
  'src/views/archive/ArchiveListView.vue',
  'src/views/accreditation/AccreditationEvidenceView.vue',
  'src/views/projects/ProjectsInnovationView.vue',
  'src/views/signatures/SignaturesStampsView.vue',
  'src/views/reports/ReportsView.vue',
  'src/views/organization/OrganizationUnitsView.vue'
];

console.log('view | baseline h1 (7d086b3) | current title | baseline subtitle | current subtitle');
console.log('-----|---------------------|---------------|-------------------|------------------');

views.forEach(file => {
  const viewName = file.split('/').pop().replace('.vue', '');
  
  // Get baseline
  let baselineH1 = '';
  let baselineSubtitle = '';
  try {
    const baseline = execSync(`git show 7d086b3:client/${file}`, { encoding: 'utf8' });
    const h1Match = baseline.match(/<h1[^>]*>([^<]+)<\/h1>/);
    baselineH1 = h1Match ? h1Match[1].trim() : 'NO_H1';
    
    // Try to find subtitle
    const lines = baseline.split('\n');
    for (let i = 0; i < lines.length; i++) {
      if (lines[i].includes('<h1')) {
        // Look for subtitle in next 5 lines
        for (let j = i + 1; j < Math.min(i + 6, lines.length); j++) {
          const subtitleMatch = lines[j].match(/<p[^>]*class="[^"]*text-(?:xs|sm)[^"]*text-ink-muted[^"]*"[^>]*>([^<]+)<\/p>/);
          if (subtitleMatch) {
            baselineSubtitle = subtitleMatch[1].trim();
            break;
          }
        }
        break;
      }
    }
  } catch (e) {
    baselineH1 = 'NOT_IN_BASELINE';
  }
  
  // Get current
  let currentTitle = '';
  let currentSubtitle = '';
  try {
    const current = fs.readFileSync('client/' + file, 'utf8');
    const titleMatch = current.match(/<PageHeader[^>]*title="([^"]+)"/);
    currentTitle = titleMatch ? titleMatch[1].trim() : 'NO_PAGEHEADER';
    
    const subtitleMatch = current.match(/<PageHeader[^>]*subtitle="([^"]+)"/);
    currentSubtitle = subtitleMatch ? subtitleMatch[1].trim() : '';
  } catch (e) {
    currentTitle = 'FILE_NOT_FOUND';
  }
  
  const mismatch = (baselineH1 !== 'NOT_IN_BASELINE' && baselineH1 !== 'NO_H1' && currentTitle !== 'NO_PAGEHEADER' && baselineH1 !== currentTitle && viewName !== 'DashboardView') ? ' ❌' : '';
  
  console.log(`${viewName} | ${baselineH1} | ${currentTitle} | ${baselineSubtitle} | ${currentSubtitle}${mismatch}`);
});
