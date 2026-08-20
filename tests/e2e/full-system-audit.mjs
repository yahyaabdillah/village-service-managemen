import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const baseURL = process.env.APP_URL || 'http://127.0.0.1:8010';
const auditMode = process.env.AUDIT_MODE || 'all';
const runId = new Date().toISOString().replaceAll(':', '-').replace(/\.\d{3}Z$/, 'Z');
const outputDir = path.resolve('storage/app/private/full-system-audit', runId);
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const requestedViewport = process.env.AUDIT_VIEWPORT || null;
const viewports = [
    { name: 'mobile', width: 320, height: 720 },
    { name: 'tablet', width: 768, height: 900 },
    { name: 'laptop', width: 1024, height: 768 },
    { name: 'desktop', width: 1440, height: 900 },
].filter((viewport) => !requestedViewport || viewport.name === requestedViewport);
const flows = [];
const pages = [];

fs.mkdirSync(outputDir, { recursive: true });

function checkpoint() {
    fs.writeFileSync(path.join(outputDir, 'partial-report.json'), JSON.stringify({
        runId,
        baseURL,
        flows,
        pages,
    }, null, 2));
}

function findFirstPdf(directory) {
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const candidate = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            const nested = findFirstPdf(candidate);
            if (nested) return nested;
        } else if (entry.name.toLowerCase().endsWith('.pdf')) {
            return candidate;
        }
    }
    return null;
}

async function recordFlow(name, callback) {
    const startedAt = Date.now();
    try {
        const evidence = await callback();
        flows.push({ name, status: 'passed', durationMs: Date.now() - startedAt, evidence });
        checkpoint();
        return evidence;
    } catch (error) {
        flows.push({ name, status: 'failed', durationMs: Date.now() - startedAt, error: error.message });
        checkpoint();
        return null;
    }
}

