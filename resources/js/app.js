import {
    AlignCenter, AlignLeft, AlignRight, ArrowLeft, ArrowRight, ArrowUpRight,
    BadgeCheck, BellRing, BriefcaseBusiness, Calendar, CalendarDays, ChevronLeft,
    ChevronRight, CircleAlert, CircleCheck, CircleCheckBig, CirclePlus, ClipboardList,
    Clock3, ContactRound, createIcons, ExternalLink, FileBadge, FileClock, FileDown, FilePenLine,
    Files, Fingerprint, FolderSearch2, Grid2X2Check, Hash, HeartHandshake, History,
    House, Inbox, Landmark, LayoutDashboard, ListChecks, ListFilter, LockKeyhole,
    LogOut, MapPinHouse, Maximize, Megaphone, Menu, MessageCircleMore, MousePointer2,
    MousePointerClick, Paperclip, Pencil, ScanLine, ScanSearch, ScrollText, Search,
    SearchCheck, Send, ShieldCheck, SlidersHorizontal, Sparkles, Trash2, TrendingUp,
    Type, UserCog, UserRound, Users, UsersRound, ZoomIn, ZoomOut, Braces,
    Plus, X, RefreshCw, LayoutTemplate, Download, File, FileCheck2,
    Eye, FileText, Image as ImageIcon, QrCode, Unlink,
} from 'lucide';

const interfaceIcons = {
    AlignCenter, AlignLeft, AlignRight, ArrowLeft, ArrowRight, ArrowUpRight,
    BadgeCheck, BellRing, BriefcaseBusiness, Calendar, CalendarDays, ChevronLeft,
    ChevronRight, CircleAlert, CircleCheck, CircleCheckBig, CirclePlus, ClipboardList,
    Clock3, ContactRound, FileBadge, FileClock, FileDown, FilePenLine, Files,
    ExternalLink, Fingerprint, FolderSearch2, Grid2X2Check, Hash, HeartHandshake, History, House,
    Inbox, Landmark, LayoutDashboard, ListChecks, ListFilter, LockKeyhole, LogOut,
    MapPinHouse, Maximize, Megaphone, Menu, MessageCircleMore, MousePointer2,
    MousePointerClick, Paperclip, Pencil, ScanLine, ScanSearch, ScrollText, Search,
    SearchCheck, Send, ShieldCheck, SlidersHorizontal, Sparkles, Trash2, TrendingUp,
    Type, UserCog, UserRound, Users, UsersRound, ZoomIn, ZoomOut, Braces,
    Plus, X, RefreshCw, LayoutTemplate, Download, File, FileCheck2,
    Eye, FileText, Image: ImageIcon, QrCode, Unlink,
    Grid2x2Check: Grid2X2Check,
};

function initNavigation() {
    const adminToggle = document.querySelector('[data-menu-toggle]');
    const closeAdminMenu = () => {
        document.body.classList.remove('menu-open');
        adminToggle?.setAttribute('aria-expanded', 'false');
    };

    adminToggle?.addEventListener('click', () => {
        const open = document.body.classList.toggle('menu-open');
        adminToggle.setAttribute('aria-expanded', String(open));
    });
    document.querySelector('[data-menu-close]')?.addEventListener('click', closeAdminMenu);
    document.querySelectorAll('.side-nav a').forEach((link) => link.addEventListener('click', closeAdminMenu));

    const publicToggle = document.querySelector('[data-public-menu-toggle]');
    const publicMenu = document.querySelector('[data-public-menu]');
    const closePublicMenu = () => {
        publicMenu?.classList.remove('open');
        publicToggle?.setAttribute('aria-expanded', 'false');
    };
    publicToggle?.addEventListener('click', () => {
        const open = publicMenu?.classList.toggle('open') ?? false;
        publicToggle.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAdminMenu();
            closePublicMenu();
        }
    });
}

function sanitizePhoneInput(input) {
    let value = input.value.replace(/\D+/g, '').replace(/^0+/, '');
    input.value = value;
    const wrap = input.closest('.phone-input');
    if (!wrap) return;

    const country = wrap.querySelector('.phone-country')?.value || '+62';
    const hidden = wrap.querySelector('.phone-combined');
    if (hidden) hidden.value = value ? country + value : '';
}

function initPhoneInputs() {
    document.addEventListener('input', (event) => {
        if (event.target.matches('.phone-local')) sanitizePhoneInput(event.target);
    });
    document.addEventListener('change', (event) => {
        if (event.target.matches('.phone-country')) {
            const local = event.target.closest('.phone-input')?.querySelector('.phone-local');
            if (local) sanitizePhoneInput(local);
        }
    });
    document.querySelectorAll('.phone-local').forEach(sanitizePhoneInput);
}

