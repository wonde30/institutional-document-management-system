const path = require('path');
const axios = require(path.resolve(__dirname, '../client/node_modules/axios'));

async function testAuthMutating() {
  const client = axios.create({
    baseURL: 'http://localhost:8001',
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'Origin': 'http://localhost:8001',
      'Referer': 'http://localhost:8001/'
    }
  });

  const csrfRes = await client.get('/sanctum/csrf-cookie');
  const cookies = (csrfRes.headers['set-cookie'] || []).map(c => c.split(';')[0]);
  const xsrfCookie = cookies.find(c => c.startsWith('XSRF-TOKEN='));
  let xsrfToken = decodeURIComponent(xsrfCookie.split('=')[1]);
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

  let sessionCookies = cookieHeader;
  if (loginRes.headers['set-cookie']) {
    const newCookies = loginRes.headers['set-cookie'].map(x => x.split(';')[0]);
    sessionCookies = newCookies.join('; ');
    const newXsrf = newCookies.find(x => x.startsWith('XSRF-TOKEN='));
    if (newXsrf) {
      xsrfToken = decodeURIComponent(newXsrf.split('=')[1]);
    }
  }

  console.log('Authenticated as pilot.readonly. Testing mutating requests with session cookies + XSRF-TOKEN:');

  const mutatingEndpoints = [
    { method: 'post', path: '/api/v1/documents', desc: 'Create document' },
    { method: 'post', path: '/api/v1/correspondences', desc: 'Create correspondence' },
    { method: 'post', path: '/api/v1/users', desc: 'Create user account' },
    { method: 'put', path: '/api/v1/settings', desc: 'Update system settings' },
    { method: 'post', path: '/api/v1/admin/workflows', desc: 'Create workflow definition' }
  ];

  for (let ep of mutatingEndpoints) {
    try {
      const res = await client.request({
        method: ep.method,
        url: ep.path,
        data: { title: 'Unauthorized Test' },
        headers: {
          'Cookie': sessionCookies,
          'X-XSRF-TOKEN': xsrfToken
        }
      });
      console.log(`[AUTH Mutating] ${ep.method.toUpperCase()} ${ep.path} -> HTTP ${res.status}`);
    } catch (e) {
      const status = e.response ? e.response.status : 'ERR';
      const msg = e.response && e.response.data ? e.response.data.message : '';
      console.log(`[AUTH Mutating] ${ep.method.toUpperCase().padEnd(4)} ${ep.path.padEnd(28)} -> HTTP ${status} (Message: ${msg})`);
    }
  }
}

testAuthMutating();
