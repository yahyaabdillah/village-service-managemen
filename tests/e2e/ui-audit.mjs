import fs from 'fs';
import path from 'path';
import { chromium } from 'playwright';

const baseURL = process.env.APP_URL || 'http://127.0.0.1:8000';
const outputDir = path.resolve('storage/app/private/ui-audit');
const chromePath =
  process.env.CHROME_PATH ||
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const viewports = [
  { name: 'mobile', width: 320, height: 720 },
  { name: 'tablet', width: 768, height: 900 },
  { name: 'laptop', width: 1024, height: 768 },
  { name: 'desktop', width: 1440, height: 900 },
];

fs.mkdirSync(outputDir, { recursive: true });

const browser = await chromium.launch({
  executablePath: chromePath,
  headless: true,
});
const report = [];
const interactions = [];

async function inspect(page, label, url, viewport, screenshot = false) {
  const consoleMessages = [];
  const pageErrors = [];
  page.on('console', (message) => {
    if (['error', 'warning'].includes(message.type())) {
      consoleMessages.push(`${message.type()}: ${message.text()}`);
    }
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));

  const response = await page.goto(`${baseURL}${url}`, {
    waitUntil: 'networkidle',
  });
  const metrics = await page.evaluate(() => ({
    title: document.title,
    lang: document.documentElement.lang,
    viewportWidth: document.documentElement.clientWidth,
    documentWidth: document.documentElement.scrollWidth,
    bodyWidth: document.body.scrollWidth,
    headings: [...document.querySelectorAll('h1,h2,h3')].map((heading) => ({
      level: heading.tagName,
      text: heading.textContent.trim(),
    })),
    unnamedInteractive: [
      ...document.querySelectorAll('button,a,input,select,textarea'),
    ]
      .filter((element) => {
        if (element.matches('input[type="hidden"]')) return false;
        const label = element.labels?.[0]?.textContent?.trim();
        const name =
          element.getAttribute('aria-label') ||
          element.getAttribute('title') ||
          label ||
          element.textContent?.trim() ||
          element.getAttribute('placeholder');
        return !name;
      })
      .map((element) => element.outerHTML.slice(0, 160)),
    brokenImages: [...document.images]
      .filter((image) => !image.complete || image.naturalWidth === 0)
      .map((image) => image.src),
  }));

  if (screenshot) {
    await page.screenshot({
      path: path.join(outputDir, `${label}-${viewport.name}.png`),
      fullPage: true,
    });
  }

  report.push({
    label,
    url,
    viewport: viewport.name,
    status: response?.status(),
    ...metrics,
    horizontalOverflow:
      Math.max(metrics.documentWidth, metrics.bodyWidth) > metrics.viewportWidth,
    consoleMessages,
    pageErrors,
  });
}