function initSteppers() {
    document.querySelectorAll('[data-stepper]').forEach((stepper) => {
        let index = 0;
        const panels = [...stepper.querySelectorAll('.step-panel')];
        const dots = [...stepper.querySelectorAll('.stepper-dot')];
        const validatePanel = (panel) => {
            const invalid = [...panel.querySelectorAll('input, select, textarea')]
                .find((field) => !field.disabled && !field.checkValidity());
            if (!invalid) return true;

            invalid.reportValidity?.();
            invalid.focus({ preventScroll: false });
            return false;
        };
        const moveForward = (targetIndex) => {
            for (let panelIndex = index; panelIndex < targetIndex; panelIndex += 1) {
                if (!validatePanel(panels[panelIndex])) {
                    index = panelIndex;
                    show();
                    return;
                }
            }
            index = targetIndex;
            show();
        };
        const show = () => {
            panels.forEach((panel, panelIndex) => panel.classList.toggle('active', panelIndex === index));
            dots.forEach((dot, dotIndex) => dot.classList.toggle('active', dotIndex === index));
            const previous = stepper.querySelector('[data-prev]');
            const next = stepper.querySelector('[data-next]');
            const submit = stepper.querySelector('[data-submit]');
            if (previous) previous.style.visibility = index === 0 ? 'hidden' : 'visible';
            if (next) next.style.display = index === panels.length - 1 ? 'none' : 'inline-flex';
            if (submit) submit.style.display = index === panels.length - 1 ? 'inline-flex' : 'none';
        };
        stepper.querySelector('[data-prev]')?.addEventListener('click', () => {
            index = Math.max(0, index - 1);
            show();
        });
        stepper.querySelector('[data-next]')?.addEventListener('click', () => {
            moveForward(Math.min(panels.length - 1, index + 1));
        });
        dots.forEach((dot, dotIndex) => dot.addEventListener('click', () => {
            if (dotIndex <= index) {
                index = dotIndex;
                show();
                return;
            }
            moveForward(dotIndex);
        }));
        show();
    });
}

function initDropzones() {
    document.querySelectorAll('[data-dropzone]').forEach((zone) => {
        const input = zone.querySelector('.dropzone-input');
        const box = zone.querySelector('[data-dropzone-box]');
        const selected = zone.querySelector('[data-dropzone-selected]');
        const render = () => {
            const files = [...(input?.files || [])];
            if (selected) {
                selected.textContent = files.length
                    ? files.map((file) => `${file.name} (${Math.ceil(file.size / 1024)} KB)`).join(', ')
                    : 'Belum ada file dipilih.';
            }
        };
        box?.addEventListener('click', () => input?.click());
        input?.addEventListener('change', render);
        ['dragenter', 'dragover'].forEach((type) => box?.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.add('dragover');
        }));
        ['dragleave', 'drop'].forEach((type) => box?.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.remove('dragover');
        }));
        box?.addEventListener('drop', (event) => {
            if (input && event.dataTransfer?.files?.length) {
                input.files = event.dataTransfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        render();
    });
}

