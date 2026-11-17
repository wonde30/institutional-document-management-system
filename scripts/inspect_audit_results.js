const fs = require('fs');

const data = JSON.parse(fs.readFileSync('artifacts/full-app-ui-qa/audit_execution_results.json', 'utf8'));

console.log('Keys in audit_execution_results.json:', Object.keys(data));
console.log('Total DOM elements recorded:', data.totalElementsDiscovered || (data.elements ? data.elements.length : 'N/A'));

if (data.interactionFlows) {
  console.log('Interaction flows count:', data.interactionFlows.length);
  console.log('Flow names:');
  data.interactionFlows.forEach((f, i) => {
    console.log(` [${i+1}] ${f.name || f.description || f.id} -> Status: ${f.status || f.result}`);
  });
} else {
  console.log('No direct interactionFlows key. Inspecting properties...');
  for (let k of Object.keys(data)) {
    if (Array.isArray(data[k])) {
      console.log(`- ${k}: ${data[k].length} items`);
    } else if (typeof data[k] === 'object' && data[k] !== null) {
      console.log(`- ${k}: object with keys [${Object.keys(data[k]).join(', ')}]`);
    } else {
      console.log(`- ${k}: ${data[k]}`);
    }
  }
}
