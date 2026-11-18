import { execSync } from 'child_process';
import fs from 'fs';

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
  'src/views/organization/OrganizationUnitsView.vue',
  'src/views/documents/DocumentDetailView.vue',
  'src/views/search/GlobalSearchView.vue'
];

function extractBaselineText(file) {
  try {
    const content = execSync(`git show 7d086b3:client/${file}`, { encoding: 'utf8' });
    const lines = content.split('\n');
    
    let h1Text = '';
    let subtitleText = '';
    let foundH1 = false;
    
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      
      // Look for h1
      if (!foundH1 && line.includes('<h1')) {
        foundH1 = true;
        // Extract text, handling multi-line and icons
        let h1Content = '';
        let j = i;
        let collecting = true;
        
        while (collecting && j < lines.length) {
          const currentLine = lines[j];
          h1Content += currentLine + '\n';
          
          if (currentLine.includes('</h1>')) {
            collecting = false;
          }
          j++;
        }
        
        // Extract text from <span> if present, otherwise from h1 directly
        let textContent = h1Content;
        const spanMatch = h1Content.match(/<span[^>]*>([^<]+)<\/span>/);
        if (spanMatch) {
          textContent = spanMatch[1];
        } else {
          // Strip all tags and {{ }} expressions
          textContent = h1Content
            .replace(/<[^>]+>/g, ' ')
            .replace(/\{\{[^}]+\}\}/g, '')
            .replace(/\s+/g, ' ')
            .trim();
        }
        
        h1Text = textContent.trim();
        
        // Look for subtitle in next few lines (text-xs or text-sm with text-ink-muted)
        for (let k = j; k < Math.min(j + 10, lines.length); k++) {
          if ((lines[k].includes('text-xs') || lines[k].includes('text-sm')) && lines[k].includes('text-ink-muted')) {
            let subtitleContent = '';
            let m = k;
            let subtitleCollecting = true;
            
            while (subtitleCollecting && m < lines.length) {
              const subLine = lines[m];
              subtitleContent += subLine + ' ';
              
              if (subLine.includes('</p>')) {
                subtitleCollecting = false;
              }
              m++;
            }
            
            subtitleText = subtitleContent
              .replace(/<[^>]+>/g, ' ')
              .replace(/\s+/g, ' ')
              .trim();
            break;
          }
        }
        
        break;
      }
    }
    
    return { h1: h1Text || 'NO_H1', subtitle: subtitleText || '' };
  } catch (e) {
    return { h1: 'FILE_NOT_IN_BASELINE', subtitle: '' };
  }
}

function extractCurrentText(file) {
  try {
    const content = fs.readFileSync(`client/${file}`, 'utf8');
    
    // Extract title prop
    const titleMatch = content.match(/<PageHeader[^>]*title="([^"]+)"/);
    const title = titleMatch ? titleMatch[1] : 'NO_PAGEHEADER';
    
    // Extract subtitle prop
    const subtitleMatch = content.match(/<PageHeader[^>]*subtitle="([^"]+)"/);
    const subtitle = subtitleMatch ? subtitleMatch[1] : '';
    
    return { title, subtitle };
  } catch (e) {
    return { title: 'FILE_NOT_FOUND', subtitle: '' };
  }
}

console.log('view | baseline h1 | current title | baseline subtitle | current subtitle');
console.log('-----|-------------|---------------|-------------------|------------------');

views.forEach(file => {
  const viewName = file.split('/').pop().replace('.vue', '');
  const baseline = extractBaselineText(file);
  const current = extractCurrentText(file);
  
  // Truncate for display
  const h1Display = baseline.h1.substring(0, 60);
  const titleDisplay = current.title.substring(0, 60);
  const subBaseDisplay = baseline.subtitle.substring(0, 80);
  const subCurrDisplay = current.subtitle.substring(0, 80);
  
  const mismatch = (
    baseline.h1 !== 'FILE_NOT_IN_BASELINE' && 
    baseline.h1 !== 'NO_H1' &&
    current.title !== 'NO_PAGEHEADER' &&
    baseline.h1 !== current.title &&
    viewName !== 'DashboardView' // approved exception
  ) ? ' ❌' : '';
  
  console.log(`${viewName} | ${h1Display} | ${titleDisplay} | ${subBaseDisplay} | ${subCurrDisplay}${mismatch}`);
});
