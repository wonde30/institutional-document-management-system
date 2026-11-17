const path = require('path');
const axios = require(path.resolve(__dirname, '../client/node_modules/axios'));

async function testApiAuth() {
  const client = axios.create({
    baseURL: 'http://localhost:8001',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'Origin': 'http://localhost:8001',
      'Referer': 'http://localhost:8001/'
    }
  });

  const endpoints = [
    { method: 'get', path: '/api/v1/auth/me', requiredPerm: 'Authenticated Session', desc: 'Current user facts' },
    { method: 'get', path: '/api/v1/documents', requiredPerm: 'documents.view', desc: 'Document repository list' },
    { method: 'get', path: '/api/v1/correspondences', requiredPerm: 'correspondence.view', desc: 'Correspondence registry list' },
    { method: 'get', path: '/api/v1/archive/entries', requiredPerm: 'archive.view', desc: 'Digital archive entries' },
    { method: 'get', path: '/api/v1/reports/summary', requiredPerm: 'reports.view', desc: 'Operational reports summary' },
    { method: 'get', path: '/api/v1/users', requiredPerm: 'users.view', desc: 'Staff users list' },
    { method: 'get', path: '/api/v1/roles', requiredPerm: 'roles.view', desc: 'Roles and permissions list' },
    { method: 'get', path: '/api/v1/organization-units', requiredPerm: 'organization.view', desc: 'Organization units directory' },
    { method: 'get', path: '/api/v1/permissions', requiredPerm: 'permissions.view', desc: 'Permission catalog' },
    { method: 'get', path: '/api/v1/settings', requiredPerm: 'system.configure', desc: 'Institutional system settings' },
    { method: 'get', path: '/api/v1/admin/workflows', requiredPerm: 'system.configure', desc: 'Workflow definitions builder' },
    { method: 'post', path: '/api/v1/documents', requiredPerm: 'documents.create', desc: 'Create document', payload: { title: 'Unauthorized Test' } },
    { method: 'post', path: '/api/v1/correspondences', requiredPerm: 'correspondence.create', desc: 'Register correspondence', payload: { subject: 'Unauthorized Test' } },
    { method: 'post', path: '/api/v1/users', requiredPerm: 'users.create', desc: 'Create user account', payload: { name: 'Test', email: 'test@wu.edu.et' } },
    { method: 'put', path: '/api/v1/settings', requiredPerm: 'system.configure', desc: 'Update system settings', payload: { institution_name: 'Compromised' } },
    { method: 'post', path: '/api/v1/admin/workflows', requiredPerm: 'system.configure', desc: 'Create workflow definition', payload: { title: 'Test WF' } }
  ];

  console.log('=== PART 1: UNAUTHENTICATED DIRECT API REQUESTS (16 ENDPOINTS) ===');
  const unauthResults = [];
  for (let ep of endpoints) {
    try {
      const res = await client.request({
        method: ep.method,
        url: ep.path,
        data: ep.payload
      });
      unauthResults.push({
        method: ep.method.toUpperCase(),
        path: ep.path,
        status: res.status,
        expected: 'HTTP 401',
        desc: ep.desc,
        result: 'UNEXPECTED_SUCCESS'
      });
      console.log(`[UNAUTH] ${ep.method.toUpperCase().padEnd(4)} ${ep.path.padEnd(30)} -> HTTP ${res.status} [UNEXPECTED SUCCESS]`);
    } catch (e) {
      const status = e.response ? e.response.status : 'ERR';
      unauthResults.push({
        method: ep.method.toUpperCase(),
        path: ep.path,
        status,
        expected: 'HTTP 401',
        desc: ep.desc,
        result: status === 401 ? 'PASS_401' : 'NON_401'
      });
      console.log(`[UNAUTH] ${ep.method.toUpperCase().padEnd(4)} ${ep.path.padEnd(30)} -> HTTP ${status} (${ep.desc})`);
    }
  }

  console.log('\n=== PART 2: AUTHENTICATING AS PILOT READONLY (pilot.readonly@wu.edu.et) ===');
  const csrfRes = await client.get('/sanctum/csrf-cookie');
  const cookies = (csrfRes.headers['set-cookie'] || []).map(c => c.split(';')[0]);
  const xsrfCookie = cookies.find(x => x.startsWith('XSRF-TOKEN='));
  const xsrfToken = decodeURIComponent(xsrfCookie.split('=')[1]);
  const cookieHeader = cookies.join('; ');

  const loginRes = await client.post('/api/v1/auth/login', {
    email: 'pilot.readonly@wu.edu.et',
    password: 'PilotPassword2026!'
  }, {
    headers: {
      'Cookie': cookieHeader,
      'X-XSRF-TOKEN': xsrfToken
    }
  });

  console.log(`Login status: ${loginRes.status}`);
  const user = loginRes.data.user;
  console.log('User:', user.email);
  console.log('Granted Permissions:', user.permissions);

  let sessionCookies = cookieHeader;
  if (loginRes.headers['set-cookie']) {
    const newCookies = loginRes.headers['set-cookie'].map(x => x.split(';')[0]);
    sessionCookies = newCookies.join('; ');
  }

  console.log('\n=== PART 3: AUTHENTICATED PILOT READONLY PERMISSION EVALUATION ===');
  const authResults = [];
  for (let ep of endpoints) {
    const hasPerm = user.permissions.includes(ep.requiredPerm) || ep.requiredPerm === 'Authenticated Session';
    const expectedStatus = hasPerm ? 'HTTP 200' : 'HTTP 403';
    
    try {
      const res = await client.request({
        method: ep.method,
        url: ep.path,
        data: ep.payload,
        headers: {
          'Cookie': sessionCookies,
          'X-XSRF-TOKEN': xsrfToken
        }
      });
      const verdict = hasPerm ? 'AUTHORIZED_ACCESS_PASS' : 'SECURITY_VIOLATION_UNEXPECTED_200';
      authResults.push({
        method: ep.method.toUpperCase(),
        path: ep.path,
        status: res.status,
        hasPerm,
        expectedStatus,
        requiredPerm: ep.requiredPerm,
        desc: ep.desc,
        verdict
      });
      console.log(`[AUTH] ${ep.method.toUpperCase().padEnd(4)} ${ep.path.padEnd(30)} -> HTTP ${res.status} [Expected: ${expectedStatus}] (HasPerm: ${hasPerm}) -> ${verdict}`);
    } catch (e) {
      const status = e.response ? e.response.status : 'ERR';
      let verdict = 'UNEXPECTED_ERROR';
      if (!hasPerm && status === 403) {
        verdict = 'CORRECTLY_DENIED_403';
      } else if (!hasPerm && status === 401) {
        verdict = 'UNEXPECTED_SESSION_LOSS_401';
      } else if (hasPerm && status >= 400) {
        verdict = 'UNEXPECTED_DENIAL';
      }
      authResults.push({
        method: ep.method.toUpperCase(),
        path: ep.path,
        status,
        hasPerm,
        expectedStatus,
        requiredPerm: ep.requiredPerm,
        desc: ep.desc,
        verdict
      });
      console.log(`[AUTH] ${ep.method.toUpperCase().padEnd(4)} ${ep.path.padEnd(30)} -> HTTP ${status} [Expected: ${expectedStatus}] (HasPerm: ${hasPerm}) -> ${verdict}`);
    }
  }

  console.log('\n=== SUMMARY METRICS ===');
  const unauth401Count = unauthResults.filter(r => r.status === 401).length;
  console.log(`Unauthenticated Endpoints returning HTTP 401: ${unauth401Count} of ${unauthResults.length}`);

  const authorized200Count = authResults.filter(r => r.hasPerm && r.status === 200).length;
  const expectedAuthorized = authResults.filter(r => r.hasPerm).length;
  console.log(`Authorized Endpoints returning HTTP 200: ${authorized200Count} of ${expectedAuthorized}`);

  const denied403Count = authResults.filter(r => !r.hasPerm && r.status === 403).length;
  const expectedDenied = authResults.filter(r => !r.hasPerm).length;
  console.log(`Unauthorized Mutating/Admin Endpoints returning HTTP 403: ${denied403Count} of ${expectedDenied}`);
}

testApiAuth();
