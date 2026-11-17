/**
 * Capture screenshots for design QA
 * Pages: /dashboard, /documents, /documents/create, /admin/users
 * Viewports: 1440, 1024, 390
 * Themes: light, dark
 */

import { spawn } from 'child_process';
import fs from 'fs';
import path from 'path';
import os from 'os';

const CHROMIUM_BIN = 'C:\\Users\\IT3\\AppData\\Local\\ms-playwright\\chromium-1254\\chrome-win64\\chrome.exe';
const BASE_URL = 'http://localhost:5173';
const CDP_PORT = 9227;
const SCREENSHOT_DIR = path.resolve('artifacts/design-qa-screenshots');
const USER_DATA_DIR = path.join(os.tmpdir(), `wu_dms_design_qa_${Date.now()}`);

// Test credentials
const TEST_EMAIL = 'amr.abdelhamed@wu.edu.et';
const TEST_PASSWORD = 'AdminPassword2026!';

const PAGES = [
  { route: '/dashboard', name: 'dashboard' },
  { route: '/documents', name: 'documents-list' },
  { route: '/documents/create', name: 'documents-create' },
  { route: '/users', name: 'admin-users' }
];

const VIEWPORTS = [
  { width: 1440, height: 900, name: '1440' },
  { width: 1024, height: 768, name: '1024' },
  { width: 390, height: 844, name: '390' }
];

fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

console.log('=== Design QA Screenshot Capture ===');
console.log('Target:', BASE_URL);
console.log('Output:', SCREENSHOT_DIR);

const chromeProcess = spawn(CHROMIUM_BIN, [
  `--remote-debugging-port=${CDP_PORT}`,
  '--headless=new',
  '--disable-gpu',
  '--no-sandbox',
  '--window-size=1440,900',
  `--user-data-dir=${USER_DATA_DIR}`,
  'about:blank'
]);

chromeProcess.stderr.on('data', () => {});

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function initCDP() {
  await sleep(1000);
  const response = await fetch(`http://localhost:${CDP_PORT}/json/version`);
  const { webSocketDebuggerUrl } = await response.json();
  
  // Use Node.js built-in WebSocket (available in Node 22+)
  const ws = new WebSocket(webSocketDebuggerUrl);
  
  return new Promise((resolve, reject) => {
    let msgId = 0;
    const pending = new Map();
    
    ws.addEventListener('open', () => resolve({ ws, send: (method, params) => {
      const id = ++msgId;
      ws.send(JSON.stringify({ id, method, params }));
      return new Promise((res, rej) => {
        pending.set(id, { resolve: res, reject: rej });
        setTimeout(() => rej(new Error('CDP timeout')), 30000);
      });
    }}));
    
    ws.addEventListener('message', (event) => {
      const msg = JSON.parse(event.data);
      if (msg.id && pending.has(msg.id)) {
        const { resolve, reject } = pending.get(msg.id);
        pending.delete(msg.id);
        if (msg.error) reject(new Error(msg.error.message));
        else resolve(msg.result);
      }
    });
    
    ws.addEventListener('error', reject);
  });
}

async function captureScreenshots() {
  const { send } = await initCDP();
  
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Network.enable');
  
  // Navigate to login page
  console.log('Navigating to login...');
  await send('Page.navigate', { url: `${BASE_URL}/login` });
  await sleep(2000);
  
  // Login
  console.log('Logging in...');
  await send('Runtime.evaluate', {
    expression: `
      document.querySelector('input[type="email"]').value = '${TEST_EMAIL}';
      document.querySelector('input[type="password"]').value = '${TEST_PASSWORD}';
      document.querySelector('button[type="submit"]').click();
    `
  });
  await sleep(3000);
  
  // Capture screenshots for each page, viewport, and theme
  for (const page of PAGES) {
    console.log(`\nCapturing ${page.name}...`);
    
    await send('Page.navigate', { url: `${BASE_URL}${page.route}` });
    await sleep(2000);
    
    for (const theme of ['light', 'dark']) {
      console.log(`  Theme: ${theme}`);
      
      // Set theme
      await send('Runtime.evaluate', {
        expression: `localStorage.setItem('wu-theme-mode', '${theme}'); location.reload();`
      });
      await sleep(2000);
      
      for (const viewport of VIEWPORTS) {
        console.log(`    Viewport: ${viewport.name}px`);
        
        await send('Emulation.setDeviceMetricsOverride', {
          width: viewport.width,
          height: viewport.height,
          deviceScaleFactor: 1,
          mobile: viewport.width < 768
        });
        await sleep(500);
        
        const { data } = await send('Page.captureScreenshot', {
          format: 'png',
          captureBeyondViewport: false
        });
        
        const filename = `${page.name}_${viewport.name}_${theme}.png`;
        fs.writeFileSync(path.join(SCREENSHOT_DIR, filename), Buffer.from(data, 'base64'));
        console.log(`      ✓ ${filename}`);
      }
    }
  }
  
  console.log('\n=== Screenshot capture complete ===');
  console.log('Screenshots saved to:', SCREENSHOT_DIR);
  
  chromeProcess.kill();
  process.exit(0);
}

captureScreenshots().catch(err => {
  console.error('Error:', err);
  chromeProcess.kill();
  process.exit(1);
});
