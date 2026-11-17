const { execSync } = require('child_process');
const fs = require('fs');

const statusOutput = execSync('git status --porcelain', { encoding: 'utf8' });
const lines = statusOutput.trim().split('\n').filter(Boolean);

console.log('Total changed items in git status:', lines.length);

const modified = [];
const untracked = [];
const deleted = [];

lines.forEach(line => {
  const code = line.substring(0, 2);
  const file = line.substring(3).trim();
  if (code.includes('M')) modified.push(file);
  else if (code.includes('??')) untracked.push(file);
  else if (code.includes('D')) deleted.push(file);
  else modified.push(file);
});

console.log('Modified count:', modified.length);
console.log('Untracked count:', untracked.length);
console.log('Deleted count:', deleted.length);

// Frontend pages modified
const frontendViews = modified.filter(f => f.startsWith('client/src/views/'));
console.log('\n--- FRONTEND VIEWS MODIFIED (' + frontendViews.length + ') ---');
frontendViews.forEach(f => console.log(' - ' + f));

// Frontend components modified
const frontendComponents = modified.filter(f => f.startsWith('client/src/components/'));
console.log('\n--- FRONTEND COMPONENTS MODIFIED (' + frontendComponents.length + ') ---');
frontendComponents.forEach(f => console.log(' - ' + f));

// Frontend other (router, stores, assets, package)
const frontendOther = modified.filter(f => f.startsWith('client/') && !f.startsWith('client/src/views/') && !f.startsWith('client/src/components/'));
console.log('\n--- FRONTEND OTHER MODIFIED (' + frontendOther.length + ') ---');
frontendOther.forEach(f => console.log(' - ' + f));

// Backend modified
const backendModified = modified.filter(f => f.startsWith('server/'));
console.log('\n--- BACKEND MODIFIED (' + backendModified.length + ') ---');
backendModified.forEach(f => console.log(' - ' + f));

// Untracked files breakdown
console.log('\n--- UNTRACKED FILES SAMPLE ---');
untracked.slice(0, 20).forEach(f => console.log(' - ' + f));
