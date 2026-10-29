// Capture the real portal UI for the public manual without exposing record rows.
// Run against a local, demo-enabled Laravel server and Chrome remote debugging.
import fs from 'node:fs/promises';
import path from 'node:path';

const base = process.env.MANUAL_BASE_URL || 'http://127.0.0.1:8765';
const debugging = process.env.CHROME_DEBUG_URL || 'http://127.0.0.1:9223';
const tabs = await (await fetch(`${debugging}/json/list`)).json();
const page = tabs.find(tab => tab.type === 'page' && tab.url.startsWith(base));
if (!page) throw new Error('Open the local sign-in page in the debug browser first.');

const socket = new WebSocket(page.webSocketDebuggerUrl);
await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, { once: true });
    socket.addEventListener('error', reject, { once: true });
});
let serial = 0;
const pending = new Map();
socket.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (!message.id) return;
    const task = pending.get(message.id);
    if (!task) return;
    pending.delete(message.id);
    if (message.error) task.reject(new Error(message.error.message));
    else task.resolve(message.result);
});
const call = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++serial;
    pending.set(id, { resolve, reject });
    socket.send(JSON.stringify({ id, method, params }));
});
const evaluate = async expression => {
    const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(result.exceptionDetails.text);
    return result.result.value;
};
const pause = ms => new Promise(resolve => setTimeout(resolve, ms));
const navigate = async url => {
    await call('Page.navigate', { url });
    for (let attempt = 0; attempt < 40; attempt++) {
        await pause(250);
        if (await evaluate('document.readyState') === 'complete') break;
    }
    await pause(700);
};

if (process.argv.includes('--preview')) {
    const mobilePreview = process.argv.includes('--mobile');
    await call('Page.enable');
    await call('Runtime.enable');
    await call('Emulation.setDeviceMetricsOverride', { width: mobilePreview ? 390 : 1440, height: mobilePreview ? 844 : 900, deviceScaleFactor: 1, mobile: mobilePreview });
    await navigate(`${base}/manual`);
    const previewDir = path.resolve('storage/app');
    for (const [name, anchor] of [['top', null], ['modules', 'modules'], ['reports', 'reports']]) {
        if (anchor) {
            await evaluate(`(() => { document.documentElement.style.scrollBehavior = 'auto'; document.getElementById(${JSON.stringify(anchor)}).scrollIntoView({behavior:'instant'}); })()`);
            await pause(200);
        }
        const result = await call('Page.captureScreenshot', { format: 'jpeg', quality: 82, captureBeyondViewport: false });
        await fs.writeFile(path.join(previewDir, `manual-preview-${mobilePreview ? 'mobile-' : ''}${name}.jpg`), Buffer.from(result.data, 'base64'));
    }
    console.log(`Horizontal overflow: ${await evaluate('document.documentElement.scrollWidth > innerWidth')}`);
    socket.close();
    console.log('Saved manual previews to storage/app.');
    process.exit(0);
}

await call('Page.enable');
await call('Runtime.enable');
await call('Emulation.setDeviceMetricsOverride', { width: 1366, height: 768, deviceScaleFactor: 1, mobile: false });
await navigate(`${base}/login`);
if ((await evaluate('location.pathname')) === '/login') {
    const submitted = await evaluate(`(() => {
        const input = document.querySelector('form[action$="/login/demo"] input[value="superadmin"]');
        if (!input) return false;
        input.form.requestSubmit();
        return true;
    })()`);
    if (!submitted) throw new Error('The demo sign-in button is unavailable.');
    await pause(1500);
}
if (!(await evaluate('location.pathname')).startsWith('/dashboard')) {
    throw new Error('Demo sign-in did not reach the dashboard.');
}

const output = path.resolve('public/img/manual');
await fs.mkdir(output, { recursive: true });

