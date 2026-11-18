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

const viewsDir = path.join(baseDir, 'src', 'views');
const vueFiles = getAllVueFiles(viewsDir);

let fixedCount = 0;

vueFiles.forEach((filePath) => {
  let content = fs.readFileSync(filePath, 'utf8');
  
  // Pattern: <PageHeader title="...">
  //   <template #subtitle>...</template>
  // Replace with: <PageHeader title="..." subtitle="...">
  
  const pattern = /(<PageHeader\s+title="[^"]+")>\s*<template\s+#subtitle>([^<]+)<\/template>/g;
  
  if (pattern.test(content)) {
    content = content.replace(pattern, (match, titlePart, subtitleText) => {
      return `${titlePart} subtitle="${subtitleText.trim()}">`;
    });
    
    fs.writeFileSync(filePath, content, 'utf8');
    
    const filename = path.basename(filePath);
    console.log(`✓ ${filename} - fixed subtitle`);
    fixedCount++;
  }
});

console.log(`\nFixed ${fixedCount} files`);
