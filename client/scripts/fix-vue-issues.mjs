import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const baseDir = path.resolve(__dirname, '..');

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

const srcDir = path.join(baseDir, 'src');
const vueFiles = getAllVueFiles(srcDir);

let fixedCount = 0;

vueFiles.forEach((filePath) => {
  let content = fs.readFileSync(filePath, 'utf8');
  const originalContent = content;
  
  // Fix trailing commas in imports: ,\n} from or ,} from
  content = content.replace(/,(\s*)\}\s+from\s+(['"]lucide-vue-next['"])/g, '$1} from $2');
  
  // Fix duplicate empty imports
  content = content.replace(/import\s+\{\s*,/g, 'import {');
  
  if (content !== originalContent) {
    fs.writeFileSync(filePath, content, 'utf8');
    const filename = path.basename(filePath);
    console.log(`✓ ${filename}`);
    fixedCount++;
  }
});

console.log(`\nFixed ${fixedCount} files`);
