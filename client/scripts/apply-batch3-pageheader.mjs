import fs from 'fs';
import path from 'path';

const batch3Files = [
  {
    file: 'client/src/views/research/FinalizedResearchView.vue',
    title: 'Finalized Faculty Research Repository',
    subtitle: 'Departmental publication of research completed and selected through external university processes.',
    icon: 'BookOpen',
    actionButtons: `<BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>

      <BaseButton
        v-if="authStore.hasPermission('research.create') || authStore.hasPermission('documents.create')"
        variant="primary"
        size="sm"
        @click="showModal = true"
      >
        <Plus class="w-4 h-4 mr-1.5" />
        Register Finalized Research
      </BaseButton>`,
  },
  {
    file: 'client/src/views/staff/StaffRecordsView.vue',
    title: 'Staff Professional Development Records',
    subtitle: 'Approved proposals and finalized trainings for institutional staff capacity development.',
    icon: 'Users',
    actionButtons: `<BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>
      <BaseButton
        v-if="authStore.hasPermission('staff.create') || authStore.hasPermission('documents.create')"
        variant="primary"
        size="sm"
        @click="showModal = true"
      >
        <Plus class="w-4 h-4 mr-1.5" />
        Register Staff Training
      </BaseButton>`,
  },
  {
    file: 'client/src/views/archive/ArchiveListView.vue',
    title: 'Institutional Physical Archive Registry',
    subtitle: 'Browse hard-copy file locations, boxes, shelves, and active/closed collection records.',
    icon: 'Archive',
    actionButtons: `<BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>
      <BaseButton
        v-if="authStore.hasPermission('archive.create')"
        variant="primary"
        size="sm"
        @click="showModal = true"
      >
        <Plus class="w-4 h-4 mr-1.5" />
        Register Archive Collection
      </BaseButton>`,
  },
  {
    file: 'client/src/views/accreditation/AccreditationEvidenceView.vue',
    title: 'Accreditation Evidence Repository',
    subtitle: 'Quality assurance documentation and accreditation criterion evidence for institutional compliance.',
    icon: 'Award',
    actionButtons: `<BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>
      <BaseButton
        v-if="authStore.hasPermission('accreditation.create') || authStore.hasPermission('documents.create')"
        variant="primary"
        size="sm"
        @click="showModal = true"
      >
        <Plus class="w-4 h-4 mr-1.5" />
        Link Evidence Document
      </BaseButton>`,
  },
  {
    file: 'client/src/views/innovation/ProjectsInnovationView.vue',
    title: 'Community Engagement & Innovation Projects',
    subtitle: 'University outreach projects, community partnerships, and innovation initiatives.',
    icon: 'Lightbulb',
    actionButtons: `<BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>
      <BaseButton
        v-if="authStore.hasPermission('projects.create') || authStore.hasPermission('documents.create')"
        variant="primary"
        size="sm"
        @click="showModal = true"
      >
        <Plus class="w-4 h-4 mr-1.5" />
        Register Innovation Project
      </BaseButton>`,
  },
  {
    file: 'client/src/views/signatures/SignaturesStampsView.vue',
    title: 'Digital Signature & Official Stamp Registry',
    subtitle: 'Manage institutional authority signatures, digital seal images, and approval delegation records.',
    icon: 'Stamp',
    actionButtons: `<BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>
      <BaseButton
        v-if="authStore.hasPermission('signatures.create')"
        variant="primary"
        size="sm"
        @click="showModal = true"
      >
        <Plus class="w-4 h-4 mr-1.5" />
        Register Signature/Stamp
      </BaseButton>`,
  },
  {
    file: 'client/src/views/reports/ReportsView.vue',
    title: 'Analytical Reports & Insights',
    subtitle: 'Pre-configured analytics dashboards for document activity, compliance, and system usage patterns.',
    icon: 'BarChart3',
    actionButtons: `<BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>`,
  },
  {
    file: 'client/src/views/organization/OrganizationUnitsView.vue',
    title: 'Institutional Organization Structure',
    subtitle: 'Hierarchical structure of colleges, directorates, departments, and academic units across Wollo University campuses.',
    icon: 'Building2',
    actionButtons: `<BaseButton variant="outline" size="sm" :loading="isLoading" @click="loadData">
        <RefreshCw class="w-4 h-4 mr-1.5" />
        Refresh
      </BaseButton>
      <BaseButton variant="primary" size="sm" @click="openCreateModal(null)">
        <Plus class="w-4 h-4 mr-1.5" />
        Add Top-Level Unit
      </BaseButton>`,
  },
];

const baseDir = 'c:/Users/IT3/Desktop/DMS';

batch3Files.forEach(({ file, title, subtitle, icon, actionButtons }) => {
  const filePath = path.join(baseDir, file);
  
  if (!fs.existsSync(filePath)) {
    console.log(`✗ File not found: ${file}`);
    return;
  }

  let content = fs.readFileSync(filePath, 'utf8');

  // Check if PageHeader already imported
  if (content.includes('PageHeader.vue')) {
    console.log(`○ ${file} - already has PageHeader`);
    return;
  }

  // Step 1: Add PageHeader import after other component imports
  const importPattern = /(import.*?from '@\/components\/ui\/\w+\.vue'\n)/;
  if (importPattern.test(content)) {
    content = content.replace(
      importPattern,
      `$1import PageHeader from '@/components/PageHeader.vue'\n`
    );
  }

  // Step 2: Replace inline header with PageHeader
  const headerPattern = new RegExp(
    `<div class="flex flex-col sm:flex-row sm:items-(?:start|center) sm:justify-between gap-4 border-b border-hairline pb-4">\\s*<div>\\s*<h1 class="[^"]*">.*?${icon}.*?${title.replace(/[()]/g, '\\$&')}.*?</h1>\\s*<p class="text-xs text-ink-muted[^"]*">\\s*${subtitle.replace(/[(),.]/g, '\\$&')}.*?</p>\\s*</div>\\s*<div class="flex items-center gap-2\\.5?">\\s*${actionButtons.replace(/[()[\]{}.*+?^$|\\]/g, '\\$&')}\\s*</div>\\s*</div>`,
    's'
  );

  // Simpler approach: find and replace the header section
  const simpleHeaderStart = /<div class="flex flex-col sm:flex-row sm:items-(?:start|center) sm:justify-between gap-4 border-b border-hairline pb-4">/;
  const simpleHeaderEnd = /<\/div>\s*<\/div>\s*<!-- /;

  if (simpleHeaderStart.test(content)) {
    // Find the full header block
    const startIndex = content.search(simpleHeaderStart);
    const afterStart = content.substring(startIndex);
    
    // Find where header block ends (before next comment or section)
    const endMatch = afterStart.match(/<\/div>\s*<\/div>\s*\n\s*<!--/);
    
    if (endMatch) {
      const endIndex = startIndex + endMatch.index + endMatch[0].length - 5; // back to before comment
      const headerBlock = content.substring(startIndex, endIndex);
      
      // Replace with PageHeader
      const newHeader = `    <PageHeader title="${title}">
      <template #subtitle>${subtitle}</template>
      <template #actions>
        ${actionButtons}
      </template>
    </PageHeader>`;
      
      content = content.substring(0, startIndex) + newHeader + content.substring(endIndex);
      
      fs.writeFileSync(filePath, content, 'utf8');
      console.log(`✓ ${file} - PageHeader applied`);
    } else {
      console.log(`○ ${file} - could not locate header end`);
    }
  } else {
    console.log(`○ ${file} - no matching header pattern`);
  }
});

console.log('\n✓ Batch 3 PageHeader application complete');
