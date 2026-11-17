const fs = require('fs');
const path = require('path');

function walk(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  list.forEach(file => {
    const full = path.join(dir, file);
    const stat = fs.statSync(full);
    if (stat && stat.isDirectory()) {
      results = results.concat(walk(full));
    } else {
      results.push(full);
    }
  });
  return results;
}

const dir = 'artifacts/full-app-ui-qa/screenshots';
const files = walk(dir);
console.log('Total screenshot files in artifacts/full-app-ui-qa/screenshots:', files.length);

// Group by viewport
const viewports = {
  '1920': 0,
  '1366': 0,
  '1024': 0,
  '768': 0,
  '390': 0,
  '360': 0,
  'other': 0
};

const routeCoverage = {};

files.forEach(f => {
  const base = path.basename(f);
  let matchedVp = false;
  ['1920', '1366', '1024', '768', '390', '360'].forEach(vp => {
    if (base.includes(vp)) {
      viewports[vp]++;
      matchedVp = true;
    }
  });
  if (!matchedVp) viewports['other']++;
  
  // Try to extract route/feature name
  const rel = path.relative(dir, f);
  const parts = rel.split(path.sep);
  const category = parts[0];
  routeCoverage[category] = (routeCoverage[category] || 0) + 1;
});

console.log('\n--- VIEWPORT BREAKDOWN ---');
console.log(JSON.stringify(viewports, null, 2));

console.log('\n--- DIRECTORY / CATEGORY COVERAGE ---');
console.log(JSON.stringify(routeCoverage, null, 2));

console.log('\n--- SAMPLE SCREENSHOT FILES ---');
files.slice(0, 25).forEach(f => console.log(' - ' + path.relative(dir, f)));
