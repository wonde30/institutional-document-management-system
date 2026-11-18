import fs from 'fs';

const file = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\research\\FinalizedResearchView.vue';
let content = fs.readFileSync(file, 'utf8');

// Replace the inline header div with PageHeader
const oldHeader = content.match(/<!--\s*Header\s*-->\s*<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 border-b border-hairline pb-4">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/);

if (!oldHeader) {
  console.log('✗ Could not find header block');
  process.exit(1);
}

const newHeader = `<PageHeader title="Finalized Faculty Research Repository">
   <template #subtitle>Departmental publication of research completed and selected through external university processes.</template>
   <template #actions>
    <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
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
    </BaseButton>
   </template>
  </PageHeader>`;

content = content.replace(oldHeader[0], newHeader);

// Add EmptyState after LoadingSkeleton check in table section
const emptyPattern = /<!-- Table Directory -->\s*<div v-else-if="filteredList\.length === 0"/;
if (emptyPattern.test(content)) {
  content = content.replace(
    /<!-- Table Directory -->\s*<div v-else-if="filteredList\.length === 0"/,
    `<!-- Empty State -->
  <EmptyState v-else-if="filteredList.length === 0" icon="BookOpen" message="No research records found" class="p-8" />

  <!-- Table Directory -->
  <div v-else-if="false"`
  );
}

fs.writeFileSync(file, content, 'utf8');
console.log('✓ FinalizedResearchView.vue - PageHeader applied + EmptyState added');
