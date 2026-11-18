import fs from 'fs';

// 1. AccreditationEvidenceView
const accred = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\accreditation\\AccreditationEvidenceView.vue';
let content1 = fs.readFileSync(accred, 'utf8');

// Simple regex replace for header
content1 = content1.replace(
  /<!-- Header -->\s*<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-hairline pb-4">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/,
  `<PageHeader title="Accreditation Evidence Repository">
   <template #subtitle>Quality assurance documentation and accreditation criterion evidence for institutional compliance.</template>
   <template #actions>
    <select
     v-model="selectedCycleId"
     class="text-xs rounded-md border border-hairline bg-surface text-ink-secondary px-3 py-1.5 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
    >
     <option v-for="c in cycles" :key="c.id" :value="c.id">
      {{ c.name }} ({{ c.starts_on }} - {{ c.ends_on || 'Present' }})
     </option>
    </select>
    <BaseButton variant="outline" size="sm" :loading="loading" @click="refreshAll">
     <RefreshCw class="w-4 h-4 mr-1.5" />
     Refresh
    </BaseButton>
    <BaseButton
     v-if="authStore.hasPermission('accreditation.export')"
     variant="outline"
     size="sm"
     @click="handleExportDossier"
    >
     <Download class="w-4 h-4 mr-1.5" />
     Export Dossier
    </BaseButton>
    <BaseButton
     v-if="authStore.hasPermission('accreditation.submit_evidence')"
     variant="primary"
     size="sm"
     @click="openAttachModal"
    >
     <Plus class="w-4 h-4 mr-1.5" />
     Attach evidence
    </BaseButton>
   </template>
  </PageHeader>`
);

// Add error handling
if (!content1.includes('const error = ref')) {
  content1 = content1.replace(
    'const loading = ref(true)',
    'const loading = ref(true)\nconst error = ref<string | null>(null)'
  );

  // Add error alert after PageHeader
  content1 = content1.replace(
    '</PageHeader>',
    `</PageHeader>

   <BaseAlert v-if="error" variant="error">
    {{ error }}
    <template #actions>
     <BaseButton size="sm" variant="outline" @click="refreshAll">
      <RefreshCw class="w-4 h-4 mr-1.5" />
      Retry
     </BaseButton>
    </template>
   </BaseAlert>`
  );

  // Update refreshAll
  content1 = content1.replace(
    'async function refreshAll() {',
    'async function refreshAll() {\n error.value = null'
  );

  // Add error.value assignment in catch
  content1 = content1.replace(
    /(async function loadCycles\(\) \{[\s\S]*?} catch \(err: any\) \{[\s\S]*?)(}\s*}\s*async function loadEvidence)/,
    (match, before, after) => {
      if (!before.includes('error.value =')) {
        return before.replace(
          /toast\.error\([^)]+\)/,
          `toast.error(err.response?.data?.message || 'Failed to load cycles')\n   error.value = err.response?.data?.message || 'Failed to load data'`
        ) + after;
      }
      return match;
    }
  );
}

fs.writeFileSync(accred, content1, 'utf8');
console.log('✓ AccreditationEvidenceView - PageHeader + error');

