import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const srcDir = path.resolve(__dirname, '../src');

function getAllVueFiles(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  
  list.forEach((file) => {
    const filePath = path.join(dir, file);
    const stat = fs.statSync(filePath);
    
    if (stat && stat.isDirectory()) {
      if (!file.startsWith('.') && file !== 'node_modules') {
        results = results.concat(getAllVueFiles(filePath));
      }
    } else if (file.endsWith('.vue')) {
      results.push(filePath);
    }
  });
  
  return results;
}

const vueFiles = getAllVueFiles(srcDir);

const importsButNoTag = [];
const hasTagButNoImport = [];

vueFiles.forEach((filePath) => {
  const content = fs.readFileSync(filePath, 'utf8');
  const relativePath = path.relative(srcDir, filePath).replace(/\\/g, '/');
  
  // Check if imports PageHeader
  const importsPageHeader = /import\s+.*PageHeader.*from/.test(content);
  
  // Check if uses <PageHeader tag
  const usesPageHeaderTag = /<PageHeader[\s>]/.test(content);
  
  if (importsPageHeader && !usesPageHeaderTag) {
    importsButNoTag.push(relativePath);
  }
  
  if (usesPageHeaderTag && !importsPageHeader) {
    hasTagButNoImport.push(relativePath);
  }
});

console.log('PAGEHEADER USAGE AUDIT');
console.log('======================\n');

console.log('(a) Imports PageHeader but has NO <PageHeader tag:');
if (importsButNoTag.length === 0) {
  console.log('  ✓ None');
} else {
  importsButNoTag.forEach(file => console.log(`  - ${file}`));
}

console.log('\n(b) Has <PageHeader tag but NO import:');
if (hasTagButNoImport.length === 0) {
  console.log('  ✓ None');
} else {
  hasTagButNoImport.forEach(file => console.log(`  - ${file}`));
}

console.log(`\nTotal issues: ${importsButNoTag.length + hasTagButNoImport.length}`);
process.exit(importsButNoTag.length + hasTagButNoImport.length > 0 ? 1 : 0);