for (const viewport of viewports) {
  const context = await browser.newContext({
    viewport: { width: viewport.width, height: viewport.height },
  });
  const page = await context.newPage();

  for (const [label, url] of [
    ['home', '/'],
    ['services', '/layanan'],
    ['status', '/cek-status'],
    ['login', '/login'],
    ['request', '/pengajuan/surat-keterangan-domisili'],
  ]) {
    await inspect(
      page,
      label,
      url,
      viewport,
      ['home', 'request'].includes(label),
    );
  }

  await page.goto(`${baseURL}/pengajuan/surat-keterangan-domisili`, {
    waitUntil: 'networkidle',
  });
  await page.locator('#phone-local').fill('0812abc345');
  const sanitizedPhone = await page.locator('#phone-local').inputValue();
  const combinedPhone = await page.locator('.phone-combined').inputValue();
  await page.getByRole('button', { name: 'Lanjut' }).click();
  const addressStepVisible = await page
    .locator('.step-panel.active')
    .getByRole('heading', { name: 'Alamat' })
    .isVisible();
  interactions.push({
    viewport: viewport.name,
    flow: 'request-stepper-and-phone',
    passed:
      sanitizedPhone === '812345' &&
      combinedPhone === '+62812345' &&
      addressStepVisible,
    sanitizedPhone,
    combinedPhone,
    addressStepVisible,
  });

  await page.goto(`${baseURL}/cek-status`, { waitUntil: 'networkidle' });
  await page.locator('#request-code').fill('REQ-TIDAK-ADA');
  await page.locator('#status-nik').fill('3201010101010001');
  await Promise.all([
    page.waitForLoadState('networkidle'),
    page.getByRole('button', { name: 'Cek Status' }).click(),
  ]);
  const notFoundVisible = await page
    .getByRole('heading', { name: 'Pengajuan tidak ditemukan' })
    .isVisible();
  interactions.push({
    viewport: viewport.name,
    flow: 'status-not-found',
    passed: notFoundVisible,
  });

  await page.goto(`${baseURL}/login`, { waitUntil: 'networkidle' });
  await page.getByLabel(/email/i).fill('admin@desa.test');
  await page.getByLabel(/password/i).fill('password');
  await Promise.all([
    page.waitForURL(/\/admin(?:\/)?$/),
    page.getByRole('button', { name: /login|masuk/i }).click(),
  ]);

  if (viewport.name === 'mobile') {
    await page.getByRole('button', { name: /menu/i }).click();
    const menuOpen = await page.locator('body').evaluate((body) =>
      body.classList.contains('menu-open'),
    );
    await page.getByRole('button', { name: 'Tutup menu navigasi' }).click();
    const menuClosed = await page.locator('body').evaluate(
      (body) => !body.classList.contains('menu-open'),
    );
    interactions.push({
      viewport: viewport.name,
      flow: 'admin-mobile-menu',
      passed: menuOpen && menuClosed,
      menuOpen,
      menuClosed,
    });
  }

  for (const [label, url] of [
    ['admin-dashboard', '/admin'],
    ['admin-requests', '/admin/service-requests'],
    ['admin-residents', '/admin/residents'],
    ['admin-whatsapp', '/admin/whatsapp'],
    ['admin-template-builder', '/admin/document-templates/1/builder'],
  ]) {
    await inspect(
      page,
      label,
      url,
      viewport,
      ['admin-dashboard', 'admin-whatsapp', 'admin-template-builder'].includes(label),
    );
  }

  if (viewport.name === 'desktop') {
    await page.goto(`${baseURL}/admin/document-templates/1/builder`, {
      waitUntil: 'networkidle',
    });
    await page.locator('[data-pdf-canvas]').waitFor({ state: 'visible' });
    await page.waitForFunction(
      () => document.querySelectorAll('.canvas-field').length > 0,
    );
    const beforeFields = await page.locator('.canvas-field').count();
    await page
      .getByRole('button', { name: /Teks Kustom custom_text/i })
      .click();
    await page.waitForFunction(
      (before) => document.querySelectorAll('.canvas-field').length === before + 1,
      beforeFields,
    );
    const afterAdd = await page.locator('.canvas-field').count();
    const inspectorVisible = await page
      .locator('[data-property-form]')
      .evaluate((element) => element.classList.contains('active'));
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Hapus field' }).click();
    await page.waitForFunction(
      (before) => document.querySelectorAll('.canvas-field').length === before,
      beforeFields,
    );
    const afterDelete = await page.locator('.canvas-field').count();
    interactions.push({
      viewport: viewport.name,
      flow: 'document-builder-add-inspect-delete',
      passed:
        afterAdd === beforeFields + 1 &&
        inspectorVisible &&
        afterDelete === beforeFields,
      beforeFields,
      afterAdd,
      inspectorVisible,
      afterDelete,
    });

    await page.goto(`${baseURL}/admin/whatsapp`, { waitUntil: 'networkidle' });
    await Promise.all([
      page.waitForLoadState('networkidle'),
      page
        .getByRole('button', { name: 'Mulai Pairing / Tampilkan QR' })
        .click(),
    ]);
    const bridgeFeedback = await page.getByText(
      /Bridge WhatsApp sudah berjalan|Bridge WhatsApp dijalankan/,
    );
    interactions.push({
      viewport: viewport.name,
      flow: 'admin-start-whatsapp-bridge',
      passed: await bridgeFeedback.isVisible(),
    });
  }

  await context.close();
}

await browser.close();
fs.writeFileSync(
  path.join(outputDir, 'report.json'),
  JSON.stringify(report, null, 2),
);

const failures = report.filter(
  (item) =>
    item.status !== 200 ||
    item.horizontalOverflow ||
    item.consoleMessages.length ||
    item.pageErrors.length ||
    item.unnamedInteractive.length ||
    item.brokenImages.length,
);
const interactionFailures = interactions.filter((item) => !item.passed);

console.log(
  JSON.stringify(
    {
      pagesChecked: report.length,
      failures: failures.length,
      details: failures,
      interactionsChecked: interactions.length,
      interactionFailures,
      report: path.join(outputDir, 'report.json'),
    },
    null,
    2,
  ),
);

if (
  failures.some((item) => item.status !== 200 || item.pageErrors.length) ||
  interactionFailures.length
) {
  process.exitCode = 1;
}
