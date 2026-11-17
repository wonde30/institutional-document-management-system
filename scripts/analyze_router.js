const fs = require('fs');

const content = fs.readFileSync('client/src/router/index.ts', 'utf8');

// Parse route entries
const startIndex = content.indexOf('const routes: RouteRecordRaw[] = [');
const endIndex = content.indexOf('const router = createRouter');
const routeBlock = content.substring(startIndex + 33, endIndex);

const lines = routeBlock.split('\n');
const entries = [];
let currentLines = [];
let braceCount = 0;
let inEntry = false;

for (let line of lines) {
  const trimmed = line.trim();
  if (trimmed.startsWith('//') || trimmed === '') continue;
  
  if (trimmed.includes('{')) {
    inEntry = true;
  }
  
  if (inEntry) {
    currentLines.push(trimmed);
    for (let char of trimmed) {
      if (char === '{') braceCount++;
      if (char === '}') braceCount--;
    }
    if (braceCount === 0) {
      entries.push(currentLines.join(' '));
      currentLines = [];
      inEntry = false;
    }
  }
}

console.log('Total route entries parsed:', entries.length);

const parsed = entries.map((str, idx) => {
  const pathMatch = str.match(/path:\s*['"]([^'"]+)['"]/);
  const nameMatch = str.match(/name:\s*['"]([^'"]+)['"]/);
  const redirectMatch = str.match(/redirect:\s*['"]([^'"]+)['"]/);
  const compMatch = str.match(/import\(['"]@\/views\/([^'"]+)['"]\)/);
  const aliasMatch = str.match(/alias:\s*(\[[^\]]+\]|['"][^'"]+['"])/);
  
  return {
    index: idx + 1,
    path: pathMatch ? pathMatch[1] : null,
    name: nameMatch ? nameMatch[1] : null,
    redirect: redirectMatch ? redirectMatch[1] : null,
    component: compMatch ? compMatch[1] : null,
    alias: aliasMatch ? aliasMatch[1] : null,
    raw: str
  };
});

const nonRedirects = parsed.filter(p => !p.redirect);
const redirects = parsed.filter(p => !!p.redirect);
const uniqueComponents = new Set(nonRedirects.map(p => p.component).filter(Boolean));

console.log('=== ROUTER VERIFICATION METRICS ===');
console.log('Total registered routes (entries in routes array):', parsed.length);
console.log('Non-redirect route paths:', nonRedirects.length);
console.log('Redirect route paths:', redirects.length);
console.log('Unique page component files imported:', uniqueComponents.size);

console.log('\n--- REDIRECT ROUTES (' + redirects.length + ') ---');
redirects.forEach(r => {
  console.log(`[${r.index}] ${r.path} -> ${r.redirect}`);
});

console.log('\n--- NON-REDIRECT ROUTES (' + nonRedirects.length + ') ---');
nonRedirects.forEach(r => {
  console.log(`[${r.index}] ${r.path.padEnd(28)} | name: ${(r.name || 'N/A').padEnd(26)} | component: ${r.component}`);
});

console.log('\n--- UNIQUE COMPONENT FILES (' + uniqueComponents.size + ') ---');
Array.from(uniqueComponents).sort().forEach(c => console.log(' - ' + c));

// Also let's check components that are reused across multiple non-redirect routes:
const compUsage = {};
nonRedirects.forEach(r => {
  if (r.component) {
    compUsage[r.component] = (compUsage[r.component] || []);
    compUsage[r.component].push(r.path);
  }
});
console.log('\n--- REUSED COMPONENTS ---');
Object.entries(compUsage).forEach(([comp, paths]) => {
  if (paths.length > 1) {
    console.log(`${comp} is reused by ${paths.length} routes: ${paths.join(', ')}`);
  }
});
