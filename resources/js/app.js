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
            index = Math.min(panels.length - 1, index + 1);
            show();
        });
        dots.forEach((dot, dotIndex) => dot.addEventListener('click', () => {
            index = dotIndex;
            show();
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

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons: interfaceIcons });
    initNavigation();
    initPhoneInputs();
    initSteppers();
    initDropzones();
    if (document.getElementById('request-trend-chart')) {
        import('./dashboard-chart').then(({ initDashboardChart }) => initDashboardChart());
    }
    if (document.getElementById('document-builder')) {
        import('./document-builder').then(({ initDocumentBuilder }) => initDocumentBuilder());
    }
});
