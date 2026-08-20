import interact from 'interactjs';
import * as pdfjsLib from 'pdfjs-dist';
import pdfWorker from 'pdfjs-dist/build/pdf.worker.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;
const clamp = (value, min, max) => Math.min(Math.max(value, min), max);
const round = (value) => Math.round(value * 100) / 100;

export function initDocumentBuilder() {
    const root = document.getElementById('document-builder');
    if (!root) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const canvas = root.querySelector('[data-pdf-canvas]');
    const pageElement = root.querySelector('[data-pdf-page]');
    const layer = root.querySelector('[data-field-layer]');
    const emptyCanvas = root.querySelector('[data-canvas-empty]');
    const propertyForm = root.querySelector('[data-property-form]');
    const propertyEmpty = root.querySelector('[data-property-empty]');
    const status = root.querySelector('[data-builder-status]');
    const toast = root.querySelector('[data-builder-toast]');
    let fields = JSON.parse(root.querySelector('[data-builder-fields]').textContent);
    let variables = JSON.parse(root.querySelector('[data-builder-variables]').textContent);
    let selectedId = null;
    let pageNumber = 1;
    let pageCount = 1;
    let pdf = null;
    let zoom = 1;
    let renderTask = null;
    let saveTimer = null;

    const setStatus = (message, state = 'saved') => {
        status.querySelector('span:last-child').textContent = message;
        const dot = status.querySelector('.system-dot');
        dot.classList.toggle('saving', state === 'saving');
        dot.classList.toggle('error', state === 'error');
    };
    const showToast = (message, isError = false) => {
        toast.textContent = message;
        toast.classList.toggle('error', isError);
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 2600);
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
            throw new Error(first || error.message || 'Perubahan gagal disimpan.');
        }
        return response.status === 204 ? null : response.json();
    };
    const selectedField = () => fields.find((field) => String(field.id) === String(selectedId));
    const mappingFor = (field) => field.mapping_config ||= { version: 1, mode: 'source', key: field.variable_key };
    const payloadFor = (field) => ({
        label: field.label,
        variable_key: field.variable_key,
        mapping_config: mappingFor(field),
        page_number: field.page_number,
        x_position: round(field.x_position), y_position: round(field.y_position),
        width: round(field.width), height: round(field.height),
        font_size: round(field.font_size), text_align: field.text_align, text_color: field.text_color,
    });
    const scheduleSave = (field) => {
        if (!field?.update_url) return;
        window.clearTimeout(saveTimer);
        setStatus('Menyimpan perubahan…', 'saving');
        saveTimer = window.setTimeout(async () => {
            try {
                const result = await request(field.update_url, 'PUT', payloadFor(field));
                Object.assign(field, result.field);
                setStatus('Semua perubahan tersimpan');
            } catch (error) {
                setStatus('Gagal menyimpan', 'error');
                showToast(error.message, true);
            }
        }, 450);
    };

    const variableOptions = (selected = '') => variables.map((item) => `<option value="${item.key}" ${item.key === selected ? 'selected' : ''}>${item.label} — ${item.key}</option>`).join('');
    const renderSegments = (field) => {
        const list = propertyForm.querySelector('[data-segment-list]');
        const mapping = mappingFor(field);
        mapping.segments ||= [];
        list.innerHTML = '';
        mapping.segments.forEach((segment, index) => {
            const row = document.createElement('div');
            row.className = 'segment-row';
            row.innerHTML = `<select data-segment-type><option value="source" ${segment.type === 'source' ? 'selected' : ''}>Variable</option><option value="literal" ${segment.type === 'literal' ? 'selected' : ''}>Teks</option></select><div data-segment-input></div><button type="button" class="tool-button" data-remove-segment aria-label="Hapus segmen">×</button>`;
            const input = row.querySelector('[data-segment-input]');
            input.innerHTML = segment.type === 'literal'
                ? `<input value="${String(segment.value || '').replaceAll('&', '&amp;').replaceAll('"', '&quot;')}" placeholder="Teks tetap">`
                : `<select>${variableOptions(segment.key)}</select>`;
            row.querySelector('[data-segment-type]').addEventListener('change', (event) => {
                mapping.segments[index] = event.target.value === 'literal' ? { type: 'literal', value: '' } : { type: 'source', key: variables[0]?.key || '' };
                renderSegments(field); scheduleSave(field);
            });
            input.querySelector('input,select').addEventListener('input', (event) => {
                if (segment.type === 'literal') segment.value = event.target.value;
                else segment.key = event.target.value;
                scheduleSave(field);
            });
            row.querySelector('[data-remove-segment]').addEventListener('click', () => {
                mapping.segments.splice(index, 1); renderSegments(field); scheduleSave(field);
            });
            list.appendChild(row);
        });
    };
    const updateMappingPanel = (field) => {
        const mapping = mappingFor(field);
        propertyForm.querySelector('[data-mapping-mode]').value = mapping.mode || 'source';
        propertyForm.querySelector('[data-mapping-source]').hidden = mapping.mode !== 'source';
        propertyForm.querySelector('[data-mapping-literal]').hidden = mapping.mode !== 'literal';
        propertyForm.querySelector('[data-mapping-segments]').hidden = mapping.mode !== 'segments';
        propertyForm.querySelector('[data-mapping-key]').value = mapping.key || field.variable_key;
        propertyForm.querySelector('[data-mapping-value]').value = mapping.value || '';
        propertyForm.querySelectorAll('[data-mapping-option]').forEach((input) => input.value = mapping[input.dataset.mappingOption] || '');
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
        updateMappingPanel(field);
    };
    const applyFieldStyles = (element, field) => {
        Object.assign(element.style, {
            left: `${field.x_position}%`, top: `${field.y_position}%`, width: `${field.width}%`, height: `${field.height}%`,
            '--field-font-size': `${field.font_size}px`, '--field-color': field.text_color, '--field-align': field.text_align,
        });
        const mapping = mappingFor(field);
        element.querySelector('.field-text').textContent = mapping.mode === 'literal' ? mapping.value || 'Teks tetap' : `{{ ${mapping.key || field.variable_key} }}`;
        element.classList.toggle('selected', String(field.id) === String(selectedId));
    };
    const selectField = (id) => {
        selectedId = id;
        layer.querySelectorAll('.canvas-field').forEach((element) => element.classList.toggle('selected', String(element.dataset.fieldId) === String(id)));
        updatePropertyPanel();
    };
    const makeInteractive = (element) => {
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
            element.tabIndex = 0; element.setAttribute('role', 'button'); element.setAttribute('aria-label', `Field ${field.label}`);
            applyFieldStyles(element, field);
            element.addEventListener('pointerdown', () => selectField(field.id));
            element.addEventListener('focus', () => selectField(field.id));
            element.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
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
        const viewport = page.getViewport({ scale: pageElement.clientWidth / initialViewport.width });
        const outputScale = window.devicePixelRatio || 1;
        canvas.width = Math.floor(viewport.width * outputScale); canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${viewport.width}px`; canvas.style.height = `${viewport.height}px`; pageElement.style.aspectRatio = `${viewport.width} / ${viewport.height}`;
        renderTask = page.render({ canvasContext: canvas.getContext('2d'), transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0], viewport });
        try { await renderTask.promise; } catch (error) { if (error?.name !== 'RenderingCancelledException') throw error; }
        root.querySelector('[data-page-current]').textContent = pageNumber; renderFields();
    };
    const fitPage = () => { const workspace = root.querySelector('.builder-workspace'); const available = Math.max(280, workspace.clientWidth - (window.innerWidth <= 720 ? 16 : 64)); pageElement.style.width = `${Math.min(768, available)}px`; zoom = pageElement.clientWidth / 768; root.querySelector('[data-zoom-label]').textContent = `${Math.round(zoom * 100)}%`; renderPage(); };
    const setZoom = (nextZoom) => { zoom = clamp(nextZoom, .6, 1.6); pageElement.style.width = `${768 * zoom}px`; root.querySelector('[data-zoom-label]').textContent = `${Math.round(zoom * 100)}%`; renderPage(); };
    const bindPaletteButton = (button) => button.addEventListener('click', async () => {
        const count = fields.filter((field) => field.page_number === pageNumber).length;
        const field = { label: button.dataset.label, variable_key: button.dataset.key, mapping_config: { version: 1, mode: 'source', key: button.dataset.key }, page_number: pageNumber, x_position: 12 + (count % 5) * 3, y_position: 14 + (count % 8) * 7, width: 30, height: 5, font_size: 11, text_align: 'left', text_color: '#17231f' };
        setStatus('Menambahkan field…', 'saving');
        try { const result = await request(root.dataset.storeUrl, 'POST', payloadFor(field)); const created = { ...result.field }; created.update_url = root.dataset.storeUrl.replace(/\/fields$/, `/fields/${created.id}`); created.delete_url = created.update_url; fields.push(created); selectedId = created.id; renderFields(); setStatus('Semua perubahan tersimpan'); showToast(`${field.label} ditambahkan`); }
        catch (error) { setStatus('Gagal menambahkan', 'error'); showToast(error.message, true); }
    });
    root.querySelectorAll('[data-add-field]').forEach(bindPaletteButton);
    propertyForm.querySelectorAll('[data-property]').forEach((input) => input.addEventListener('input', () => { const field = selectedField(); if (!field) return; const numeric = ['font_size', 'x_position', 'y_position', 'width', 'height'].includes(input.dataset.property); field[input.dataset.property] = numeric ? Number(input.value) : input.value; const element = layer.querySelector(`[data-field-id="${field.id}"]`); if (element) applyFieldStyles(element, field); scheduleSave(field); }));
    propertyForm.querySelector('[data-mapping-mode]').addEventListener('change', (event) => { const field = selectedField(); if (!field) return; const mapping = mappingFor(field); mapping.mode = event.target.value; if (mapping.mode === 'segments' && !mapping.segments?.length) mapping.segments = [{ type: 'source', key: field.variable_key }]; updateMappingPanel(field); scheduleSave(field); });
    propertyForm.querySelector('[data-mapping-key]').addEventListener('change', (event) => { const field = selectedField(); if (!field) return; mappingFor(field).key = event.target.value; field.variable_key = event.target.value; renderFields(); scheduleSave(field); });
    propertyForm.querySelector('[data-mapping-value]').addEventListener('input', (event) => { const field = selectedField(); if (!field) return; mappingFor(field).value = event.target.value; renderFields(); scheduleSave(field); });
    propertyForm.querySelectorAll('[data-mapping-option]').forEach((input) => input.addEventListener('input', () => { const field = selectedField(); if (!field) return; mappingFor(field)[input.dataset.mappingOption] = input.value; scheduleSave(field); }));
    propertyForm.querySelector('[data-add-segment]').addEventListener('click', () => { const field = selectedField(); if (!field) return; mappingFor(field).segments ||= []; mappingFor(field).segments.push({ type: 'literal', value: '' }); renderSegments(field); scheduleSave(field); });
    propertyForm.querySelectorAll('[data-align]').forEach((button) => button.addEventListener('click', () => { const field = selectedField(); if (!field) return; field.text_align = button.dataset.align; const element = layer.querySelector(`[data-field-id="${field.id}"]`); if (element) applyFieldStyles(element, field); updatePropertyPanel(); scheduleSave(field); }));
    root.querySelector('[data-delete-field]')?.addEventListener('click', async () => { const field = selectedField(); if (!field || !window.confirm(`Hapus field "${field.label}"?`)) return; try { await request(field.delete_url, 'DELETE'); fields = fields.filter((item) => String(item.id) !== String(field.id)); selectedId = null; renderFields(); showToast('Field dihapus'); } catch (error) { showToast(error.message, true); } });
    root.querySelector('[data-prev-page]')?.addEventListener('click', () => { pageNumber = Math.max(1, pageNumber - 1); selectedId = null; renderPage(); });
    root.querySelector('[data-next-page]')?.addEventListener('click', () => { pageNumber = Math.min(pageCount, pageNumber + 1); selectedId = null; renderPage(); });
    root.querySelector('[data-zoom-in]')?.addEventListener('click', () => setZoom(zoom + .1)); root.querySelector('[data-zoom-out]')?.addEventListener('click', () => setZoom(zoom - .1)); root.querySelector('[data-fit-page]')?.addEventListener('click', fitPage);
    root.querySelector('[data-variable-search]').addEventListener('input', (event) => root.querySelectorAll('.palette-item').forEach((button) => button.hidden = !button.textContent.toLowerCase().includes(event.target.value.toLowerCase())));

    const dialog = root.querySelector('[data-variable-dialog]'); const variableForm = root.querySelector('[data-variable-form]'); const typeSelect = variableForm.querySelector('[name="field_type"]');
    root.querySelector('[data-open-variable]').addEventListener('click', () => dialog.showModal());
    typeSelect.addEventListener('change', () => variableForm.querySelector('[data-variable-options]').hidden = typeSelect.value !== 'select');
    variableForm.addEventListener('submit', async (event) => {
        if (event.submitter?.value === 'cancel') return;
        event.preventDefault(); const data = Object.fromEntries(new FormData(variableForm)); data.is_required = Boolean(data.is_required); data.options = String(data.options_text || '').split('\n').map((item) => item.trim()).filter(Boolean); delete data.options_text;
        try { const result = await request(root.dataset.variableUrl, 'POST', data); variables.push(result.variable); const button = document.createElement('button'); button.className = 'palette-item'; button.type = 'button'; button.dataset.addField = ''; button.dataset.key = result.variable.key; button.dataset.label = result.variable.label; button.innerHTML = `<span class="palette-item-icon">{ }</span><span><strong>${result.variable.label}</strong><small>${result.variable.key} · draft</small></span>`; root.querySelector('[data-variable-list]').appendChild(button); bindPaletteButton(button); const option = document.createElement('option'); option.value = result.variable.key; option.textContent = `${result.variable.label} — ${result.variable.key}`; propertyForm.querySelector('[data-mapping-key]').appendChild(option); variableForm.reset(); dialog.close(); showToast(result.message); }
        catch (error) { showToast(error.message, true); }
    });
    window.addEventListener('offline', () => setStatus('Offline — perubahan belum tersimpan', 'error'));
    window.addEventListener('online', () => setStatus('Koneksi pulih'));
    pdfjsLib.getDocument({ url: root.dataset.previewUrl }).promise.then((document) => { pdf = document; pageCount = document.numPages; root.querySelector('[data-page-count]').textContent = pageCount; fitPage(); }).catch((error) => { setStatus('PDF gagal dimuat', 'error'); showToast(`PDF gagal dimuat: ${error.message}`, true); });
}
