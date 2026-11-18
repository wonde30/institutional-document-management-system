import fs from 'fs';

const file = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\signatures\\SignaturesStampsView.vue';
let content = fs.readFileSync(file, 'utf8');

// Fix the error.value placement bug first
content = content.replace(
  /(if \(!form\.value\.document_version_id \|\| !form\.value\.signer_id \|\| !form\.value\.purpose\) \{\s*toast\.error\([^)]+\))\s*error\.value = err\.response\?\.data\?\.message \|\| 'Failed to load signatures'/,
  '$1'
);

// Find and replace header
const startMarker = '<!-- Header -->';
const endIdx = content.indexOf('<!-- Institutional Policy Notice');

const startIdx = content.lastIndexOf('<div class="flex flex-col', endIdx);

if (startIdx > 0 && endIdx > startIdx) {
  const beforeHeader = content.substring(0, startIdx);
  const afterHeader = content.substring(endIdx);

  const newHeader = `<PageHeader title="Digital Signature & Official Stamp Registry">
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
     @click="openSignatureModal"
    >
     <PenLine class="w-4 h-4 mr-1.5" />
     Request signature
    </BaseButton>
   </template>
  </PageHeader>

  <BaseAlert v-if="error" variant="error">
   {{ error }}
   <template #actions>
    <BaseButton size="sm" variant="outline" @click="loadData">
     <RefreshCw class="w-4 h-4 mr-1.5" />
     Retry
    </BaseButton>
   </template>
  </BaseAlert>

  `;

  content = beforeHeader + newHeader + afterHeader;

  // Update loadData to clear error
  content = content.replace(
    'async function loadData() {',
    'async function loadData() {\n error.value = null'
  );

  // Add error handling in catch blocks
  const catchPattern = /} catch \([^)]+\) \{\s*(toast\.error\([^)]+\))/g;
  content = content.replace(catchPattern, (match, toastCall) => {
    if (!match.includes('error.value =')) {
      return match.replace(toastCall, `${toastCall}\n   error.value = err.response?.data?.message || 'Failed to load data'`);
    }
    return match;
  });

  fs.writeFileSync(file, content, 'utf8');
  console.log('✓ SignaturesStampsView - PageHeader + error state applied');
} else {
  console.log('✗ Could not find header boundaries');
}
