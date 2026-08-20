import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const errors = [];
page.on('console', (message) => { if (message.type() === 'error') { errors.push(message.text()); console.error('BROWSER:', message.text()); } });
page.on('pageerror', (error) => { errors.push(error.message); console.error('PAGE:', error.message); });

await page.goto('http://localhost:8000/login');
await page.getByLabel('Email').fill('admin@desa.test');
await page.getByLabel('Password').fill('password');
await Promise.all([page.waitForURL('**/admin'), page.getByRole('button', { name: /login/i }).click()]);

await page.goto('http://localhost:8000/admin/document-templates');
await page.getByRole('heading', { name: 'Template Dokumen' }).waitFor();
const builderHref = await page.getByRole('link', { name: /Buka Builder/ }).first().getAttribute('href');
if (!builderHref) throw new Error('Builder link tidak ditemukan.');

await page.goto(builderHref);
await page.locator('#document-builder').waitFor();
await page.waitForTimeout(1500);
console.log('Builder runtime:', await page.locator('#document-builder').evaluate((root) => ({
    status: root.querySelector('[data-builder-status]')?.textContent.trim(),
    dialogSupported: typeof root.querySelector('[data-variable-dialog]')?.showModal === 'function',
    scripts: performance.getEntriesByType('resource').map((item) => item.name).filter((name) => name.includes('document-builder')),
})));
await page.locator('[data-open-variable]').click();
await page.locator('[data-variable-dialog][open]').waitFor();
await page.getByRole('heading', { name: 'Buat Variable Form' }).waitFor();
await page.getByRole('button', { name: 'Tutup' }).click();
await page.locator('[data-variable-dialog]').waitFor({ state: 'hidden' });
await page.locator('[data-mapping-mode]').waitFor({ state: 'attached' });
await page.locator('.canvas-field').first().click();
await page.locator('[data-property-form].active').waitFor();
await page.screenshot({ path: 'storage/app/browser-builder-desktop.png', fullPage: true });

await page.setViewportSize({ width: 390, height: 844 });
await page.reload();
await page.locator('#document-builder').waitFor();
await page.waitForTimeout(1500);
await page.screenshot({ path: 'storage/app/browser-builder-mobile.png', fullPage: true });

await page.setViewportSize({ width: 1440, height: 1000 });
await page.goto('http://localhost:8000/admin/service-requests/1');
await page.getByRole('heading', { name: /REQ-|Pengajuan/ }).first().waitFor();
await page.getByRole('heading', { name: 'Artefak Dokumen' }).waitFor();
const activeDownload = page.getByRole('link', { name: 'Unduh Dokumen Aktif' });
const downloadHref = await activeDownload.getAttribute('href');
if (!downloadHref) throw new Error('Link dokumen aktif request #1 tidak ditemukan.');
const downloadResponse = await page.request.get(downloadHref);
if (!downloadResponse.ok() || downloadResponse.headers()['content-type'] !== 'image/png') {
    throw new Error(`Download request #1 salah: ${downloadResponse.status()} ${downloadResponse.headers()['content-type']}`);
}
const downloadBody = await downloadResponse.body();
if (downloadBody.subarray(0, 8).toString('hex') !== '89504e470d0a1a0a') throw new Error('Body download request #1 bukan PNG valid.');
await page.screenshot({ path: 'storage/app/browser-request-1.png', fullPage: true });

if (errors.length) throw new Error(`Console errors: ${errors.join(' | ')}`);
console.log('Browser smoke passed: templates, builder desktop/mobile, request #1, console clean.');
await browser.close();
