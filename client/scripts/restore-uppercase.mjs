import fs from 'fs';

const file = 'c:\\Users\\IT3\\Desktop\\DMS\\client\\src\\views\\organization\\OrganizationUnitsView.vue';
const content = fs.readFileSync(file, 'utf8');

// Find BaseInput with form.code and add class="uppercase"
const updated = content.replace(
  /(<BaseInput[\s\S]*?v-model="form\.code"[\s\S]*?placeholder="e\.g\. CNCS, CS-DEPARTMENT, REGISTRAR"\s*)([\s\S]*?:disabled="isEditing"[\s\S]*?\/>)/,
  (match, before, after) => {
    // Check if class already exists
    if (before.includes('class=') || after.includes('class=')) {
      console.log('✗ Class attribute already present');
      return match;
    }
    // Insert class before :disabled
    return before + '\n class="uppercase"' + after;
  }
);

if (updated !== content) {
  fs.writeFileSync(file, updated, 'utf8');
  console.log('✓ Added uppercase class to code input in OrganizationUnitsView.vue');
} else {
  console.log('✗ No changes made');
}
