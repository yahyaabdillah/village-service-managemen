import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1365, height: 768 }, deviceScaleFactor: 1 });
const page = await context.newPage();
const root = process.cwd();
const wireframeUrl = pathToFileURL(path.join(root, 'thesis-assets', 'wireframes.html')).href;

for (const view of ['home', 'request', 'status', 'dashboard']) {
  await page.goto(`${wireframeUrl}?view=${view}`);
  await page.locator('.wireframe.active').screenshot({ path: path.join(root, 'thesis-assets', 'wireframes', `wireframe-${view}.png`) });
}

const app = 'http://127.0.0.1:8010';
const capture = async (name, url) => {
  await page.goto(`${app}${url}`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(root, 'thesis-assets', 'screenshots', `${name}.png`), fullPage: false });
};

await capture('hasil-beranda', '/');
await capture('hasil-daftar-layanan', '/layanan');
await capture('hasil-form-pengajuan', '/pengajuan/surat-keterangan-domisili');
await capture('hasil-cek-status', '/cek-status');
await capture('hasil-login-admin', '/login');

await page.getByLabel('Email').fill('admin@desa.test');
await page.getByLabel('Password').fill('password');
await Promise.all([page.waitForURL(/\/admin\/?$/), page.getByRole('button', { name: /login|masuk/i }).click()]);

await capture('hasil-dashboard-admin', '/admin');
await capture('hasil-pengajuan-admin', '/admin/service-requests');
await capture('hasil-data-penduduk', '/admin/residents');
await capture('hasil-template-dokumen', '/admin/document-templates');

await browser.close();
