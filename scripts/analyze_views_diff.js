const { execSync } = require('child_process');
const fs = require('fs');

// List of all 38 unique view files imported in the router
const viewFiles = [
  'account/AccountProfileView.vue',
  'accreditation/AccreditationEvidenceView.vue',
  'admin/DataImportsExportsView.vue',
  'admin/InstitutionalConfigurationCenterView.vue',
  'admin/ReferenceDataView.vue',
  'admin/SystemSettingsView.vue',
  'archive/ArchiveListView.vue',
  'audit/AuditLogView.vue',
  'auth/ForgotPasswordView.vue',
  'auth/LoginView.vue',
  'auth/ResetPasswordView.vue',
  'auth/VerifyEmailView.vue',
  'correspondence/CorrespondenceRegistryView.vue',
  'dashboard/DashboardView.vue',
  'documents/DocumentCreateView.vue',
  'documents/DocumentDetailView.vue',
  'documents/DocumentListView.vue',
  'errors/ForbiddenView.vue',
  'errors/NotFoundView.vue',
  'help/HelpGuideView.vue',
  'notifications/NotificationsView.vue',
  'operations/OperationsView.vue',
  'organization/OrganizationUnitsView.vue',
  'procedures/OfficeProceduresView.vue',
  'projects/ProjectsInnovationView.vue',
  'public/LandingPageView.vue',
  'public/PrivacyPolicyView.vue',
  'public/TermsConditionsView.vue',
  'rbac/PermissionManagementView.vue',
  'rbac/RoleManagementView.vue',
  'reports/ReportsView.vue',
  'research/FinalizedResearchView.vue',
  'search/GlobalSearchView.vue',
  'signatures/SignaturesStampsView.vue',
  'staff/StaffRecordsView.vue',
  'templates/TemplateBuilderView.vue',
  'users/UserManagementView.vue',
  'workflows/WorkflowTasksView.vue'
];

console.log('Total unique view files to check:', viewFiles.length);

const results = [];

viewFiles.forEach(file => {
  const fullPath = 'client/src/views/' + file;
  let status = 'UNTRACKED';
  let diffStat = '';
  
  try {
    const isUntracked = !execSync(`git ls-files ${fullPath}`, { encoding: 'utf8' }).trim();
    if (isUntracked) {
      status = 'NEW_UNTRACKED';
      const lines = fs.readFileSync(fullPath, 'utf8').split('\n').length;
      diffStat = `New file (${lines} lines)`;
    } else {
      const diff = execSync(`git diff HEAD -- ${fullPath}`, { encoding: 'utf8' });
      if (!diff.trim()) {
        status = 'NO_CHANGES';
        diffStat = 'Identical to HEAD';
      } else {
        status = 'MODIFIED';
        const numstat = execSync(`git diff --numstat HEAD -- ${fullPath}`, { encoding: 'utf8' }).trim();
        diffStat = numstat;
      }
    }
  } catch (e) {
    status = 'ERROR: ' + e.message;
  }
  
  results.push({ file, status, diffStat });
});

console.log('\n--- DETAILED VIEW AUDIT ---');
results.forEach(r => {
  console.log(`[${r.status.padEnd(14)}] ${r.file.padEnd(46)} | ${r.diffStat}`);
});

const modifiedCount = results.filter(r => r.status === 'MODIFIED' || r.status === 'NEW_UNTRACKED').length;
const untouchedCount = results.filter(r => r.status === 'NO_CHANGES').length;
console.log('\nModified/New views:', modifiedCount);
console.log('Untouched views:', untouchedCount);
