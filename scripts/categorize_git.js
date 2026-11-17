const { execSync } = require('child_process');
const fs = require('fs');

const diffNameStatus = execSync('git diff --name-status HEAD', { encoding: 'utf8' }).trim().split('\n').filter(Boolean);
const untracked = execSync('git ls-files --others --exclude-standard', { encoding: 'utf8' }).trim().split('\n').filter(Boolean);

console.log('Total diff entries against HEAD:', diffNameStatus.length);
console.log('Total untracked files:', untracked.length);

const categories = {
  1: { title: 'Modified Production Vue Views', files: [] },
  2: { title: 'Newly Created Production Views', files: [] },
  3: { title: 'Modified Shared Frontend Components', files: [] },
  4: { title: 'Backend Production Changes', files: [] },
  5: { title: 'Test-Only Changes (Frontend & Backend)', files: [] },
  6: { title: 'QA Scripts and Generated Artifacts', files: [] },
  7: { title: 'Earlier Project Changes / Unattributed Infrastructure', files: [] }
};

diffNameStatus.forEach(line => {
  const parts = line.split('\t');
  const status = parts[0];
  const file = parts[1];
  
  if (file.startsWith('client/src/views/')) {
    categories[1].files.push({ file, status });
  } else if (file.startsWith('client/src/components/')) {
    categories[3].files.push({ file, status });
  } else if (file.startsWith('client/tests/') || file.startsWith('server/tests/')) {
    categories[5].files.push({ file, status });
  } else if (file.startsWith('scripts/') || file.startsWith('artifacts/')) {
    categories[6].files.push({ file, status });
  } else if (file.startsWith('server/app/') || file.startsWith('server/routes/') || file.startsWith('server/resources/views/')) {
    categories[4].files.push({ file, status });
  } else {
    categories[7].files.push({ file, status });
  }
});

untracked.forEach(file => {
  if (file.startsWith('client/src/views/')) {
    categories[2].files.push({ file, status: 'A' });
  } else if (file.startsWith('client/src/components/')) {
    categories[3].files.push({ file, status: 'A' });
  } else if (file.startsWith('client/tests/') || file.startsWith('server/tests/')) {
    categories[5].files.push({ file, status: 'A' });
  } else if (file.startsWith('scripts/') || file.startsWith('artifacts/')) {
    categories[6].files.push({ file, status: 'A' });
  } else if (file.startsWith('server/app/') || file.startsWith('server/resources/views/')) {
    categories[4].files.push({ file, status: 'A' });
  } else {
    categories[7].files.push({ file, status: 'A' });
  }
});

console.log('\n=== GIT ATTRIBUTION BREAKDOWN ===');
for (let i = 1; i <= 7; i++) {
  console.log(`\n[Category ${i}] ${categories[i].title} (${categories[i].files.length} files):`);
  categories[i].files.slice(0, 15).forEach(f => {
    console.log(`  - [${f.status}] ${f.file}`);
  });
  if (categories[i].files.length > 15) {
    console.log(`  ... and ${categories[i].files.length - 15} more`);
  }
}
