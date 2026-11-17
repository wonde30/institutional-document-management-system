const path = require('path');
const axios = require(path.resolve(__dirname, '../client/node_modules/axios'));

async function testAuth() {
  const client = axios.create({
    baseURL: 'http://localhost:8000',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  });

  console.log('1. Fetching CSRF cookie...');
  const csrfRes = await client.get('/sanctum/csrf-cookie');
  const cookies = (csrfRes.headers['set-cookie'] || []).map(c => c.split(';')[0]);
  let xsrfToken = '';
  cookies.forEach(c => {
    if (c.startsWith('XSRF-TOKEN=')) {
      xsrfToken = decodeURIComponent(c.substring('XSRF-TOKEN='.length));
    }
  });

  console.log('CSRF Cookie acquired. XSRF-TOKEN present:', !!xsrfToken);

  console.log('2. Authenticating as pilot.readonly@wu.edu.et...');
  const loginRes = await client.post('/api/v1/auth/login', {
    email: 'pilot.readonly@wu.edu.et',
    password: 'PilotPassword2026!'
  }, {
    headers: {
      'Cookie': cookies.join('; '),
      'X-XSRF-TOKEN': xsrfToken
    }
  });

  console.log('Login Status:', loginRes.status);
  const loginCookies = (loginRes.headers['set-cookie'] || []).map(c => c.split(';')[0]);
  const allCookies = [...cookies, ...loginCookies];

  console.log('3. Testing Authenticated Read-Only API Authorization:');
  const testEndpoints = [
    { method: 'get', url: '/api/v1/auth/me', desc: 'Current user facts' },
    { method: 'get', url: '/api/v1/documents', desc: 'Document list (readonly observer has documents.view)' },
    { method: 'get', url: '/api/v1/users', desc: 'Admin users list (needs users.view)' },
    { method: 'get', url: '/api/v1/roles', desc: 'Admin roles list (needs roles.view)' },
    { method: 'post', url: '/api/v1/users', desc: 'Create user (needs users.create)', data: { name: 'X', email: 'x@wu.edu.et' } },
    { method: 'put', url: '/api/v1/settings', desc: 'Modify system settings (needs system.configure)', data: { institution_name: 'test' } },
    { method: 'post', url: '/api/v1/admin/workflows', desc: 'Create workflow definition (needs system.configure)', data: { title: 'Test WF' } },
    { method: 'post', url: '/api/v1/documents', desc: 'Create document (needs documents.create)', data: { title: 'Test Doc' } }
  ];

  for (let ep of testEndpoints) {
    try {
      const res = await client.request({
        method: ep.method,
        url: ep.url,
        data: ep.data,
        headers: {
          'Cookie': allCookies.join('; '),
          'X-XSRF-TOKEN': xsrfToken
        }
      });
      console.log(`[PASS: HTTP ${res.status}] ${ep.method.toUpperCase().padEnd(4)} ${ep.url.padEnd(26)} - ${ep.desc}`);
    } catch (e) {
      if (e.response) {
        console.log(`[DENIED: HTTP ${e.response.status}] ${ep.method.toUpperCase().padEnd(4)} ${ep.url.padEnd(26)} - ${ep.desc} (StatusText: ${e.response.statusText})`);
      } else {
        console.log(`[ERROR] ${ep.method.toUpperCase()} ${ep.url} -> ${e.message}`);
      }
    }
  }
}

testAuth();
