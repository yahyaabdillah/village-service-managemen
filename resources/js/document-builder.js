import interact from 'interactjs';
import * as pdfjsLib from 'pdfjs-dist';
import pdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;
const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
const round = (value) => Math.round(value * 100) / 100;
const escapeHtml = (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;');

export function initDocumentBuilder() {
    const root = document.getElementById('document-builder');
    if (!root) return;

    const readOnly = root.dataset.readOnly === '1';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const canvas = root.querySelector('[data-pdf-canvas]');
    const pageElement = root.querySelector('[data-pdf-page]');
    const layer = root.querySelector('[data-field-layer]');
    const emptyCanvas = root.querySelector('[data-canvas-empty]');
    const propertyForm = root.querySelector('[data-property-form]');
    const propertyEmpty = root.querySelector('[data-property-empty]');
    const status = root.querySelector('[data-builder-status]');
    const toast = root.querySelector('[data-builder-toast]');
    const dateKeys = JSON.parse(root.dataset.dateKeys || '[]');
    let fields = JSON.parse(root.querySelector('[data-builder-fields]').textContent);
    let variables = JSON.parse(root.querySelector('[data-builder-variables]').textContent);
    let selectedId = null;
    let pageNumber = 1;
    let pageCount = 1;
    let pdf = null;
    let zoom = 1;
    let renderTask = null;
    let saveTimer = null;
    let pageWidthPt = 595.28; // updated from the PDF so canvas text matches print size

    const setStatus = (message, state = 'saved') => {
        if (!status) return;
        status.querySelector('span:last-child').textContent = message;
        const dot = status.querySelector('.system-dot');
        dot.classList.toggle('saving', state === 'saving');
        dot.classList.toggle('error', state === 'error');
    };
    const showToast = (message, isError = false) => {
        toast.textContent = message;
        toast.classList.toggle('error', isError);
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 3200);
    };
    const request = async (url, method, payload) => {
        const response = await fetch(url, {
            method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: payload ? JSON.stringify(payload) : undefined,
        });
        if (!response.ok) {
            const error = await response.json().catch(() => ({}));
            const first = Object.values(error.errors || {}).flat()[0];
            const failure = new Error(first || error.message || 'Perubahan gagal disimpan.');
            failure.fields = error.errors || {};
            throw failure;
        }
        return response.status === 204 ? null : response.json();
    };
    const variableByKey = (key) => variables.find((item) => item.key === key);
    const selectedField = () => fields.find((field) => String(field.id) === String(selectedId));
    const mappingFor = (field) => field.mapping_config ||= { version: 1, mode: 'source', key: field.variable_key };
    // What the box shows on the canvas: the example value of the data it prints.
    const displayText = (field) => {
        const mapping = mappingFor(field);
        const sample = (key) => variableByKey(key)?.sample || variableByKey(key)?.label || key;
        if (mapping.mode === 'literal') return mapping.value || 'Teks tetap';
        if (mapping.mode === 'segments') return (mapping.segments || []).map((segment) => segment.type === 'literal' ? segment.value || '' : sample(segment.key)).join('') || 'Gabungan';
        return `${mapping.prefix || ''}${sample(mapping.key || field.variable_key)}${mapping.suffix || ''}`;
    };
    const payloadFor = (field) => ({
        label: field.label,
        variable_key: field.variable_key,
        mapping_config: mappingFor(field),
        page_number: field.page_number,
        x_position: round(field.x_position), y_position: round(field.y_position),
        width: round(field.width), height: round(field.height),
        font_size: round(field.font_size), font_weight: field.font_weight || 'normal', text_align: field.text_align, text_color: field.text_color,
    });
    const scheduleSave = (field) => {
        if (readOnly || !field?.update_url) return;
        window.clearTimeout(saveTimer);
        setStatus('Menyimpan…', 'saving');
        // Each edit bumps the field's revision; a response for an older revision must not
        // overwrite what the clerk typed while it was in flight.
        const revision = (field.__rev = (field.__rev || 0) + 1);
        saveTimer = window.setTimeout(async () => {
            try {
                const result = await request(field.update_url, 'PUT', payloadFor(field));
                if (field.__rev === revision) Object.assign(field, result.field);
                setStatus('Tersimpan otomatis');
            } catch (error) {
                setStatus('Gagal menyimpan', 'error');
                showToast(error.message, true);
            }
        }, 450);
    };

    const variableOptions = (selected = '') => {
        const groups = new Map();
        variables.forEach((item) => { if (!groups.has(item.group)) groups.set(item.group, []); groups.get(item.group).push(item); });
        return [...groups.entries()].map(([group, items]) => `<optgroup label="${escapeHtml(group)}">${items.map((item) => `<option value="${escapeHtml(item.key)}" ${item.key === selected ? 'selected' : ''}>${escapeHtml(item.label)}</option>`).join('')}</optgroup>`).join('');
    };
    const renderSegments = (field) => {
        const list = propertyForm.querySelector('[data-segment-list]');
        const mapping = mappingFor(field);
        mapping.segments ||= [];
        list.innerHTML = '';
        mapping.segments.forEach((segment, index) => {
            const row = document.createElement('div');
            row.className = 'segment-row';
            row.innerHTML = `<select data-segment-type aria-label="Jenis bagian"><option value="source" ${segment.type === 'source' ? 'selected' : ''}>Data</option><option value="literal" ${segment.type === 'literal' ? 'selected' : ''}>Teks</option></select><div data-segment-input></div><button type="button" class="tool-button" data-remove-segment aria-label="Hapus bagian">×</button>`;
            const input = row.querySelector('[data-segment-input]');
            input.innerHTML = segment.type === 'literal'
                ? `<input value="${escapeHtml(segment.value)}" placeholder="Teks tetap" aria-label="Teks bagian">`
                : `<select aria-label="Data bagian">${variableOptions(segment.key)}</select>`;
            row.querySelector('[data-segment-type]').addEventListener('change', (event) => {
                mapping.segments[index] = event.target.value === 'literal' ? { type: 'literal', value: '' } : { type: 'source', key: variables[0]?.key || '' };
                renderSegments(field); refreshFieldElement(field); scheduleSave(field);
            });
            input.querySelector('input,select').addEventListener('input', (event) => {
                if (segment.type === 'literal') segment.value = event.target.value;
                else segment.key = event.target.value;
                refreshFieldElement(field); scheduleSave(field);
            });
            row.querySelector('[data-remove-segment]').addEventListener('click', () => {
                mapping.segments.splice(index, 1); renderSegments(field); refreshFieldElement(field); scheduleSave(field);
            });
            list.appendChild(row);
        });
    };
    const updateMappingPanel = (field) => {
        const mapping = mappingFor(field);
        const mode = mapping.mode || 'source';
        propertyForm.querySelector('[data-mapping-mode]').value = mode;
        propertyForm.querySelectorAll('[data-mode]').forEach((button) => { button.classList.toggle('active', button.dataset.mode === mode); button.setAttribute('aria-checked', String(button.dataset.mode === mode)); });
        propertyForm.querySelector('[data-mapping-source]').hidden = mode !== 'source';
        propertyForm.querySelector('[data-mapping-literal]').hidden = mode !== 'literal';
        propertyForm.querySelector('[data-mapping-segments]').hidden = mode !== 'segments';
        propertyForm.querySelector('[data-mapping-key]').value = mapping.key || field.variable_key;
        propertyForm.querySelector('[data-mapping-value]').value = mapping.value || '';
        propertyForm.querySelectorAll('[data-mapping-option]').forEach((input) => input.value = mapping[input.dataset.mappingOption] || '');
        propertyForm.querySelector('[data-date-format-wrap]').hidden = !dateKeys.includes(mapping.key || field.variable_key);
        renderSegments(field);
    };
    const updatePropertyPanel = () => {
        const field = selectedField();
        propertyEmpty.hidden = Boolean(field);
        propertyForm.classList.toggle('active', Boolean(field));
        root.classList.toggle('inspector-open', Boolean(field) && window.innerWidth <= 1100);
        if (!field) return;
        propertyForm.querySelectorAll('[data-property]').forEach((input) => input.value = field[input.dataset.property] ?? '');
        propertyForm.querySelectorAll('[data-align]').forEach((button) => button.classList.toggle('active', button.dataset.align === field.text_align));
        const bold = propertyForm.querySelector('[data-bold]');
        bold.classList.toggle('active', field.font_weight === 'bold'); bold.setAttribute('aria-pressed', String(field.font_weight === 'bold'));
        updateMappingPanel(field);
    };
    const applyFieldStyles = (element, field) => {
        Object.assign(element.style, {
            left: `${field.x_position}%`, top: `${field.y_position}%`, width: `${field.width}%`, height: `${field.height}%`,
            '--field-font-size': `${field.font_size * (pageElement.clientWidth / pageWidthPt)}px`, '--field-color': field.text_color, '--field-align': field.text_align, '--field-weight': field.font_weight === 'bold' ? '700' : '400',
        });
        element.querySelector('.field-text').textContent = displayText(field);
        element.title = field.label;
        element.classList.toggle('selected', String(field.id) === String(selectedId));
    };
    const refreshFieldElement = (field) => { const element = layer.querySelector(`[data-field-id="${field.id}"]`); if (element) applyFieldStyles(element, field); };
    const selectField = (id) => {
        selectedId = id;
        layer.querySelectorAll('.canvas-field').forEach((element) => element.classList.toggle('selected', String(element.dataset.fieldId) === String(id)));
        updatePropertyPanel();
    };
    const makeInteractive = (element) => {
        if (readOnly) return;
        interact(element).draggable({
            ignoreFrom: '.resize-handle', modifiers: [interact.modifiers.restrictRect({ restriction: 'parent', endOnly: true })],
            listeners: {
                move(event) { const field = fields.find((item) => String(item.id) === event.target.dataset.fieldId); if (!field) return; field.x_position = clamp(field.x_position + (event.dx / layer.clientWidth) * 100, 0, 100 - field.width); field.y_position = clamp(field.y_position + (event.dy / layer.clientHeight) * 100, 0, 100 - field.height); applyFieldStyles(event.target, field); updatePropertyPanel(); },
                end(event) { scheduleSave(fields.find((item) => String(item.id) === event.target.dataset.fieldId)); },
            },
        }).resizable({
            edges: { right: '.resize-handle', bottom: '.resize-handle' }, modifiers: [interact.modifiers.restrictEdges({ outer: 'parent' }), interact.modifiers.restrictSize({ min: { width: 48, height: 20 } })],
            listeners: {
                move(event) { const field = fields.find((item) => String(item.id) === event.target.dataset.fieldId); if (!field) return; field.width = clamp((event.rect.width / layer.clientWidth) * 100, 2, 100 - field.x_position); field.height = clamp((event.rect.height / layer.clientHeight) * 100, 1, 100 - field.y_position); applyFieldStyles(event.target, field); updatePropertyPanel(); },
                end(event) { scheduleSave(fields.find((item) => String(item.id) === event.target.dataset.fieldId)); },
            },
        });
    };
    const renderFields = () => {
        layer.innerHTML = '';
        const visible = fields.filter((field) => Number(field.page_number) === pageNumber);
        emptyCanvas.hidden = visible.length > 0;
        visible.forEach((field) => {
            const element = document.createElement('div');
            element.className = 'canvas-field'; element.dataset.fieldId = field.id;
            element.innerHTML = '<span class="field-text"></span><span class="resize-handle" aria-hidden="true"></span>';
            element.tabIndex = 0; element.setAttribute('role', 'button'); element.setAttribute('aria-label', `Teks ${field.label}`);
            applyFieldStyles(element, field);
            element.addEventListener('pointerdown', () => selectField(field.id));
            element.addEventListener('focus', () => selectField(field.id));
            element.addEventListener('keydown', (event) => {
                if (readOnly || !['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
                event.preventDefault(); const step = event.shiftKey ? 1 : .1;
                if (event.key === 'ArrowLeft') field.x_position = clamp(field.x_position - step, 0, 100 - field.width);
                if (event.key === 'ArrowRight') field.x_position = clamp(field.x_position + step, 0, 100 - field.width);
                if (event.key === 'ArrowUp') field.y_position = clamp(field.y_position - step, 0, 100 - field.height);
                if (event.key === 'ArrowDown') field.y_position = clamp(field.y_position + step, 0, 100 - field.height);
                applyFieldStyles(element, field); updatePropertyPanel(); scheduleSave(field);
            });
            layer.appendChild(element); makeInteractive(element);
        });
        updatePropertyPanel();
    };
    const renderPage = async () => {
        if (!pdf) return;
        if (renderTask) renderTask.cancel();
        const page = await pdf.getPage(pageNumber);
        const initialViewport = page.getViewport({ scale: 1 });
        pageWidthPt = initialViewport.width;
        const viewport = page.getViewport({ scale: pageElement.clientWidth / initialViewport.width });
        const outputScale = window.devicePixelRatio || 1;
        canvas.width = Math.floor(viewport.width * outputScale); canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${viewport.width}px`; canvas.style.height = `${viewport.height}px`; pageElement.style.aspectRatio = `${viewport.width} / ${viewport.height}`;
        renderTask = page.render({ canvasContext: canvas.getContext('2d'), transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0], viewport });
        try { await renderTask.promise; } catch (error) { if (error?.name !== 'RenderingCancelledException') throw error; }
        root.querySelector('[data-page-current]').textContent = pageNumber;
        root.querySelector('[data-prev-page]').disabled = pageNumber <= 1;
        root.querySelector('[data-next-page]').disabled = pageNumber >= pageCount;
        renderFields();
    };
    const fitPage = () => { const workspace = root.querySelector('.builder-workspace'); const available = Math.max(280, workspace.clientWidth - (window.innerWidth <= 720 ? 16 : 64)); pageElement.style.width = `${Math.min(768, available)}px`; zoom = pageElement.clientWidth / 768; root.querySelector('[data-zoom-label]').textContent = `${Math.round(zoom * 100)}%`; renderPage(); };
    const setZoom = (nextZoom) => { zoom = clamp(nextZoom, .6, 1.6); pageElement.style.width = `${768 * zoom}px`; root.querySelector('[data-zoom-label]').textContent = `${Math.round(zoom * 100)}%`; renderPage(); };

    const bindPaletteButton = (button) => button.addEventListener('click', async () => {
        if (readOnly) return;
        const count = fields.filter((field) => field.page_number === pageNumber).length;
        const field = { label: button.dataset.label, variable_key: button.dataset.key, mapping_config: { version: 1, mode: 'source', key: button.dataset.key }, page_number: pageNumber, x_position: 12 + (count % 5) * 3, y_position: 14 + (count % 8) * 7, width: 30, height: 4, font_size: 11, font_weight: 'normal', text_align: 'left', text_color: '#000000' };
        setStatus('Menambahkan…', 'saving');
        try {
            const result = await request(root.dataset.storeUrl, 'POST', payloadFor(field));
            const created = { ...result.field };
            created.update_url = root.dataset.storeUrl.replace(/\/fields$/, `/fields/${created.id}`); created.delete_url = created.update_url;
            fields.push(created); selectedId = created.id; renderFields(); setStatus('Tersimpan otomatis');
            showToast(`${field.label} ditempatkan di halaman ${pageNumber}. Geser ke posisi yang tepat.`);
            layer.querySelector(`[data-field-id="${created.id}"]`)?.focus();
        } catch (error) { setStatus('Gagal menambahkan', 'error'); showToast(error.message, true); }
    });
    const addPaletteItem = (variable) => {
        const list = root.querySelector('[data-variable-list]');
        let group = list.querySelector(`[data-variable-group="${variable.group}"]`);
        if (!group) {
            group = document.createElement('div'); group.className = 'palette-group'; group.dataset.variableGroup = variable.group;
            group.innerHTML = `<strong class="palette-group-title">${escapeHtml(variable.group)}</strong>`;
            list.insertBefore(group, list.querySelector('[data-palette-empty]'));
        }
        const button = document.createElement('button');
        button.className = 'palette-item'; button.type = 'button'; button.dataset.addField = ''; button.dataset.key = variable.key; button.dataset.label = variable.label;
        button.innerHTML = `<span class="palette-item-icon"><i data-lucide="message-square-text"></i></span><span><strong>${escapeHtml(variable.label)}</strong><small>Contoh: ${escapeHtml(variable.sample)}</small></span>`;
        group.appendChild(button); bindPaletteButton(button);
        window.refreshIcons?.();
        const option = document.createElement('option'); option.value = variable.key; option.textContent = variable.label;
        const select = propertyForm.querySelector('[data-mapping-key]');
        const optgroup = [...select.querySelectorAll('optgroup')].find((item) => item.label === variable.group);
        if (optgroup) optgroup.appendChild(option); else { const created = document.createElement('optgroup'); created.label = variable.group; created.appendChild(option); select.appendChild(created); }
    };

    root.querySelectorAll('[data-add-field]').forEach(bindPaletteButton);
    propertyForm.querySelectorAll('[data-property]').forEach((input) => input.addEventListener('input', () => { const field = selectedField(); if (!field) return; const numeric = ['font_size', 'x_position', 'y_position', 'width', 'height'].includes(input.dataset.property); field[input.dataset.property] = numeric ? Number(input.value) : input.value; refreshFieldElement(field); scheduleSave(field); }));
    const setMode = (mode) => { const field = selectedField(); if (!field) return; const mapping = mappingFor(field); mapping.mode = mode; if (mode === 'segments' && !mapping.segments?.length) mapping.segments = [{ type: 'source', key: field.variable_key }]; updateMappingPanel(field); refreshFieldElement(field); scheduleSave(field); };
    propertyForm.querySelectorAll('[data-mode]').forEach((button) => button.addEventListener('click', () => setMode(button.dataset.mode)));
    propertyForm.querySelector('[data-mapping-mode]').addEventListener('change', (event) => setMode(event.target.value));
    propertyForm.querySelector('[data-mapping-key]').addEventListener('change', (event) => { const field = selectedField(); if (!field) return; const mapping = mappingFor(field); mapping.key = event.target.value; field.variable_key = event.target.value; if (!dateKeys.includes(mapping.key)) delete mapping.date_format; if (field.label === variableByKey(mapping.key)?.label || !field.label) field.label = variableByKey(mapping.key)?.label || field.label; updateMappingPanel(field); propertyForm.querySelector('[data-property="label"]').value = field.label; refreshFieldElement(field); scheduleSave(field); });
    propertyForm.querySelector('[data-mapping-value]').addEventListener('input', (event) => { const field = selectedField(); if (!field) return; mappingFor(field).value = event.target.value; refreshFieldElement(field); scheduleSave(field); });
    propertyForm.querySelectorAll('[data-mapping-option]').forEach((input) => input.addEventListener('input', () => { const field = selectedField(); if (!field) return; const mapping = mappingFor(field); if (input.value === '') delete mapping[input.dataset.mappingOption]; else mapping[input.dataset.mappingOption] = input.value; refreshFieldElement(field); scheduleSave(field); }));
    propertyForm.querySelector('[data-add-segment]').addEventListener('click', () => { const field = selectedField(); if (!field) return; mappingFor(field).segments ||= []; mappingFor(field).segments.push({ type: 'literal', value: '' }); renderSegments(field); scheduleSave(field); });
    propertyForm.querySelectorAll('[data-align]').forEach((button) => button.addEventListener('click', () => { const field = selectedField(); if (!field) return; field.text_align = button.dataset.align; refreshFieldElement(field); updatePropertyPanel(); scheduleSave(field); }));
    propertyForm.querySelector('[data-bold]').addEventListener('click', () => { const field = selectedField(); if (!field) return; field.font_weight = field.font_weight === 'bold' ? 'normal' : 'bold'; refreshFieldElement(field); updatePropertyPanel(); scheduleSave(field); });
    root.querySelector('[data-delete-field]')?.addEventListener('click', async () => {
        const field = selectedField(); if (!field) return;
        const ok = window.appConfirm ? await window.appConfirm({ title: `Hapus teks “${field.label}”?`, text: 'Teks ini tidak lagi dicetak pada surat berikutnya.', label: 'Ya, hapus' }) : window.confirm(`Hapus teks "${field.label}"?`);
        if (!ok) return;
        try { await request(field.delete_url, 'DELETE'); fields = fields.filter((item) => String(item.id) !== String(field.id)); selectedId = null; renderFields(); showToast('Teks dihapus.'); } catch (error) { showToast(error.message, true); }
    });
    root.querySelector('[data-inspector-close]')?.addEventListener('click', () => selectField(null));
    root.querySelector('[data-prev-page]')?.addEventListener('click', () => { pageNumber = Math.max(1, pageNumber - 1); selectedId = null; renderPage(); });
    root.querySelector('[data-next-page]')?.addEventListener('click', () => { pageNumber = Math.min(pageCount, pageNumber + 1); selectedId = null; renderPage(); });
    root.querySelector('[data-zoom-in]')?.addEventListener('click', () => setZoom(zoom + .1)); root.querySelector('[data-zoom-out]')?.addEventListener('click', () => setZoom(zoom - .1)); root.querySelector('[data-fit-page]')?.addEventListener('click', fitPage);
    root.querySelector('[data-variable-search]').addEventListener('input', (event) => {
        const term = event.target.value.trim().toLowerCase();
        let shown = 0;
        root.querySelectorAll('.palette-item').forEach((button) => { const hit = !term || button.textContent.toLowerCase().includes(term); button.hidden = !hit; if (hit) shown++; });
        root.querySelectorAll('.palette-group').forEach((group) => group.hidden = ![...group.querySelectorAll('.palette-item')].some((button) => !button.hidden));
        root.querySelector('[data-palette-empty]').hidden = shown > 0;
    });

    const dialog = root.querySelector('[data-variable-dialog]');
    if (dialog) {
        const variableForm = root.querySelector('[data-variable-form]');
        const typeSelect = variableForm.querySelector('[name="field_type"]');
        const showErrors = (errors = {}) => variableForm.querySelectorAll('[data-error]').forEach((el) => { const message = errors[el.dataset.error]?.[0]; el.textContent = message || ''; el.hidden = !message; });
        root.querySelector('[data-open-variable]').addEventListener('click', () => { showErrors(); dialog.showModal(); variableForm.querySelector('[name="label"]').focus(); });
        typeSelect.addEventListener('change', () => variableForm.querySelector('[data-variable-options]').hidden = typeSelect.value !== 'select');
        variableForm.addEventListener('submit', async (event) => {
            if (event.submitter?.value === 'cancel') return;
            event.preventDefault();
            const data = Object.fromEntries(new FormData(variableForm));
            data.is_required = Boolean(data.is_required);
            data.options = String(data.options_text || '').split('\n').map((item) => item.trim()).filter(Boolean); delete data.options_text;
            if (!data.label.trim()) { showErrors({ label: ['Tulis pertanyaannya terlebih dahulu.'] }); return; }
            try {
                const result = await request(root.dataset.variableUrl, 'POST', data);
                variables.push(result.variable); addPaletteItem(result.variable);
                variableForm.reset(); variableForm.querySelector('[data-variable-options]').hidden = true; showErrors(); dialog.close(); showToast(result.message);
            } catch (error) { showErrors(error.fields); if (!Object.keys(error.fields || {}).length) showToast(error.message, true); }
        });
    }
    window.addEventListener('offline', () => setStatus('Offline — perubahan belum tersimpan', 'error'));
    window.addEventListener('online', () => setStatus('Koneksi pulih'));
    window.addEventListener('resize', () => { if (window.innerWidth > 1100) root.classList.remove('inspector-open'); });
    pdfjsLib.getDocument({ url: root.dataset.previewUrl }).promise.then((document) => { pdf = document; pageCount = document.numPages; root.querySelector('[data-page-count]').textContent = pageCount; fitPage(); }).catch((error) => { setStatus('PDF gagal dimuat', 'error'); showToast(`Halaman PDF gagal dimuat: ${error.message}`, true); });
}
