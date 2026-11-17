const fs = require('fs');
const path = require('path');

function walk(dir) {
  let results = [];
  const list = fs.readdirSync(dir);
  list.forEach(file => {
    const full = path.join(dir, file);
    const stat = fs.statSync(full);
    if (stat && stat.isDirectory()) results = results.concat(walk(full));
    else results.push(full);
  });
  return results;
}

const dir = 'artifacts/full-app-ui-qa/screenshots';
const files = walk(dir);

const intermediateFiles = files.filter(f => f.includes('1366x768') || f.includes('1024x768') || f.includes('768x1024') || f.includes('360x800'));
intermediateFiles.forEach(f => {
  console.log(path.relative(dir, f));
});