function initComboboxes() {
    document.querySelectorAll('[data-combobox]').forEach((box) => {
        const select = box.querySelector('[data-combobox-source]');
        const shell = box.querySelector('[data-combobox-shell]');
        const chips = box.querySelector('[data-combobox-chips]');
        const input = box.querySelector('.combobox-input');
        const list = box.querySelector('.combobox-list');
        if (!select || !shell || !chips || !input || !list) return;

        const addable = box.hasAttribute('data-combobox-addable');
        let active = -1;

        // The <select> stays the submitted value; we only swap which control is visible,
        // so the form still works when this script fails to load.
        select.hidden = true;
        select.tabIndex = -1;
        shell.hidden = false;

        const all = () => [...select.options];
        const chosen = () => all().filter((option) => option.selected);
        const shown = () => [...list.querySelectorAll('[role="option"]')];

        const setActive = (index) => {
            const items = shown();
            items.forEach((item) => {
                item.classList.remove('is-active');
                item.setAttribute('aria-selected', 'false');
            });
            active = index;
            const current = items[index];
            if (current) {
                current.classList.add('is-active');
                current.setAttribute('aria-selected', 'true');
                current.scrollIntoView({ block: 'nearest' });
            }
        };

        const deselect = (option) => {
            // Inline-created entries only exist in the DOM, so drop them entirely.
            if (option.dataset.created === 'true') option.remove();
            else option.selected = false;
            renderChips();
            renderList();
            input.focus();
        };

        const renderChips = () => {
            chips.replaceChildren();
            chosen().forEach((option) => {
                const chip = document.createElement('li');
                chip.className = 'combobox-chip';
                const text = document.createElement('span');
                text.textContent = option.value;
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'combobox-chip-remove';
                remove.setAttribute('aria-label', `Hapus role ${option.value}`);
                remove.textContent = '×';
                remove.addEventListener('click', () => deselect(option));
                chip.append(text, remove);
                chips.appendChild(chip);
            });
            input.placeholder = chosen().length ? 'Tambah role lain...' : 'Ketik untuk mencari role...';
        };

        const addOption = (li, value) => {
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', 'false');
            li.dataset.value = value;
            list.appendChild(li);
        };

        const renderList = () => {
            const typed = input.value.trim();
            const query = typed.toLowerCase();
            list.replaceChildren();

            all()
                .filter((option) => !option.selected && option.value.toLowerCase().includes(query))
                .forEach((option) => {
                    const li = document.createElement('li');
                    li.className = 'combobox-option';
                    li.textContent = option.value;
                    addOption(li, option.value);
                });

            const exists = all().some((option) => option.value.toLowerCase() === query);
            if (addable && typed !== '' && !exists) {
                const li = document.createElement('li');
                li.className = 'combobox-create';
                li.textContent = `Buat role baru: “${typed}”`;
                li.dataset.create = 'true';
                addOption(li, typed);
            }

            if (!list.children.length) {
                const li = document.createElement('li');
                li.className = 'combobox-empty';
                li.textContent = typed ? 'Role tidak ditemukan.' : 'Semua role sudah dipilih.';
                list.appendChild(li);
            }

            setActive(shown().length ? 0 : -1);
        };

        const open = () => { list.hidden = false; input.setAttribute('aria-expanded', 'true'); renderList(); };
        const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); setActive(-1); };

        const choose = (li) => {
            if (!li) return;
            const value = li.dataset.value;
            let option = all().find((candidate) => candidate.value === value);
            if (!option) {
                option = new Option(value, value);
                option.dataset.created = 'true';
                select.add(option);
            }
            option.selected = true;
            input.value = '';
            renderChips();
            renderList();
            input.focus();
        };

        input.addEventListener('focus', open);
        // Also on click: after removing a chip the input already holds focus, so a
        // focus listener alone would leave the list shut when the user clicks it.
        input.addEventListener('click', open);
        input.addEventListener('input', () => (list.hidden ? open() : renderList()));

        // The control is styled as one text field, so its padding should behave like it.
        shell.addEventListener('mousedown', (event) => {
            if (event.target === shell || event.target === chips) {
                event.preventDefault();
                input.focus();
                open();
            }
        });
        box.querySelector('[data-combobox-toggle]')?.addEventListener('click', () => (list.hidden ? open() : close()));

        // mousedown, not click: the input's blur must not close the list before selection lands.
        list.addEventListener('mousedown', (event) => {
            const li = event.target.closest('[role="option"]');
            if (li) { event.preventDefault(); choose(li); }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (list.hidden) { open(); return; }
                const items = shown();
                if (!items.length) return;
                setActive((active + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length);
            } else if (event.key === 'Enter') {
                const items = shown();
                if (!list.hidden && items[active]) { event.preventDefault(); choose(items[active]); }
            } else if (event.key === 'Backspace' && input.value === '') {
                const last = chosen().at(-1);
                if (last) deselect(last);
            } else if (event.key === 'Escape') {
                close();
            }
        });

        document.addEventListener('click', (event) => { if (!box.contains(event.target)) close(); });

        renderChips();
    });
}

function initPermissionPickers() {
    document.querySelectorAll('[data-permission-picker]').forEach((picker) => {
        const search = picker.querySelector('[data-permission-search]');
        const items = [...picker.querySelectorAll('[data-permission-item]')];
        const count = picker.querySelector('[data-permission-count]');
        const empty = picker.querySelector('[data-permission-empty]');
        const boxOf = (item) => item.querySelector('input[type="checkbox"]');

        const render = () => {
            if (count) count.textContent = `${items.filter((i) => boxOf(i).checked).length} dari ${items.length} izin`;
        };

        const filter = () => {
            const query = (search?.value || '').trim().toLowerCase();
            let shown = 0;
            items.forEach((item) => {
                const match = item.textContent.toLowerCase().includes(query);
                item.hidden = !match;
                if (match) shown += 1;
            });
            if (empty) empty.hidden = shown > 0;
        };

        // Bulk actions act on what is currently filtered, so "pilih semua" after a
        // search means "select these matches" rather than silently ticking all 17.
        const setVisible = (checked) => {
            items.filter((item) => !item.hidden).forEach((item) => { boxOf(item).checked = checked; });
            render();
        };

        search?.addEventListener('input', filter);
        picker.addEventListener('change', render);
        picker.querySelector('[data-permission-all]')?.addEventListener('click', () => setVisible(true));
        picker.querySelector('[data-permission-none]')?.addEventListener('click', () => setVisible(false));
        render();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons: interfaceIcons });
    initNavigation();
    initPhoneInputs();
    initSteppers();
    initDropzones();
    initComboboxes();
    initPermissionPickers();
    if (document.getElementById('request-trend-chart')) {
        import('./dashboard-chart').then(({ initDashboardChart }) => initDashboardChart());
    }
    if (document.getElementById('document-builder')) {
        import('./document-builder').then(({ initDocumentBuilder }) => initDocumentBuilder());
    }
});
