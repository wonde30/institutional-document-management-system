const http = require('http');

const endpoints = [
  { method: 'GET', path: '/api/v1/users', desc: 'User list' },
  { method: 'GET', path: '/api/v1/roles', desc: 'Role list' },
  { method: 'GET', path: '/api/v1/organization-units', desc: 'Org units' },
  { method: 'GET', path: '/api/v1/permissions', desc: 'Permissions' },
  { method: 'GET', path: '/api/v1/settings', desc: 'System settings' },
  { method: 'GET', path: '/api/v1/admin/workflows', desc: 'Workflow definitions' },
  { method: 'GET', path: '/api/v1/documents', desc: 'Documents list' },
  { method: 'GET', path: '/api/v1/correspondence', desc: 'Correspondence list' },
  { method: 'GET', path: '/api/v1/archive', desc: 'Archive list' },
  { method: 'GET', path: '/api/v1/reports/summary', desc: 'Reports summary' },
  { method: 'POST', path: '/api/v1/documents', desc: 'Document creation' }
];

function request(options, data = null) {
  return new Promise((resolve, reject) => {
    const req = http.request(options, (res) => {
      let body = '';
      res.on('data', chunk => body += chunk);
      res.on('end', () => {
        let json = null;
        try { json = JSON.parse(body); } catch (_) {}
        resolve({ statusCode: res.statusCode, statusMessage: res.statusMessage, body: json || body, headers: res.headers });
      });
    });
    req.on('error', reject);
    if (data) {
      req.write(typeof data === 'string' ? data : JSON.stringify(data));
    }
    req.end();
  });
}

async function run() {
  console.log('=== 1. UNAUTHENTICATED DIRECT API REQUESTS ===');
  for (let ep of endpoints) {
    try {
      const res = await request({
        hostname: 'localhost',
        port: 8001,
        path: ep.path,
        method: ep.method,
        headers: { 'Accept': 'application/json' }
      });
      console.log(`[Unauth] ${ep.method.padEnd(5)} ${ep.path.padEnd(30)} -> HTTP ${res.statusCode} (${res.statusMessage}) - ${ep.desc}`);
    } catch (e) {
      console.log(`[Unauth] ${ep.method} ${ep.path} -> FAILED: ${e.message}`);
    }
  }

  console.log('\n=== 2. AUTHENTICATED PILOT.READONLY DIRECT API REQUESTS ===');
  // First login as pilot.readonly@wu.edu.et
  let token = null;
  let cookies = [];
  try {
    const loginRes = await request({
      hostname: 'localhost',
      port: 8001,
      path: '/api/v1/auth/login',
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    }, { email: 'pilot.readonly@wu.edu.et', password: 'PilotPassword2026!' });
    
    console.log(`Login response status: ${loginRes.statusCode}`);
    if (loginRes.headers['set-cookie']) {
      cookies = loginRes.headers['set-cookie'].map(c => c.split(';')[0]);
    }
    if (loginRes.body && loginRes.body.token) {
      token = loginRes.body.token;
    }
    console.log(`Token acquired: ${token ? 'YES' : 'NO'}, Cookies: ${cookies.length}`);
  } catch (e) {
    console.log(`Login error: ${e.message}`);
  }

  // Now test administrative/mutating endpoints with readonly account
  const restrictedEndpoints = [
    { method: 'GET', path: '/api/v1/users', desc: 'Read users list (admin only)' },
    { method: 'POST', path: '/api/v1/users', desc: 'Create user account', payload: { name: 'Test', email: 'test@wu.edu.et' } },
    { method: 'GET', path: '/api/v1/roles', desc: 'Read roles list' },
    { method: 'POST', path: '/api/v1/roles', desc: 'Create custom role', payload: { name: 'Test Role' } },
    { method: 'PUT', path: '/api/v1/settings', desc: 'Modify system settings', payload: { institution_name: 'Hacked' } },
    { method: 'POST', path: '/api/v1/admin/workflows', desc: 'Create workflow definition', payload: { title: 'Test WF' } },
    { method: 'POST', path: '/api/v1/documents', desc: 'Create document', payload: { title: 'Test Doc' } },
    { method: 'POST', path: '/api/v1/correspondence', desc: 'Create correspondence', payload: { subject: 'Test' } }
  ];

  for (let rep of restrictedEndpoints) {
    try {
      const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      };
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (cookies.length) headers['Cookie'] = cookies.join('; ');

      const res = await request({
        hostname: 'localhost',
        port: 8001,
        path: rep.path,
        method: rep.method,
        headers
      }, rep.payload);

      console.log(`[ReadOnly] ${rep.method.padEnd(5)} ${rep.path.padEnd(30)} -> HTTP ${res.statusCode} (${res.statusMessage}) - ${rep.desc}`);
      if (res.statusCode >= 400 && res.body && res.body.message) {
        console.log(`          Message: ${res.body.message}`);
      }
    } catch (e) {
      console.log(`[ReadOnly] ${rep.method} ${rep.path} -> FAILED: ${e.message}`);
    }
  }
}

run();
