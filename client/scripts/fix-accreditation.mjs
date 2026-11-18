import fs from 'fs';

const file = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\accreditation\\AccreditationEvidenceView.vue';
let content = fs.readFileSync(file, 'utf8');

// Find and replace header
const startMarker = '<!-- Header -->';
const endMarker = '<Plus class="w-4 h-4 mr-1.5" />';

const startIdx = content.indexOf(startMarker);
if (startIdx === -1) {
  console.log('✗ Could not find header marker');
  process.exit(1);
}

const endIdx = content.indexOf(endMarker, startIdx);
if (endIdx === -1) {
  console.log('✗ Could not find end marker');
  process.exit(1);
}

// Find the closing tags after endMarker
const afterEnd = content.substring(endIdx + endMarker.length);
const closingMatch = afterEnd.match(/^\s*Link Evidence to Standard\s*<\/BaseButton>\s*<\/div>\s*<\/div>/);

if (!closingMatch) {
  console.log('✗ Could not find closing tags');
  process.exit(1);
}

const fullEndIdx = endIdx + endMarker.length + closingMatch[0].length;

const newHeader = `<PageHeader title="Accreditation Evidence Repository">
   <template #subtitle>Quality assurance documentation and accreditation criterion evidence for institutional compliance.</template>
   <template #actions>
    <!-- Cycle Picker -->
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
     Link Evidence to Standard
    </BaseButton>
   </template>
  </PageHeader>`;

content = content.substring(0, startIdx) + newHeader + content.substring(fullEndIdx);

// Add error ref
if (!content.includes('const error = ref')) {
  content = content.replace(
    'const loading = ref(true)',
    'const loading = ref(true)\nconst error = ref<string | null>(null)'
  );
}

// Add error state in template after space-y-6
const spaceIdx = content.indexOf('<div class="space-y-6">');
if (spaceIdx > 0) {
  const afterSpace = content.substring(spaceIdx);
  const pageHeaderIdx = afterSpace.indexOf('<PageHeader');
  if (pageHeaderIdx > 0) {
    const insertIdx = spaceIdx + pageHeaderIdx;
    const errorBlock = `<BaseAlert v-if="error" variant="error" class="mb-4">
    {{ error }}
    <template #actions>
     <BaseButton size="sm" variant="outline" @click="refreshAll">
      <RefreshCw class="w-4 h-4 mr-1.5" />
      Retry
     </BaseButton>
    </template>
   </BaseAlert>

   `;
    content = content.substring(0, insertIdx) + errorBlock + content.substring(insertIdx);
  }
}

// Update refreshAll to clear error
content = content.replace(
  /async function refreshAll\(\) \{/,
  'async function refreshAll() {\n error.value = null'
);

// Set error in catch blocks
content = content.replace(
  /(} catch \(err: any\) \{[\s\S]*?toast\.error\([^)]+\))/g,
  (match) => {
    if (!match.includes('error.value =')) {
      return match + '\n   error.value = err.response?.data?.message || \'Failed to load data\'';
    }
    return match;
  }
);

fs.writeFileSync(file, content, 'utf8');
console.log('✓ AccreditationEvidenceView.vue - PageHeader + error state applied');
