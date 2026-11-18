import fs from 'fs';

const files = [
  'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\search\\GlobalSearchView.vue',
  'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\notifications\\NotificationsView.vue',
  'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\profile\\AccountProfileView.vue',
];

files.forEach((filePath) => {
  const filename = filePath.split('\\').pop();
  
  if (!fs.existsSync(filePath)) {
    console.log(`✗ ${filename} - not found`);
    return;
  }

  let content = fs.readFileSync(filePath, 'utf8');

  // Add BaseAlert import if missing
  if (!content.includes('BaseAlert')) {
    content = content.replace(
      /(import.*?from '@\/components\/ui\/\w+\.vue'\n)/,
      `$1import BaseAlert from '@/components/ui/BaseAlert.vue'\n`
    );
  }

  // Add error ref if missing
  if (!content.includes('const error = ref')) {
    content = content.replace(
      /const isLoading = ref\([^)]+\)/,
      `$&\nconst error = ref<string | null>(null)`
    );
  }

  // Add error alert before LoadingSkeleton in template
  const loadingPattern = /<LoadingSkeleton\s+v-if="isLoading"/;
  if (loadingPattern.test(content) && !content.includes('BaseAlert v-if="error"')) {
    content = content.replace(
      loadingPattern,
      `<BaseAlert v-if="error" variant="error" class="mb-4">
    {{ error }}
    <template #actions>
     <BaseButton size="sm" variant="outline" @click="loadData || performSearch">
      <RefreshCw class="w-4 h-4 mr-1.5" />
      Retry
     </BaseButton>
    </template>
   </BaseAlert>

   <LoadingSkeleton v-else-if="isLoading"`
    );
  }

  // Add RefreshCw icon import if missing
  if (!content.includes('RefreshCw') && content.includes('lucide-vue-next')) {
    content = content.replace(
      /(import \{[^}]+)(\} from 'lucide-vue-next')/,
      `$1,\n RefreshCw$2`
    );
  }

  // Clear error at start of search/load functions
  const fnPattern = /async function (loadData|performSearch|executeSearch)\(\) \{/g;
  content = content.replace(fnPattern, (match) => {
    if (!content.substring(content.indexOf(match), content.indexOf(match) + 200).includes('error.value = null')) {
      return match + '\n error.value = null';
    }
    return match;
  });

  // Add error handling in catch blocks
  content = content.replace(
    /(} catch \([^)]+\) \{[\s\S]{0,150}?)(toast\.[^(]+\([^)]+\))/g,
    (match, before, toastCall) => {
      if (!match.includes('error.value =')) {
        return match.replace(toastCall, `${toastCall}\n   error.value = err.response?.data?.message || 'Failed to load data'`);
      }
      return match;
    }
  );

  fs.writeFileSync(filePath, content, 'utf8');
  console.log(`✓ ${filename} - error+retry added`);
});

console.log('\n✓ Task B complete - error+retry added to 3 views');
