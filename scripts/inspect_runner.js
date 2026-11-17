const fs = require('fs');
const runnerContent = fs.readFileSync('artifacts/full-app-ui-qa/full-audit-runner.mjs', 'utf8');

console.log('Runner length:', runnerContent.length);

const lines = runnerContent.split('\n');
console.log('Runner total lines:', lines.length);

// Search for 48, interaction, flows, etc.
lines.forEach((l, idx) => {
  if (l.includes('48') || l.includes('flow') || l.includes('interaction') || l.includes('interactiveControlsCount')) {
    console.log(`L${idx+1}: ${l.trim()}`);
  }
});
