const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

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

// Function to read PNG dimensions from header (IHDR chunk is at offset 16-24)
function getPngDimensions(filePath) {
  try {
    const fd = fs.openSync(filePath, 'r');
    const buffer = Buffer.alloc(24);
    fs.readSync(fd, buffer, 0, 24, 0);
    fs.closeSync(fd);
    
    // Check PNG signature: 89 50 4E 47 0D 0A 1A 0A
    if (buffer[0] === 0x89 && buffer[1] === 0x50 && buffer[2] === 0x4E && buffer[3] === 0x47) {
      const width = buffer.readUInt32BE(16);
      const height = buffer.readUInt32BE(20);
      return { width, height };
    }
  } catch (e) {
    return null;
  }
  return null;
}

const dir = 'artifacts/full-app-ui-qa/screenshots';
const files = walk(dir);

console.log('Total files found:', files.length);

const hashes = {};
const duplicates = [];
const dimensionGroups = {};
const fileDetails = [];

files.forEach(f => {
  const content = fs.readFileSync(f);
  const hash = crypto.createHash('sha256').update(content).digest('hex');
  if (hashes[hash]) {
    duplicates.push({ original: hashes[hash], duplicate: f, hash });
  } else {
    hashes[hash] = f;
  }
  
  const dims = getPngDimensions(f);
  const dimStr = dims ? `${dims.width}x${dims.height}` : 'unknown';
  dimensionGroups[dimStr] = (dimensionGroups[dimStr] || 0) + 1;
  
  fileDetails.push({
    path: f,
    relative: path.relative(dir, f),
    filename: path.basename(f),
    size: content.length,
    dimensions: dimStr,
    dims
  });
});

console.log('\n--- EXACT IMAGE DIMENSION GROUPS (FROM PNG HEADERS) ---');
Object.entries(dimensionGroups).sort((a,b) => b[1] - a[1]).forEach(([dim, count]) => {
  console.log(`  ${dim.padEnd(16)}: ${count} files`);
});

console.log('\n--- DUPLICATES CHECK ---');
console.log('Total duplicate files (identical SHA-256):', duplicates.length);
duplicates.forEach(d => {
  console.log(`Duplicate: ${d.duplicate} == ${d.original}`);
});

console.log('\n--- FILENAME PATTERN ANALYSIS ---');
const namePatterns = {};
fileDetails.forEach(f => {
  const name = f.filename;
  let vp = 'specialized';
  if (name.includes('1920x1080')) vp = '1920x1080';
  else if (name.includes('1366x768')) vp = '1366x768';
  else if (name.includes('1024x768')) vp = '1024x768';
  else if (name.includes('768x1024')) vp = '768x1024';
  else if (name.includes('390x844')) vp = '390x844';
  else if (name.includes('360x800')) vp = '360x800';
  
  namePatterns[vp] = (namePatterns[vp] || 0) + 1;
});
console.log(JSON.stringify(namePatterns, null, 2));
const sumPatterns = Object.values(namePatterns).reduce((a,b) => a+b, 0);
console.log('Sum of filename patterns:', sumPatterns);