// 2. ProjectsInnovationView
const projects = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\projects\\ProjectsInnovationView.vue';
if (fs.existsSync(projects)) {
  let content2 = fs.readFileSync(projects, 'utf8');

  // Add imports if missing
  if (!content2.includes('PageHeader.vue')) {
    content2 = content2.replace(
      /(import LoadingSkeleton from '@\/components\/ui\/LoadingSkeleton\.vue')/,
      `$1\nimport PageHeader from '@/components/PageHeader.vue'\nimport BaseAlert from '@/components/ui/BaseAlert.vue'`
    );
  }

  // Replace header
  content2 = content2.replace(
    /<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-hairline pb-4">[\s\S]*?<h1[\s\S]*?Community Engagement & Innovation Projects[\s\S]*?<\/h1>[\s\S]*?<\/p>[\s\S]*?<\/div>[\s\S]*?<\/div>\s*<\/div>/,
    `<PageHeader title="Community Engagement & Innovation Projects">
   <template #subtitle>University outreach projects, community partnerships, and innovation initiatives.</template>
   <template #actions>
    <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
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
    </BaseButton>
   </template>
  </PageHeader>`
  );

  // Add error handling
  if (!content2.includes('const error = ref')) {
    content2 = content2.replace(
      'const loading = ref(true)',
      'const loading = ref(true)\nconst error = ref<string | null>(null)'
    );

    content2 = content2.replace(
      '</PageHeader>',
      `</PageHeader>

   <BaseAlert v-if="error" variant="error">
    {{ error }}
    <template #actions>
     <BaseButton size="sm" variant="outline" @click="loadData">
      <RefreshCw class="w-4 h-4 mr-1.5" />
      Retry
     </BaseButton>
    </template>
   </BaseAlert>`
    );

    content2 = content2.replace(
      'async function loadData() {',
      'async function loadData() {\n error.value = null'
    );

    content2 = content2.replace(
      /(} catch \(err: any\) \{[\s\S]*?toast\.error\([^)]+\))/,
      `$1\n   error.value = err.response?.data?.message || 'Failed to load projects'`
    );
  }

  fs.writeFileSync(projects, content2, 'utf8');
  console.log('✓ ProjectsInnovationView - PageHeader + error');
} else {
  console.log('✗ ProjectsInnovationView not found');
}

// 3. SignaturesStampsView
const sigs = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\signatures\\SignaturesStampsView.vue';
if (fs.existsSync(sigs)) {
  let content3 = fs.readFileSync(sigs, 'utf8');

  // Add imports if missing
  if (!content3.includes('PageHeader.vue')) {
    content3 = content3.replace(
      /(import LoadingSkeleton from '@\/components\/ui\/LoadingSkeleton\.vue')/,
      `$1\nimport PageHeader from '@/components/PageHeader.vue'\nimport BaseAlert from '@/components/ui/BaseAlert.vue'`
    );
  }

  // Replace header
  content3 = content3.replace(
    /<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-hairline pb-4">[\s\S]*?<h1[\s\S]*?Digital Signature[\s\S]*?<\/h1>[\s\S]*?<\/p>[\s\S]*?<\/div>[\s\S]*?<\/div>\s*<\/div>/,
    `<PageHeader title="Digital Signature & Official Stamp Registry">
   <template #subtitle>Manage institutional authority signatures, digital seal images, and approval delegation records.</template>
   <template #actions>
    <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
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
    </BaseButton>
   </template>
  </PageHeader>`
  );

  // Add error handling
  if (!content3.includes('const error = ref')) {
    content3 = content3.replace(
      'const loading = ref(true)',
      'const loading = ref(true)\nconst error = ref<string | null>(null)'
    );

    content3 = content3.replace(
      '</PageHeader>',
      `</PageHeader>

   <BaseAlert v-if="error" variant="error">
    {{ error }}
    <template #actions>
     <BaseButton size="sm" variant="outline" @click="loadData">
      <RefreshCw class="w-4 h-4 mr-1.5" />
      Retry
     </BaseButton>
    </template>
   </BaseAlert>`
    );

    content3 = content3.replace(
      'async function loadData() {',
      'async function loadData() {\n error.value = null'
    );

    content3 = content3.replace(
      /(} catch \(err: any\) \{[\s\S]*?toast\.error\([^)]+\))/,
      `$1\n   error.value = err.response?.data?.message || 'Failed to load signatures'`
    );
  }

  fs.writeFileSync(sigs, content3, 'utf8');
  console.log('✓ SignaturesStampsView - PageHeader + error');
} else {
  console.log('✗ SignaturesStampsView not found');
}

console.log('\n✓ All 3 remaining files processed');