async function login(page) {
    await page.goto(`${baseURL}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel('Email').fill('admin@desa.test');
    await page.getByLabel('Password').fill('password');
    await Promise.all([
        page.waitForURL(/\/admin\/?$/),
        page.getByRole('button', { name: /login|masuk/i }).click(),
    ]);
}

async function submitCurrentForm(page, resource) {
    const submit = page.locator('main form').last()
        .locator('[data-submit], button[type="submit"], button:not([type])').last();
    const lastStep = page.locator('.stepper-dot').last();
    if (await lastStep.count()) await lastStep.click();
    if (!await submit.count()) {
        const buttons = await page.locator('button').allTextContents();
        const heading = await page.locator('h1').first().textContent();
        throw new Error(`Tombol submit ${resource} tidak ditemukan di ${page.url()} (h1=${heading}; buttons=${buttons.join('|')})`);
    }
    await submit.click();
    await page.waitForTimeout(1200);
    if (new URL(page.url()).pathname !== `/admin/${resource}`) {
        const feedback = await page.locator('.errors.notice').allTextContents();
        throw new Error(`Submit ${resource} kembali ke form: ${feedback.join(' ') || 'tanpa pesan validasi'}`);
    }
}

async function setFormValues(page, values) {
    for (const [name, value] of Object.entries(values)) {
        const field = page.locator(`[name="${name}"]`);
        if (!await field.count()) continue;
        await field.evaluate((element, nextValue) => {
            element.value = String(nextValue);
            element.dispatchEvent(new Event('input', { bubbles: true }));
            element.dispatchEvent(new Event('change', { bubbles: true }));
        }, value);
    }
}

async function inspectPage(context, label, url, viewport) {
    const page = await context.newPage();
    const consoleMessages = [];
    const pageErrors = [];
    page.on('console', (message) => {
        if (['error', 'warning'].includes(message.type())) {
            consoleMessages.push(`${message.type()}: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) => pageErrors.push(error.message));

    try {
        const response = await page.goto(`${baseURL}${url}`, { waitUntil: 'load' });
        await page.waitForTimeout(label.includes('builder') ? 1200 : 200);
        const metrics = await page.evaluate(() => {
            const ids = [...document.querySelectorAll('[id]')].map((element) => element.id);
            const duplicateIds = [...new Set(ids.filter((id, index) => ids.indexOf(id) !== index))];
            const unnamedInteractive = [...document.querySelectorAll('button,a,input,select,textarea')]
                .filter((element) => {
                    if (element.matches('input[type="hidden"]')) return false;
                    return !(element.getAttribute('aria-label')
                        || element.getAttribute('title')
                        || element.labels?.[0]?.textContent?.trim()
                        || element.textContent?.trim()
                        || element.getAttribute('placeholder'));
                })
                .map((element) => element.outerHTML.slice(0, 180));
            const text = document.body.innerText;

            return {
                title: document.title,
                h1Count: document.querySelectorAll('h1').length,
                headings: [...document.querySelectorAll('h1,h2,h3')].map((heading) => ({
                    level: heading.tagName,
                    text: heading.textContent.trim(),
                })),
                horizontalOverflow: Math.max(
                    document.documentElement.scrollWidth,
                    document.body.scrollWidth,
                ) > document.documentElement.clientWidth,
                duplicateIds,
                unnamedInteractive,
                brokenImages: [...document.images]
                    .filter((image) => !image.complete || image.naturalWidth === 0)
                    .map((image) => image.src),
                mojibake: [...new Set(text.match(/(?:Â|Ã|â€”|â€|ðŸ|�)[^\n]{0,60}/g) || [])],
            };
        });
        const fileName = `${String(pages.length + 1).padStart(3, '0')}-${label}-${viewport.name}.png`;
        await page.screenshot({ path: path.join(outputDir, fileName), fullPage: true });
        pages.push({
            label,
            url,
            viewport: viewport.name,
            screenshot: fileName,
            status: response?.status() ?? null,
            consoleMessages,
            pageErrors,
            ...metrics,
        });
        checkpoint();
    } catch (error) {
        pages.push({
            label,
            url,
            viewport: viewport.name,
            status: null,
            consoleMessages,
            pageErrors,
            auditError: error.message,
        });
        checkpoint();
    } finally {
        await page.close();
    }
}

const launchOptions = fs.existsSync(chromePath)
    ? { executablePath: chromePath, headless: true }
    : { headless: true };
const browser = await chromium.launch(launchOptions);
const flowContext = await browser.newContext({ viewport: { width: 1440, height: 900 }, acceptDownloads: true });
const flowPage = await flowContext.newPage();
const templatePdf = findFirstPdf(path.resolve('storage/app/private/document-templates'));
let requestCode = null;
let requestDetailPath = process.env.AUDIT_REQUEST_DETAIL_PATH || null;