const screens = [
    ['dashboard', '/dashboard'],
    ['balance', '/dashboard', `document.querySelector('#tab-balance')?.click()`],
    ['balance-detail', '/dashboard', `(async () => {
        document.querySelector('#tab-balance')?.click();
        await new Promise(resolve => setTimeout(resolve, 450));
        const issues = document.querySelector('#panel-balance input[type="checkbox"]');
        if (issues && !issues.checked) issues.click();
        await new Promise(resolve => setTimeout(resolve, 300));
        document.querySelector('#panel-balance .kp-dash-check-head:not(:disabled)')?.click();
        document.documentElement.style.scrollBehavior = 'auto';
        document.querySelector('#b-checks-h')?.scrollIntoView({behavior:'instant'});
    })()`],
    ['weekly-setup', '/dashboard', `openCropYearModal()`],
    ['crop-management', '/crop-weeks'],
    ['price-management', '/prices'],
    ['registry', '/prices/registry'],
    ['buy-quedan', '/buy-quedan'],
    ['buy-molasses', '/buy-molasses'],
    ['profiles', '/planter-profiles'],
    ['loans', '/loans'],
    ['cash-advances', '/cash-advances'],
    ['deductions', '/planter-deductions'],
    ['hauling', '/hauling-liquidity'],
    ['vouchers', '/print-vouchers'],
    ['account', '/account/password'],
    ['users', '/user-management'],
    ['audit', '/audit-logs'],
    ['system-panel', '/system/dashboard'],
];

// The manual page itself provides canonical links; this avoids guessing route URLs.
await navigate(`${base}/manual`);
const links = await evaluate(`Object.fromEntries([...document.querySelectorAll('#moduleGrid .module-card')].map(card => [card.id, card.querySelector('a.text-link')?.href || null]))`);
for (const screen of screens) {
    if (links[screen[0]]) screen[1] = links[screen[0]];
    else screen[1] = new URL(screen[1], base).href;
}
const reports = await evaluate(`[...document.querySelectorAll('.report-card')].map((card, index) => ({ id: 'report-' + String(index + 1).padStart(2, '0'), url: card.querySelector('a.text-link')?.href }))`);
for (const report of reports) if (report.url) screens.push([report.id, report.url]);
const onlyIndex = process.argv.indexOf('--only');
const onlyId = onlyIndex >= 0 ? process.argv[onlyIndex + 1] : null;

const clean = `(() => {
    const style = document.createElement('style');
    style.textContent = 'main table tbody, #main-content table tbody { visibility:hidden !important } main table, #main-content table { position:relative } main table:after, #main-content table:after { content:"Record rows hidden for privacy"; display:block; position:absolute; top:55px; left:15px; padding:8px 12px; background:#fff; color:#42604d; border:1px solid #cbd9cd; border-radius:6px; font:600 14px system-ui } .kp-kpi-value { filter:blur(8px) } #panel-overview { filter:blur(9px) } body.swal2-shown #main-content { filter:blur(9px) }';
    document.head.append(style);
    document.querySelectorAll('main input, main textarea, #main-content input, #main-content textarea').forEach(field => {
        if (field.type !== 'hidden' && field.type !== 'checkbox' && field.type !== 'radio') field.value = '';
    });
    const walker = document.createTreeWalker(document.querySelector('main') || document.body, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.parentElement?.closest('script,style')) continue;
        node.textContent = node.textContent.replace(/₱\\s*[\\d,.]+/g, '₱••••');
    }
    return document.title;
})()`;

const results = [];
for (const [id, url, action] of screens) {
    if (onlyId && id !== onlyId) continue;
    await navigate(url);
    const pathname = await evaluate('location.pathname');
    if (pathname === '/login' || pathname === '/system/auth/admin-login') {
        results.push({ id, status: 'sign-in redirect' });
        continue;
    }
    if (action) {
        await evaluate(action);
        await pause(600);
    }
    const title = await evaluate(clean);
    const shot = await call('Page.captureScreenshot', { format: 'jpeg', quality: 78, captureBeyondViewport: false });
    await fs.writeFile(path.join(output, `${id}.jpg`), Buffer.from(shot.data, 'base64'));
    results.push({ id, title, pathname });
}
socket.close();
console.log(results.map(result => `${result.id}: ${result.pathname || result.status}`).join('\n'));
