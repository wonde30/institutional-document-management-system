import fs from 'fs';

// 1. ProjectsInnovationView
const projectsFile = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\projects\\ProjectsInnovationView.vue';
let content1 = fs.readFileSync(projectsFile, 'utf8');

const projectsOldHeader = content1.match(/<!-- Header Row -->\s*<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/);

if (projectsOldHeader) {
  const newHeader = `<PageHeader title="Community Engagement & Innovation Projects">
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
     @click="openCreateModal"
    >
     <Plus class="w-4 h-4 mr-1.5" />
     Register Innovation Project
    </BaseButton>
   </template>
  </PageHeader>`;

  content1 = content1.replace(projectsOldHeader[0], newHeader);
  fs.writeFileSync(projectsFile, content1, 'utf8');
  console.log('✓ ProjectsInnovationView - PageHeader applied');
} else {
  console.log('✗ ProjectsInnovationView - header not found');
}

// 2. SignaturesStampsView
const sigsFile = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\signatures\\SignaturesStampsView.vue';
let content2 = fs.readFileSync(sigsFile, 'utf8');

const sigsOldHeader = content2.match(/<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-hairline pb-4">[\s\S]*?<h1[\s\S]*?Digital Signature[\s\S]*?<\/h1>[\s\S]*?<\/p>[\s\S]*?<\/div>\s*<\/div>/);

if (sigsOldHeader) {
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
     @click="openCreateModal"
    >
     <Plus class="w-4 h-4 mr-1.5" />
     Register Signature/Stamp
    </BaseButton>
   </template>
  </PageHeader>`;

  content2 = content2.replace(sigsOldHeader[0], newHeader);
  fs.writeFileSync(sigsFile, content2, 'utf8');
  console.log('✓ SignaturesStampsView - PageHeader applied');
} else {
  console.log('✗ SignaturesStampsView - header not found');
}

console.log('\n✓ Fixed Projects and Signatures views');
