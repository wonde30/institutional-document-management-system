import fs from 'fs';

const files = [
  {
    path: 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\staff\\StaffRecordsView.vue',
    title: 'Staff Professional Development Records',
    subtitle: 'Approved proposals and finalized trainings for institutional staff capacity development.',
    needsEmpty: true,
  },
  {
    path: 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\archive\\ArchiveListView.vue',
    title: 'Institutional Physical Archive Registry',
    subtitle: 'Browse hard-copy file locations, boxes, shelves, and active/closed collection records.',
    needsEmpty: false, // already has empty
    needsTableWrap: true,
  },
  {
    path: 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\accreditation\\AccreditationEvidenceView.vue',
    title: 'Accreditation Evidence Repository',
    subtitle: 'Quality assurance documentation and accreditation criterion evidence for institutional compliance.',
    needsEmpty: true,
    needsError: true,
  },
  {
    path: 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\innovation\\ProjectsInnovationView.vue',
    title: 'Community Engagement & Innovation Projects',
    subtitle: 'University outreach projects, community partnerships, and innovation initiatives.',
    needsEmpty: false, // already has empty
    needsError: true,
  },
  {
    path: 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\signatures\\SignaturesStampsView.vue',
    title: 'Digital Signature & Official Stamp Registry',
    subtitle: 'Manage institutional authority signatures, digital seal images, and approval delegation records.',
    needsEmpty: false, // already has empty
    needsError: true,
  },
  {
    path: 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\reports\\ReportsView.vue',
    title: 'Analytical Reports & Insights',
    subtitle: 'Pre-configured analytics dashboards for document activity, compliance, and system usage patterns.',
    needsEmpty: true,
  },
  {
    path: 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\organization\\OrganizationUnitsView.vue',
    title: 'Institutional Organization Structure',
    subtitle: 'Hierarchical structure of colleges, directorates, departments, and academic units across Wollo University campuses.',
    needsEmpty: false, // already has empty
    loadingVar: 'isLoading', // different var name
  },
];

files.forEach((spec) => {
  if (!fs.existsSync(spec.path)) {
    console.log(`✗ ${spec.path.split('\\').pop()} - file not found`);
    return;
  }

  let content = fs.readFileSync(spec.path, 'utf8');
  const filename = spec.path.split('\\').pop();

  // Skip if already has PageHeader
  if (content.includes('<PageHeader')) {
    console.log(`○ ${filename} - already has PageHeader`);
    return;
  }

  // Step 1: Add imports
  if (!content.includes('PageHeader.vue')) {
    content = content.replace(
      /(import LoadingSkeleton from '@\/components\/ui\/LoadingSkeleton\.vue')/,
      `$1\nimport EmptyState from '@/components/ui/EmptyState.vue'\nimport PageHeader from '@/components/PageHeader.vue'\nimport BaseAlert from '@/components/ui/BaseAlert.vue'`
    );
  }

  // Step 2: Replace inline header with PageHeader
  // Find the header section that starts with h1
  const headerMatch = content.match(/(\s*)<h1 class="text-2xl font-semibold[^>]*>[\s\S]*?<\/h1>\s*<p class="[^"]*text-ink-muted[^"]*">\s*([^<]+)<\/p>\s*<\/div>\s*<div class="flex items-center gap-2\.5?">([\s\S]*?)<\/div>\s*<\/div>/);
  
  if (headerMatch) {
    const indent = headerMatch[1];
    const subtitle = headerMatch[2].trim();
    const actions = headerMatch[3];

    // Find the opening div before h1
    const beforeH1 = content.substring(0, headerMatch.index);
    const lastDivOpen = beforeH1.lastIndexOf('<div class="flex flex-col sm:flex-row');
    
    if (lastDivOpen > 0) {
      const fullHeaderStart = lastDivOpen;
      const fullHeaderEnd = headerMatch.index + headerMatch[0].length;
      const headerBlock = content.substring(fullHeaderStart, fullHeaderEnd);

      const newHeader = `${indent}<PageHeader title="${spec.title}">
${indent} <template #subtitle>${spec.subtitle}</template>
${indent} <template #actions>
${actions}
${indent} </template>
${indent}</PageHeader>`;

      content = content.substring(0, fullHeaderStart) + newHeader + content.substring(fullHeaderEnd);
      console.log(`✓ ${filename} - PageHeader applied`);
    }
  } else {
    console.log(`○ ${filename} - header pattern not matched`);
  }

  // Step 3: Add EmptyState if needed
  if (spec.needsEmpty) {
    // Find empty message like "No records found" or similar
    const emptyPattern = /(<div[^>]*class="[^"]*text-center[^"]*"[^>]*>\s*)(No [^<]+<\/div>)/;
    if (emptyPattern.test(content)) {
      content = content.replace(
        emptyPattern,
        '<EmptyState icon="Inbox" message="$2" class="p-8" />'
      );
      console.log(`✓ ${filename} - EmptyState added`);
    }
  }

  // Step 4: Add error+retry if needed
  if (spec.needsError) {
    // Look for loading check and add error state before it
    const loadVar = spec.loadingVar || 'loading';
    const pattern = new RegExp(`<LoadingSkeleton v-if="${loadVar}"`);
    if (pattern.test(content) && !content.includes('BaseAlert')) {
      content = content.replace(
        pattern,
        `<BaseAlert v-if="error" variant="error" class="m-4">
     {{ error }}
     <template #actions>
      <BaseButton size="sm" variant="outline" @click="loadData">
       <RefreshCw class="w-4 h-4 mr-1.5" />
       Retry
      </BaseButton>
     </template>
    </BaseAlert>

    <LoadingSkeleton v-else-if="${loadVar}"`
      );

      // Add error ref if not present
      if (!content.includes('const error = ref')) {
        content = content.replace(
          new RegExp(`const ${loadVar} = ref\\(true\\)`),
          `const ${loadVar} = ref(true)\nconst error = ref<string | null>(null)`
        );
      }

      // Update loadData to set error
      content = content.replace(
        /(} catch \([^)]+\) \{[\s\S]*?toast\.error\([^)]+\))/,
        `$1\n   error.value = err.response?.data?.message || 'Failed to load data'`
      );

      // Clear error on success
      content = content.replace(
        /async function loadData\(\) \{[\s\S]*?loading\.value = true/,
        (match) => match.replace('loading.value = true', 'loading.value = true\n error.value = null')
      );

      console.log(`✓ ${filename} - error+retry added`);
    }
  }

  // Step 5: Wrap tables if needed
  if (spec.needsTableWrap) {
    // Find table not inside overflow-x-auto
    const tablePattern = /(\s*)<table\s/;
    if (tablePattern.test(content) && !content.includes('overflow-x-auto')) {
      // Check if table is directly in section
      const sectionPattern = /(<section[^>]*>)\s*(<table)/;
      if (sectionPattern.test(content)) {
        content = content.replace(
          sectionPattern,
          '$1\n   <div class="overflow-x-auto">\n    $2'
        );
        // Close the div before </section>
        content = content.replace(
          /(<\/table>)\s*(<\/section>)/,
          '$1\n   </div>\n  $2'
        );
        console.log(`✓ ${filename} - table wrapped`);
      }
    }
  }

  fs.writeFileSync(spec.path, content, 'utf8');
});

console.log('\n✓ Batch 3 transformations complete');