if (auditMode !== 'pages') {
await recordFlow('authentication-guard-invalid-login-login-logout', async () => {
    await flowPage.goto(`${baseURL}/admin`, { waitUntil: 'networkidle' });
    if (!/\/login$/.test(flowPage.url())) throw new Error('Admin route tidak mengarahkan tamu ke login.');

    await flowPage.getByLabel('Email').fill('admin@desa.test');
    await flowPage.getByLabel('Password').fill('salah-password');
    await Promise.all([
        flowPage.waitForLoadState('networkidle'),
        flowPage.getByRole('button', { name: /login|masuk/i }).click(),
    ]);
    if (!await flowPage.locator('.errors.notice').getByText('Email atau password salah.').isVisible()) {
        throw new Error('Login invalid tidak menampilkan feedback.');
    }

    await login(flowPage);
    const title = await flowPage.locator('h1').first().textContent();
    return { landingTitle: title?.trim() };
});

await recordFlow('citizen-submit-request-success-and-status-check', async () => {
    if (!templatePdf) throw new Error('Tidak ada PDF valid untuk berkas persyaratan E2E.');
    await flowPage.goto(`${baseURL}/pengajuan/surat-keterangan-domisili`, { waitUntil: 'networkidle' });
    await flowPage.locator('#applicant-nik').fill('3201019999999001');
    await flowPage.locator('#applicant-name').fill('Warga Audit E2E');
    await flowPage.locator('#phone-local').fill('081299990001');
    await flowPage.getByRole('button', { name: 'Lanjut' }).click();
    await flowPage.locator('#applicant-address').fill('Jl. Audit Sistem No. 1');
    await flowPage.locator('#hamlet').fill('Dusun QA');
    await flowPage.locator('#rt').fill('007');
    await flowPage.locator('#rw').fill('008');
    await flowPage.getByRole('button', { name: 'Lanjut' }).click();
    await flowPage.locator('#field-keperluan').fill('Audit end-to-end sistem layanan desa');
    await flowPage.getByRole('button', { name: 'Lanjut' }).click();
    await flowPage.locator('input[type="file"][name^="requirements"]').setInputFiles(templatePdf);
    await Promise.all([
        flowPage.waitForURL(/\/pengajuan-sukses\//),
        flowPage.getByRole('button', { name: 'Submit Pengajuan' }).click(),
    ]);
    requestCode = (await flowPage.locator('body').innerText()).match(/REQ-\d{8}-[A-Z0-9]{6}/)?.[0] || null;
    if (!requestCode) throw new Error('Kode pengajuan tidak ditemukan pada halaman sukses.');

    await flowPage.goto(`${baseURL}/cek-status`, { waitUntil: 'networkidle' });
    await flowPage.locator('#request-code').fill(requestCode);
    await flowPage.locator('#status-nik').fill('3201019999999001');
    await Promise.all([
        flowPage.waitForLoadState('networkidle'),
        flowPage.getByRole('button', { name: 'Cek Status' }).click(),
    ]);
    if (!await flowPage.getByRole('heading', { name: 'Status Pengajuan' }).isVisible()) {
        throw new Error('Status pengajuan yang baru dibuat tidak ditemukan.');
    }
    return { requestCode };
});

await recordFlow('admin-filter-verify-publish-and-citizen-download', async () => {
    if (!requestCode) throw new Error('Flow pengajuan warga sebelumnya gagal.');
    await login(flowPage);
    await flowPage.goto(`${baseURL}/admin/service-requests?q=${requestCode}`, { waitUntil: 'networkidle' });
    const detailLink = flowPage.getByRole('link', { name: requestCode });
    requestDetailPath = new URL(await detailLink.getAttribute('href')).pathname;
    await detailLink.click();
    await flowPage.waitForLoadState('networkidle');
    await Promise.all([
        flowPage.waitForLoadState('networkidle'),
        flowPage.getByRole('button', { name: 'Verifikasi Berkas' }).click(),
    ]);
    await flowPage.locator('#letter-number').fill('470/E2E/001/2026');
    await Promise.all([
        flowPage.waitForLoadState('networkidle'),
        flowPage.getByRole('button', { name: 'Setujui & Terbitkan' }).click(),
    ]);
    if (!await flowPage.getByText('Dokumen final berhasil').count()
        && !await flowPage.getByText('Dokumen siap').count()
        && !await flowPage.getByText('Selesai').count()) {
        throw new Error('Pengajuan tidak tampak selesai setelah publish.');
    }

    await flowPage.goto(`${baseURL}/cek-status`, { waitUntil: 'networkidle' });
    await flowPage.locator('#request-code').fill(requestCode);
    await flowPage.locator('#status-nik').fill('3201019999999001');
    await Promise.all([
        flowPage.waitForLoadState('networkidle'),
        flowPage.getByRole('button', { name: 'Cek Status' }).click(),
    ]);
    const downloadButton = flowPage.getByRole('button', { name: 'Unduh Dokumen' });
    if (!await downloadButton.isVisible()) throw new Error('Dokumen selesai tidak tersedia bagi warga.');
    const [download] = await Promise.all([flowPage.waitForEvent('download'), downloadButton.click()]);
    return { requestCode, downloadName: download.suggestedFilename() };
});

await recordFlow('resident-import-preview-confirm-export-template', async () => {
    await login(flowPage);
    await flowPage.goto(`${baseURL}/admin/residents`, { waitUntil: 'networkidle' });
    const csv = [
        'nik,name,gender,birth_place,birth_date,address,hamlet,rt,rw,religion,marital_status,occupation,phone,is_active',
        '3201019999999002,Penduduk Audit,male,Solo,1992-02-02,Jl Audit,Dusun QA,007,008,Islam,Kawin,Tester,081299990002,1',
    ].join('\n');
    await flowPage.locator('input[type="file"][name="csv"]').setInputFiles({
        name: 'penduduk-audit.csv',
        mimeType: 'text/csv',
        buffer: Buffer.from(csv),
    });
    await Promise.all([
        flowPage.waitForLoadState('networkidle'),
        flowPage.getByRole('button', { name: 'Validasi File' }).click(),
    ]);
    const importButton = flowPage.getByRole('button', { name: 'Import 1 Baris' });
    if (!await importButton.isVisible()) throw new Error('File valid tidak menghasilkan tombol konfirmasi import.');
    await Promise.all([flowPage.waitForLoadState('networkidle'), importButton.click()]);
    if (!await flowPage.getByText(/Import penduduk selesai/i).count()) {
        throw new Error('Import penduduk tidak memberi konfirmasi selesai.');
    }
    const exportResponse = await flowPage.request.get(`${baseURL}/admin/residents/export`);
    const templateResponse = await flowPage.request.get(`${baseURL}/admin/residents/template`);
    if (!exportResponse.ok() || !templateResponse.ok()) throw new Error('Export atau template penduduk gagal diunduh.');
    return {
        exportStatus: exportResponse.status(),
        templateStatus: templateResponse.status(),
        fileInputCount: await flowPage.locator('input[type="file"][name="csv"]').count(),
    };
});
}

const crudCases = [
    ['village-profiles', 'Desa Audit E2E', { village_name: 'Desa Audit E2E', district: 'Kecamatan QA', phone: '+6281299990003', is_active: '0' }, 'village_name', 'Desa Audit E2E Updated'],
    ['family-cards', '3201019999999003', { family_card_number: '3201019999999003', head_of_family_name: 'Kepala Audit', address: 'Jl. Audit KK' }, 'head_of_family_name', 'Kepala Audit Updated'],
    ['residents', '3201019999999004', { family_card_id: '1', nik: '3201019999999004', name: 'Resident CRUD Audit', gender: 'male', address: 'Jl. Audit Resident', phone: '+6281299990004', is_active: '1' }, 'name', 'Resident CRUD Updated'],
    ['service-types', 'Layanan Audit E2E', { name: 'Layanan Audit E2E', slug: 'layanan-audit-e2e', description: 'Layanan sementara untuk audit', is_active: '1', sort_order: '99' }, 'name', 'Layanan Audit Updated'],
    ['service-requirements', 'Syarat Audit E2E', { service_type_id: '1', name: 'Syarat Audit E2E', description: 'Syarat sementara', is_required: '0', allowed_file_types: 'pdf,jpg', max_file_size_kb: '1024', sort_order: '99' }, 'name', 'Syarat Audit Updated'],
    ['service-type-fields', 'Field Audit E2E', { service_type_id: '1', label: 'Field Audit E2E', field_key: 'field_audit_e2e', field_type: 'text', is_required: '0', sort_order: '99' }, 'label', 'Field Audit Updated'],
    ['announcements', 'Pengumuman Audit E2E', { title: 'Pengumuman Audit E2E', slug: 'pengumuman-audit-e2e', content: 'Konten pengumuman audit.', is_published: '0' }, 'title', 'Pengumuman Audit Updated'],
    ['users', 'audit-user@desa.test', { name: 'User Audit E2E', email: 'audit-user@desa.test', password: 'password123', phone: '+6281299990005', is_active: '1', role: 'Petugas' }, 'name', 'User Audit Updated'],
    ['roles', 'Role Audit E2E', { name: 'Role Audit E2E', guard_name: 'web' }, 'name', 'Role Audit Updated'],
];

if (auditMode !== 'pages') {
    for (const [resource, marker, createValues, editField, updatedMarker] of crudCases) {
        await recordFlow(`admin-crud-${resource}-create-edit-delete`, async () => {
        await login(flowPage);
        await flowPage.goto(`${baseURL}/admin/${resource}/create`, { waitUntil: 'networkidle' });
        await setFormValues(flowPage, createValues);
        await submitCurrentForm(flowPage, resource);
        if (!await flowPage.getByText(marker, { exact: false }).count()) throw new Error(`Data ${resource} tidak tampil setelah create.`);

        const row = flowPage.locator('tbody tr').filter({ hasText: marker }).first();
        const editUrl = await row.getByRole('link', { name: 'Edit' }).getAttribute('href');
        const editResponse = await flowPage.goto(editUrl, { waitUntil: 'load' });
        let editFailure = null;
        let cleanupMarker = marker;
        if (!editResponse?.ok()) {
            editFailure = `Halaman edit ${resource} mengembalikan HTTP ${editResponse?.status() ?? 'unknown'}.`;
        } else {
            await setFormValues(flowPage, { [editField]: updatedMarker });
            await submitCurrentForm(flowPage, resource);
            if (!await flowPage.getByText(updatedMarker, { exact: false }).count()) {
                editFailure = `Data ${resource} tidak tampil setelah edit.`;
            } else {
                cleanupMarker = updatedMarker;
            }
        }

        await flowPage.goto(`${baseURL}/admin/${resource}`, { waitUntil: 'load' });
        const cleanupRow = flowPage.locator('tbody tr').filter({ hasText: cleanupMarker }).first();
        await cleanupRow.getByRole('button', { name: 'Delete' }).click();
        await flowPage.waitForTimeout(1200);
        if (await flowPage.getByText(cleanupMarker, { exact: false }).count()) throw new Error(`Data ${resource} masih tampil setelah delete.`);
        if (editFailure) throw new Error(`${editFailure} Create dan delete berhasil; cleanup selesai.`);
        return { created: marker, updated: updatedMarker, deleted: true };
        });
    }
}

if (auditMode !== 'pages') {
await recordFlow('optional-phone-field-can-remain-empty', async () => {
    await login(flowPage);
    await flowPage.goto(`${baseURL}/admin/village-profiles/create`, { waitUntil: 'networkidle' });
    await setFormValues(flowPage, {
        village_name: 'Desa Tanpa Nomor Telepon',
        is_active: '0',
    });
    await flowPage.locator('.stepper-dot').last().click();
    await flowPage.locator('main form').last()
        .locator('[data-submit], button[type="submit"], button:not([type])').last().click();
    await flowPage.waitForTimeout(1200);
    if (!await flowPage.getByText('Desa Tanpa Nomor Telepon', { exact: false }).count()) {
        throw new Error('Field telepon berstatus nullable, tetapi form kosong dikirim sebagai +62 dan ditolak validasi.');
    }
    return { nullablePhoneAccepted: true };
});

await recordFlow('document-builder-add-inspect-delete', async () => {
    await login(flowPage);
    await flowPage.goto(`${baseURL}/admin/document-templates/1/builder`, { waitUntil: 'networkidle' });
    await flowPage.locator('[data-pdf-canvas]').waitFor({ state: 'visible' });
    await flowPage.waitForFunction(() => document.querySelectorAll('.canvas-field').length > 0);
    const before = await flowPage.locator('.canvas-field').count();
    await flowPage.getByRole('button', { name: /Nama Pemohon applicant_name/i }).click();
    await flowPage.waitForFunction((count) => document.querySelectorAll('.canvas-field').length === count + 1, before);
    const inspectorActive = await flowPage.locator('[data-property-form]').evaluate((element) => element.classList.contains('active'));
    flowPage.once('dialog', (dialog) => dialog.accept());
    await flowPage.getByRole('button', { name: 'Hapus field' }).click();
    await flowPage.waitForFunction((count) => document.querySelectorAll('.canvas-field').length === count, before);
    return { before, after: await flowPage.locator('.canvas-field').count(), inspectorActive };
});
}

const publicPages = [
    ['home', '/'],
    ['services', '/layanan'],
    ['status-check', '/cek-status'],
    ['login', '/login'],
    ...[
        'surat-keterangan-domisili',
        'surat-keterangan-usaha',
        'surat-keterangan-tidak-mampu',
        'surat-pengantar-ktp-kk',
        'pengaduan-masyarakat',
    ].flatMap((slug) => [
        [`service-${slug}`, `/layanan/${slug}`],
        [`request-${slug}`, `/pengajuan/${slug}`],
    ]),
];
const resources = crudCases.map(([resource]) => resource);
const adminPages = [
    ['admin-dashboard', '/admin'],
    ['admin-requests', '/admin/service-requests'],
    ...(requestDetailPath ? [['admin-request-detail', requestDetailPath]] : []),
    ...resources.flatMap((resource) => [
        [`admin-${resource}`, `/admin/${resource}`],
        [`admin-${resource}-create`, `/admin/${resource}/create`],
    ]),
    ['admin-document-templates', '/admin/document-templates'],
    ['admin-document-template-create', '/admin/document-templates/create'],
    ['admin-document-template-builder', '/admin/document-templates/1/builder'],
    ['admin-activity-logs', '/admin/activity-logs'],
    ['admin-security-logs', '/admin/security-logs'],
    ['admin-notification-logs', '/admin/notification-logs'],
    ['admin-whatsapp', '/admin/whatsapp'],
];

if (auditMode !== 'flows') {
    for (const viewport of viewports) {
        const publicContext = await browser.newContext({ viewport });
        for (const [label, url] of publicPages) {
            await inspectPage(publicContext, label, url, viewport);
        }
        await publicContext.close();

        const adminContext = await browser.newContext({ viewport });
        const loginPage = await adminContext.newPage();
        await login(loginPage);
        await loginPage.close();
        for (const [label, url] of adminPages) {
            await inspectPage(adminContext, label, url, viewport);
        }
        await adminContext.close();
    }
}

await flowContext.close();
await browser.close();

const pageFailures = pages.filter((item) =>
    item.status !== 200
    || item.auditError
    || item.horizontalOverflow
    || item.duplicateIds?.length
    || item.unnamedInteractive?.length
    || item.brokenImages?.length
    || item.consoleMessages?.length
    || item.pageErrors?.length
    || item.mojibake?.length
    || item.h1Count !== 1,
);
const report = {
    runId,
    baseURL,
    summary: {
        flowsChecked: flows.length,
        flowsPassed: flows.filter((item) => item.status === 'passed').length,
        flowsFailed: flows.filter((item) => item.status === 'failed').length,
        pagesChecked: pages.length,
        pagesFlagged: pageFailures.length,
        screenshots: pages.filter((item) => item.screenshot).length,
    },
    flows,
    pageFailures,
    pages,
};

fs.writeFileSync(path.join(outputDir, 'report.json'), JSON.stringify(report, null, 2));
fs.writeFileSync(path.join(outputDir, 'summary.json'), JSON.stringify({
    ...report.summary,
    report: path.join(outputDir, 'report.json'),
}, null, 2));
console.log(JSON.stringify({ ...report.summary, outputDir }, null, 2));

if (report.summary.flowsFailed > 0) process.exitCode = 1;
