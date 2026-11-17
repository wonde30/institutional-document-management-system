const fs = require('fs');

const data = JSON.parse(fs.readFileSync('artifacts/full-app-ui-qa/audit_execution_results.json', 'utf8'));
const routerContent = fs.readFileSync('client/src/router/index.ts', 'utf8');

// Parse non-redirect paths from router
const lines = routerContent.split('\n');
const nonRedirectRoutes = [];
let currentPath = null;
let isRedirect = false;

for (let line of lines) {
  const pMatch = line.match(/path:\s*['"]([^'"]+)['"]/);
  if (pMatch) {
    if (currentPath && !isRedirect) {
      nonRedirectRoutes.push(currentPath);
    }
    currentPath = pMatch[1];
    isRedirect = false;
  }
  if (line.includes('redirect:')) {
    isRedirect = true;
  }
}
if (currentPath && !isRedirect) {
  nonRedirectRoutes.push(currentPath);
}

console.log('Non-redirect paths in router:', nonRedirectRoutes.length);
console.log('Explicit non-redirect (excluding wildcard):', nonRedirectRoutes.filter(p => !p.includes('*')).length);

const auditedPaths = data.routesAudited.map(r => r.path);
console.log('Audited paths in audit_execution_results.json:', auditedPaths.length);

const notInAudited = nonRedirectRoutes.filter(p => !auditedPaths.includes(p) && !p.includes('*'));
console.log('Explicit routes NOT in audited array:', notInAudited);

const inAuditedNotExplicit = auditedPaths.filter(p => !nonRedirectRoutes.includes(p));
console.log('Audited paths not in explicit routes:', inAuditedNotExplicit);
