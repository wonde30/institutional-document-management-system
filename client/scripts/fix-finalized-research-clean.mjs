import fs from 'fs';

const file = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\research\\FinalizedResearchView.vue';
let content = fs.readFileSync(file, 'utf8');

// Find the header block starting with <!-- Header --> and ending before <!-- Institutional Policy Notice -->
const startIdx = content.indexOf('<!-- Header -->');
const endIdx = content.indexOf('<!-- Institutional Policy Notice -->');

if (startIdx === -1 || endIdx === -1) {
  console.log('✗ Could not find header markers');
  process.exit(1);
}

// Extract everything between
const beforeHeader = content.substring(0, startIdx);
const afterHeader = content.substring(endIdx);

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
  </PageHeader>

  `;

content = beforeHeader + newHeader + afterHeader;

// Add EmptyState for no results
content = content.replace(
  /(<LoadingSkeleton v-if="loading" :count="4" class="p-4" \/>)\s*<!-- Table Directory -->/,
  `$1

   <!-- Empty State -->
   <EmptyState v-else-if="filteredList.length === 0" icon="BookOpen" message="No research records found" class="p-4" />

   <!-- Table Directory -->`
);

fs.writeFileSync(file, content, 'utf8');
console.log('✓ FinalizedResearchView - PageHeader + EmptyState applied');
