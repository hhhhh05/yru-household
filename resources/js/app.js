/* ============================================================================
   ระบบฐานข้อมูลครัวเรือน มรย.พัฒนาท้องถิ่น · ยุทธศาสตร์ที่ 1
   สคริปต์ฝั่งหน้าเว็บ — ทำเฉพาะงานที่ต้องใช้ JS จริง ๆ
   (สลับธีม · ย่อเมนู · ลิ้นชัก/หน้าต่าง · แจ้งเตือน · เลือกหลายรายการ · ทูลทิป)
   หน้าเว็บทั้งหมด render จากฝั่ง Laravel (Blade) — ไม่ใช่ SPA
   ============================================================================ */

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
const fmt = (n) => Number(n).toLocaleString('th-TH');

/* ----------------------------------------------------------------- ไอคอน --- */
const ICONS = {
    chk: '<polyline points="20 6 9 17 4 12"/>',
    warn: '<path d="M10.3 3.6 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13.5"/><line x1="12" y1="17" x2="12" y2="17"/>',
    sun: '<circle cx="12" cy="12" r="4.2"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    moon: '<path d="M21 13.2A8.5 8.5 0 1 1 10.8 3a6.8 6.8 0 0 0 10.2 10.2z"/>',
};
const svg = (k, s = 17, w = 2) =>
    `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round">${ICONS[k] || ''}</svg>`;
const esc = (s) =>
    String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/* ------------------------------------------------------------ แจ้งเตือน --- */
let toastN = 0;

function toast(title, msg, kind) {
    const box = $('#toasts');

    if (!box) return;

    const el = document.createElement('div');
    el.className = 'toast' + (kind ? ' ' + kind : '');
    const ic = kind === 'err' || kind === 'warn' ? 'warn' : 'chk';
    const col = kind === 'err' ? 'var(--critical)' : kind === 'warn' ? 'var(--warning-ink)' : 'var(--good)';
    el.innerHTML =
        `<span style="color:${col};margin-top:1px">${svg(ic, 16, 2.2)}</span>` +
        `<div><b>${esc(title)}</b>${msg ? `<span>${esc(msg)}</span>` : ''}</div>`;
    box.appendChild(el);

    const id = ++toastN;
    setTimeout(() => {
        el.style.transition = 'opacity .2s,transform .2s';
        el.style.opacity = 0;
        el.style.transform = 'translateY(6px)';
        setTimeout(() => el.remove(), 220);
    }, 3600 + (id % 3) * 150);
}

/* แสดงข้อความที่ Laravel ส่งมาผ่าน session flash */
function flashToasts() {
    const tag = $('#flash-toasts');

    if (!tag) return;

    try {
        JSON.parse(tag.textContent || '[]').forEach((t) => toast(t.title, t.msg, t.kind));
    } catch (e) {
        /* ไม่ต้องทำอะไร */
    }
}

/* ----------------------------------------------------------------- ธีม --- */
function initTheme() {
    const root = document.documentElement;
    const saved = localStorage.getItem('yru-theme');

    if (saved) root.dataset.theme = saved;

    paintThemeButton();

    const btn = $('#theme');

    if (!btn) return;

    btn.addEventListener('click', () => {
        const dark = root.dataset.theme === 'dark';
        root.dataset.theme = dark ? 'light' : 'dark';
        localStorage.setItem('yru-theme', root.dataset.theme);
        paintThemeButton();
        toast(dark ? 'โหมดสว่าง' : 'โหมดมืด', 'สลับธีมเรียบร้อย');
    });
}

function paintThemeButton() {
    const btn = $('#theme');

    if (btn) btn.innerHTML = svg(document.documentElement.dataset.theme === 'dark' ? 'moon' : 'sun', 17, 2);
}

/* ------------------------------------------------------------ ย่อเมนู --- */
function initSidebar() {
    const app = $('#app');
    const burger = $('#burger');

    if (!app || !burger) return;

    if (localStorage.getItem('yru-sidebar') === 'collapsed') app.classList.add('collapsed');

    burger.addEventListener('click', () => {
        app.classList.toggle('collapsed');
        localStorage.setItem('yru-sidebar', app.classList.contains('collapsed') ? 'collapsed' : 'open');
    });
}

/* --------------------------------------------------------------- ทูลทิป --- */
function initTips() {
    const tt = $('#tt');

    if (!tt) return;

    document.addEventListener('mouseover', (e) => {
        const n = e.target.closest('[data-tip]');

        if (!n) return;

        tt.innerHTML = n.dataset.tip;
        tt.classList.add('on');
    });

    document.addEventListener('mousemove', (e) => {
        if (!tt.classList.contains('on')) return;

        const w = tt.offsetWidth;
        const h = tt.offsetHeight;
        tt.style.left = Math.min(window.innerWidth - w - 10, Math.max(8, e.clientX + 13)) + 'px';
        tt.style.top = Math.max(8, e.clientY - h - 11) + 'px';
    });

    document.addEventListener('mouseout', (e) => {
        if (e.target.closest('[data-tip]')) tt.classList.remove('on');
    });
}

/* ------------------------------------------------- ลิ้นชัก / หน้าต่าง --- */
function closeJsModals() {
    let closed = false;

    $$('.modal.js-modal.on').forEach((m) => {
        m.classList.remove('on');
        closed = true;

        if (m.id === 'm-en-detail') revertStatusSelect();
    });

    if (closed) {
        const veil = $('#veil');

        if (veil && !$('.drawer.on') && !$('.modal.on')) veil.classList.remove('on');

        document.body.style.overflow = '';
    }

    return closed;
}

function initOverlays() {
    const veil = $('#veil');

    /* ลิ้นชัก/หน้าต่างที่ render มาจากเซิร์ฟเวอร์ — ปิดโดยกลับไปหน้าเดิม */
    const closeUrl = veil?.dataset.closeUrl;

    if ($('.drawer.on') || $('.modal.on:not(.js-modal)')) document.body.style.overflow = 'hidden';

    veil?.addEventListener('click', () => {
        if (closeJsModals()) return;

        if (closeUrl) window.location.href = closeUrl;
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (closeJsModals()) return;

            if (closeUrl) window.location.href = closeUrl;
        }

        if (e.key === '/' && !/input|textarea|select/i.test(e.target.tagName)) {
            e.preventDefault();
            $('#gq')?.focus();
        }
    });

    /* เลือกโครงการหลัก → กรองรายการกิจกรรมในหน้าต่างเพิ่มเข้ากิจกรรม */
    document.addEventListener('change', (e) => {
        if (e.target.id === 'enrollProgram') filterEnrollActivities();
    });

    /* ปุ่มแก้ไขในแถวรายชื่อเข้าร่วม */
    document.addEventListener('click', (e) => {
        const edit = e.target.closest('[data-en-edit]');

        if (edit) {
            e.preventDefault();
            openEnrollEdit(edit);
        }
    });

    /* พิมพ์รายได้ → อัปเดตส่วนต่างให้เห็นทันที */
    document.addEventListener('input', (e) => {
        if (e.target.id === 'enEditBefore' || e.target.id === 'enEditAfter') showEnrollEditDiff();
    });

    /* เปิดหน้าต่างที่เตรียมไว้ในหน้า (การจัดการแบบกลุ่ม) */
    document.addEventListener('click', (e) => {
        const opener = e.target.closest('[data-modal-open]');

        if (opener) {
            e.preventDefault();
            /* data-modal-only = ทำกับรายการนี้รายการเดียว (ปุ่มในแถว)
               ไม่มีค่า = ทำกับรายการที่ติ๊กเลือกไว้ (แถบจัดการแบบกลุ่ม) */
            openJsModal(opener.dataset.modalOpen, opener.dataset.modalOnly, opener.dataset);
            return;
        }

        if (e.target.closest('[data-modal-close]')) {
            e.preventDefault();
            closeJsModals();
        }
    });
}

/* สถานะที่ต้องกรอกข้อมูลเพิ่มก่อนบันทึก */
const STATUS_NEEDS_DETAIL = {
    'สำเร็จ': { income: true, noteRequired: false,
        title: 'บันทึกผลสำเร็จ',
        noteHint: 'ผลที่เกิดขึ้นหลังเข้าร่วม เช่น เริ่มขายผลผลิตได้ · เว้นว่างได้' },
    'ออกกลางคัน': { income: false, noteRequired: true,
        title: 'บันทึกการออกกลางคัน',
        noteHint: 'ระบุเหตุผลที่ออก เช่น ย้ายถิ่น · ปัญหาสุขภาพ — จำเป็นต้องกรอก' },
};

/**
 * เปิดหน้าต่างกรอกข้อมูลเพิ่ม เมื่อเลือกสถานะที่ต้องมีรายละเอียด
 * คืน true ถ้าเปิดหน้าต่าง (แปลว่าอย่าเพิ่งส่งฟอร์ม) · false ถ้าส่งได้เลย
 */
function openStatusDetail(select) {
    const spec = STATUS_NEEDS_DETAIL[select.value];
    const modal = $('#m-en-detail');
    const form = $('#enDetailForm');

    if (!spec || !modal || !form) return false;

    const urlTemplate = readJson('#en-status-url');

    if (!urlTemplate) return false;

    form.action = urlTemplate.replace('__ID__', encodeURIComponent(select.dataset.enId || ''));
    $('#enDetailStatus').value = select.value;
    $('#enDetailTitle').textContent = spec.title;
    $('#enDetailWho').innerHTML =
        `<span class="code">${esc(select.dataset.enHc || '')}</span> · ${esc(select.dataset.enName || '')}`;

    /* ช่องรายได้แสดงเฉพาะสถานะ «สำเร็จ» — ถ้าซ่อนต้องปิดการส่งค่าด้วย
       ไม่งั้นเซิร์ฟเวอร์จะเห็น income_after ว่างแล้วไปล้างค่าเดิมทิ้ง */
    const incomeBox = $('#enDetailIncomeBox');
    const income = $('#enDetailIncome');
    incomeBox.hidden = !spec.income;
    income.disabled = !spec.income;
    income.value = spec.income ? select.dataset.enIncome || '' : '';

    /* รายได้ก่อนเข้าร่วม แสดงคู่กันเฉพาะตอน «สำเร็จ» เพราะเป็นตอนที่ต้องเทียบสองตัวเลข
       ปิด disabled ด้วยเมื่อซ่อน ไม่งั้นค่าว่างจะถูกส่งไปล้างค่าที่จดไว้ */
    const beforeBox = $('#enDetailIncomeBeforeBox');
    const before = $('#enDetailIncomeBefore');

    if (beforeBox && before) {
        beforeBox.hidden = !spec.income;
        before.disabled = !spec.income;
        /* บังคับกรอกเมื่อช่องโผล่ — ปิด required พร้อมกับซ่อน
           ไม่งั้นเบราว์เซอร์จะบล็อกการส่งด้วยช่องที่มองไม่เห็น */
        before.required = spec.income;
        before.value = spec.income ? select.dataset.enIncomeBefore || '' : '';
    }

    const note = $('#enDetailNote');
    note.value = select.dataset.enNote || '';
    note.required = spec.noteRequired;
    $('#enDetailNoteReq').hidden = !spec.noteRequired;
    $('#enDetailNoteHint').textContent = spec.noteHint;

    /* ถ้าปิดหน้าต่างโดยไม่บันทึก ต้องคืนค่า dropdown กลับเป็นสถานะเดิม
       ไม่งั้นหน้าจอจะโชว์สถานะใหม่ทั้งที่ยังไม่ได้บันทึกลงฐานข้อมูล */
    const previous = select.dataset.enPrev || originalStatus(select);
    modal.dataset.revertTo = previous;
    modal.dataset.revertId = select.dataset.enId || '';

    openJsModal('m-en-detail');
    setTimeout(() => (spec.income ? income : note).focus(), 60);

    return true;
}

/**
 * เปิดหน้าต่างแก้ไขรายการเข้าร่วม — รายได้ก่อน/หลัง และหมายเหตุ
 * ส่งสถานะเดิมกลับไปด้วยโดยไม่เปลี่ยน เพราะปลายทางเดียวกับการเปลี่ยนสถานะ
 */
function openEnrollEdit(button) {
    const form = $('#enEditForm');
    const urlTemplate = readJson('#en-status-url');

    if (!form || !urlTemplate) return;

    const d = button.dataset;
    form.action = urlTemplate.replace('__ID__', encodeURIComponent(d.enId || ''));
    $('#enEditStatus').value = d.enStatus || '';
    $('#enEditWho').innerHTML =
        `<span class="code">${esc(d.enHc || '')}</span> · ${esc(d.enName || '')}`
        + (d.enPa ? ` · <span class="code">${esc(d.enPa)}</span>` : '');

    const before = $('#enEditBefore');
    const after = $('#enEditAfter');
    const note = $('#enEditNote');
    const joined = $('#enEditJoined');

    if (joined) joined.value = d.enJoined || '';

    before.value = d.enBefore || '';
    after.value = d.enAfter || '';
    note.value = d.enNote || '';

    /* «ออกกลางคัน» ต้องมีเหตุผลเสมอ — กฎเดียวกับตอนเปลี่ยนสถานะ
       ถ้าไม่บังคับที่นี่ด้วย จะลบหมายเหตุทิ้งผ่านหน้าต่างนี้ได้ แล้วเซิร์ฟเวอร์จะปฏิเสธทีหลัง */
    const noteRequired = d.enStatus === 'ออกกลางคัน';
    note.required = noteRequired;
    $('#enEditNoteReq').hidden = !noteRequired;
    $('#enEditNoteHint').textContent = noteRequired
        ? 'สถานะออกกลางคัน — ต้องระบุเหตุผล'
        : 'สถานะปัจจุบัน: ' + (d.enStatus || '—') + ' (แก้ที่นี่ไม่เปลี่ยนสถานะ)';

    showEnrollEditDiff();
    openJsModal('m-en-edit');
    setTimeout(() => before.focus(), 60);
}

/** โชว์ส่วนต่างรายได้ทันทีที่พิมพ์ จะได้เห็นว่าตัวเลขที่กรอกสมเหตุสมผลไหม */
function showEnrollEditDiff() {
    const before = $('#enEditBefore');
    const after = $('#enEditAfter');
    const hint = $('#enEditDiff');

    if (!before || !after || !hint) return;

    const a = before.value === '' ? null : Number(before.value);
    const b = after.value === '' ? null : Number(after.value);

    if (a === null || b === null || Number.isNaN(a) || Number.isNaN(b)) {
        hint.textContent = 'เว้นว่างได้ถ้ายังเก็บตัวเลขไม่ได้';
        hint.style.color = '';

        return;
    }

    const diff = b - a;
    hint.textContent = diff === 0
        ? 'เท่ากับก่อนเข้าร่วม'
        : (diff > 0 ? '+' : '−') + fmt(Math.abs(diff)) + ' บาท/ปี เทียบก่อนเข้าร่วม';
    hint.style.color = diff > 0 ? 'var(--good-ink)' : (diff < 0 ? 'var(--critical-ink)' : '');
}

/** สถานะที่เซิร์ฟเวอร์ส่งมา (option ที่ถูก selected ตอน render) */
function originalStatus(select) {
    const marked = [...select.options].find((o) => o.defaultSelected);

    return marked ? marked.value : select.value;
}

/** คืนค่า dropdown กลับสถานะเดิม เมื่อผู้ใช้ปิดหน้าต่างโดยไม่บันทึก */
function revertStatusSelect() {
    const modal = $('#m-en-detail');

    if (!modal || !modal.dataset.revertId) return;

    const select = $(`select[data-status][data-en-id="${CSS.escape(modal.dataset.revertId)}"]`);

    if (select) select.value = modal.dataset.revertTo;

    delete modal.dataset.revertId;
}

/**
 * หน้าต่างที่ใช้ร่วมกันระหว่าง «เพิ่ม» กับ «แก้ไข» (เจ้าหน้าที่ · คณะ/หน่วยงาน)
 *
 * ตั้งค่าทุกช่องทุกครั้งที่เปิด (ไม่ใช่เฉพาะช่องที่มีค่า)
 * ไม่งั้นค่าของรายการก่อนหน้าจะค้างอยู่ในช่องที่รายการใหม่ไม่มีข้อมูล
 */
function prepareRecordModal(modal, data, cfg) {
    if (modal.id !== cfg.modal) return;

    const form = modal.querySelector(cfg.form);
    const method = modal.querySelector(cfg.method);

    if (!form) return;

    const d = data || {};
    const id = d[cfg.idKey] || '';
    const editing = Boolean(id);

    const storeUrl = readJson(cfg.storeUrl) || form.getAttribute('action') || '';
    const updateUrl = readJson(cfg.updateUrl) || '';

    /* แก้ไข = PATCH ไปที่ URL ของรายการนั้น · เพิ่ม = POST ไปที่ URL รายการรวม
       ถ้าแม่แบบ URL หาย ให้ถอยไปโหมดเพิ่ม ดีกว่าส่ง PATCH ไปผิดที่ */
    if (editing && updateUrl) {
        form.setAttribute('action', updateUrl.replace('__ID__', encodeURIComponent(id)));
        if (method) method.value = 'PATCH';
    } else {
        form.setAttribute('action', storeUrl);
        if (method) method.value = '';
    }

    Object.entries(cfg.fields).forEach(([selector, key]) => {
        setFieldValue(modal.querySelector(selector), editing ? (d[key] || '') : '');
    });

    const title = modal.querySelector(cfg.title);
    const sub = modal.querySelector(cfg.sub);

    if (title) title.textContent = editing ? cfg.titleEdit : cfg.titleNew;
    if (sub) sub.textContent = editing ? cfg.subEdit : cfg.subNew;
}

/**
 * ใส่ค่าลงช่องกรอก
 *
 * ถ้าเป็นช่องตัวเลือกและค่านั้นไม่มีในรายการ (ข้อมูลเก่าที่บันทึกไว้ก่อนมีรายการกลาง)
 * ให้เติมตัวเลือกชั่วคราวก่อน ไม่งั้นเบราว์เซอร์จะตั้งค่าเป็นว่าง
 * แล้วการกดบันทึกจะลบค่าเดิมทิ้งโดยที่ผู้ใช้ไม่รู้ตัว
 */
function setFieldValue(field, value) {
    if (!field) return;

    if (field.tagName === 'SELECT') {
        /* ตัวเลือกชั่วคราวของรอบก่อน เอาออกก่อนเสมอ */
        field.querySelector('option[data-adhoc]')?.remove();

        const has = [...field.options].some((o) => o.value === value);

        if (value && !has) {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value + ' (ไม่อยู่ในรายการ)';
            opt.dataset.adhoc = '1';
            field.appendChild(opt);
        }
    }

    field.value = value;
}

const STAFF_MODAL = {
    modal: 'm-staff',
    form: '#staffForm',
    method: '#staffMethod',
    idKey: 'staffId',
    storeUrl: '#staff-store-url',
    updateUrl: '#staff-update-url',
    title: '#staffTitle',
    sub: '#staffSub',
    titleNew: 'เพิ่มเจ้าหน้าที่',
    titleEdit: 'แก้ไขเจ้าหน้าที่',
    subNew: 'รายชื่อกลาง ไม่ผูกกับพื้นที่',
    subEdit: 'แก้ไขข้อมูลติดต่อของเจ้าหน้าที่รายนี้',
    fields: {
        '#staffName': 'staffName',
        '#staffPosition': 'staffPosition',
        '#staffUnit': 'staffUnit',
        '#staffPhone': 'staffPhone',
        '#staffEmail': 'staffEmail',
    },
};

const UNIT_MODAL = {
    modal: 'm-unit',
    form: '#unitForm',
    method: '#unitMethod',
    idKey: 'unitId',
    storeUrl: '#unit-store-url',
    updateUrl: '#unit-update-url',
    title: '#unitTitle',
    sub: '#unitSub',
    titleNew: 'เพิ่มคณะ / หน่วยงาน',
    titleEdit: 'แก้ไขคณะ / หน่วยงาน',
    subNew: 'ใช้เป็นตัวเลือกในหน้าเจ้าหน้าที่',
    subEdit: 'เปลี่ยนชื่อที่นี่ ระบบจะตามไปแก้ให้เจ้าหน้าที่ในคณะนี้ด้วย',
    fields: {
        '#unitName': 'unitName',
        '#unitShort': 'unitShort',
        '#unitNote': 'unitNote',
    },
};

/**
 * เตรียมหน้าต่าง «เพิ่มเข้ากิจกรรม»
 *   · ช่องรายได้ก่อนเข้าร่วม เปิดเฉพาะตอนเพิ่มทีละราย และเติมค่าจากทะเบียนให้ก่อน (แก้ได้)
 *   · ปิด disabled เมื่อซ่อน ไม่งั้นค่าว่างจะถูกส่งไปทับค่าที่ระบบจะจดให้เอง
 */
function prepareEnrollModal(modal, only, data) {
    /* ทำเฉพาะหน้าต่าง «เพิ่มเข้ากิจกรรม» เท่านั้น
       หน้าต่างอื่นก็มีช่อง name="status" เหมือนกัน (เช่นหน้าต่างแก้ไขรายการเข้าร่วม)
       ถ้าไม่กันไว้ การล้างค่าด้านล่างจะไปลบสถานะของหน้าต่างนั้นทิ้ง */
    if (modal.id !== 'm-enroll') return;

    showEnrollWho(modal, only, data);

    /* เปิดหน้าต่างใหม่ทุกครั้ง = เริ่มเลือกใหม่หมด ไม่ให้ค่าจากครั้งก่อนค้างอยู่
       ไม่งั้นเพิ่มคนที่สองอาจติดกิจกรรมของคนแรกไปโดยไม่ได้ตั้งใจ */
    const program = modal.querySelector('#enrollProgram');
    const activity = modal.querySelector('#enrollActivity');
    const status = modal.querySelector('[name="status"]');

    if (program) program.value = '';
    if (activity) activity.value = '';
    if (status) status.value = '';

    filterEnrollActivities();

    const box = modal.querySelector('#enrollIncomeBox');
    const input = modal.querySelector('#enrollIncome');
    const hint = modal.querySelector('#enrollIncomeHint');

    if (!box || !input) return;

    /* บังคับกรอกเฉพาะตอนเพิ่มทีละราย — ตอนนั้นเท่านั้นที่ช่องนี้โผล่ให้กรอกจริง
       ปิด required พร้อม disabled เมื่อซ่อน ไม่งั้นเบราว์เซอร์จะบล็อกการส่งฟอร์ม
       ด้วยช่องที่มองไม่เห็น แล้วผู้ใช้จะงงว่ากดบันทึกแล้วไม่มีอะไรเกิดขึ้น */
    const single = Boolean(only);
    box.hidden = !single;
    input.disabled = !single;
    input.required = single;

    if (!single) {
        input.value = '';
        return;
    }

    const known = (data && data.modalIncome) || '';
    input.value = known;

    if (hint) {
        hint.textContent = known
            ? 'ดึงจากทะเบียนครัวเรือนมาให้ แก้ได้ถ้าตัวเลขปัจจุบันไม่ตรง'
            : 'ทะเบียนยังไม่มีรายได้ของครัวเรือนนี้ — กรอกได้ หรือเว้นว่างไว้ก่อน';
    }
}

/**
 * บอกให้ชัดว่ากำลังเพิ่ม «ใคร» เข้ากิจกรรม
 *   · ทีละราย  → รหัส HC + ชื่อ
 *   · ทีละกลุ่ม → รายชื่อที่ติ๊กไว้ (ยาวเกินก็ตัดแล้วบอกว่าเหลืออีกกี่ราย)
 * ป้องกันการกดผิดแถวแล้วเพิ่มผิดคนโดยไม่รู้ตัว
 */
function showEnrollWho(modal, only, data) {
    const who = modal.querySelector('#enrollWho');
    const title = modal.querySelector('#enrollTitle');

    if (!who) return;

    if (only) {
        const name = (data && data.modalName) || '';
        who.innerHTML = `<span class="code">${esc(only)}</span>${name ? ' · ' + esc(name) : ''}`;

        if (title) title.textContent = 'เพิ่มครัวเรือนนี้เข้ากิจกรรม';

        return;
    }

    if (title) {
        title.innerHTML = 'เพิ่ม <span data-selection-count>0</span> ครัวเรือนเข้ากิจกรรม';
    }

    const picked = selectionBoxes()
        .filter((b) => b.checked)
        .map((b) => b.dataset.ckName || b.dataset.ck || b.dataset.cken)
        .filter(Boolean);

    if (!picked.length) {
        who.textContent = 'เลือกกิจกรรมปลายทาง';

        return;
    }

    const shown = picked.slice(0, 5).map(esc).join(' · ');
    who.innerHTML = shown + (picked.length > 5 ? ` และอีก ${fmt(picked.length - 5)} ราย` : '');
}

/** กรองรายการกิจกรรมตามโครงการหลักที่เลือก */
function filterEnrollActivities() {
    const program = document.getElementById('enrollProgram');
    const activity = document.getElementById('enrollActivity');

    if (!program || !activity) return;

    const want = program.value;
    const placeholder = activity.querySelector('option[value=""]');
    let matches = 0;

    [...activity.options].forEach((opt) => {
        if (opt.value === '') return;   // ตัวเลือกหัวข้อ ไม่ต้องกรอง

        const show = Boolean(want) && opt.dataset.program === want;
        opt.hidden = !show;
        opt.disabled = !show;

        if (show) matches++;
    });

    if (placeholder) {
        placeholder.textContent = want
            ? (matches ? '— เลือกกิจกรรม —' : '— โครงการนี้ยังไม่มีกิจกรรม —')
            : '— เลือกโครงการหลักก่อน —';
    }

    /* กิจกรรมที่ค้างอยู่ไม่อยู่ในโครงการที่เพิ่งเลือก → กลับไปที่ตัวเลือกหัวข้อ
       จงใจไม่เด้งไปกิจกรรมแรกให้เอง เพราะผู้ใช้ต้องเลือกเองทุกครั้ง
       ไม่งั้นอาจกดบันทึกโดยไม่ทันดูว่าเป็นกิจกรรมไหน */
    const current = activity.selectedOptions[0];

    if (!current || current.hidden) {
        activity.value = '';
    }
}

function openJsModal(id, only, data) {
    const m = document.getElementById(id);

    if (!m) return;

    prepareEnrollModal(m, only, data);
    prepareRecordModal(m, data, STAFF_MODAL);
    prepareRecordModal(m, data, UNIT_MODAL);

    /* เติมรายการที่เลือกไว้ลงในฟอร์ม — หรือรายการเดียวถ้าเปิดจากปุ่มในแถว */
    const selected = only ? [only] : currentSelection();

    /* จำไว้ว่าหน้าต่างนี้ล็อกไว้กับรายการเดียวหรือไม่
       ถ้าไม่จำ syncSelection() จะไปเขียนทับด้วยรายการที่ติ๊กไว้ทันทีที่มีการติ๊ก */
    m.dataset.onlyHc = only || '';
    $$('[data-fill-selection]', m).forEach((input) => {
        input.value = selected.join(',');
    });
    $$('[data-selection-count]', m).forEach((el) => {
        el.textContent = fmt(selected.length);
    });

    m.classList.add('on');
    $('#veil')?.classList.add('on');
    document.body.style.overflow = 'hidden';
}

/* ------------------------------------------- ยืนยันก่อนทำรายการสำคัญ --- */
function initConfirm() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-confirm]');

        if (!btn) return;

        e.preventDefault();

        const form = btn.closest('form');
        const kind = btn.dataset.confirmKind || 'crit';
        const box = $('#modal-confirm');

        if (!box || !form) return;

        box.innerHTML =
            `<div class="md-h"><div class="ic-cir ${kind}">${svg(kind === 'brand' ? 'chk' : 'warn', 19, 2.1)}</div>
             <div style="flex:1"><h3>${esc(btn.dataset.confirmTitle || 'ยืนยันการทำรายการ')}</h3>
             <p>การกระทำนี้มีผลกับข้อมูลในระบบ</p></div></div>
             <div class="md-b">${btn.dataset.confirmBody || ''}</div>
             <div class="md-f"><span style="flex:1"></span>
             <button type="button" class="btn out" data-modal-close>ยกเลิก</button>
             <button type="button" class="btn ${kind === 'brand' ? 'pri' : 'dang'}" id="confirmOk">${esc(btn.dataset.confirmLabel || 'ยืนยัน')}</button></div>`;

        box.classList.add('on');
        $('#veil')?.classList.add('on');
        document.body.style.overflow = 'hidden';

        $('#confirmOk').addEventListener('click', () => {
            /* เติมรายการที่เลือก (กรณีลบหลายรายการ) */
            $$('[data-fill-selection]', form).forEach((i) => {
                i.value = currentSelection().join(',');
            });
            form.submit();
        });
    });
}

/* -------------------------------------------------- เลือกหลายรายการ --- */
function selectionBoxes() {
    return $$('input[data-ck], input[data-cken]');
}

function currentSelection() {
    return selectionBoxes()
        .filter((b) => b.checked)
        .map((b) => b.dataset.ck || b.dataset.cken);
}

function syncSelection() {
    const boxes = selectionBoxes();
    const sel = currentSelection();
    const all = $('#ckAll') || $('#ckEnAll');

    if (all) {
        all.checked = sel.length > 0 && sel.length === boxes.length;
        all.indeterminate = sel.length > 0 && sel.length < boxes.length;
    }

    $$('[data-bulk-bar]').forEach((bar) => {
        bar.hidden = sel.length === 0;
    });
    $$('[data-sel-count]').forEach((el) => {
        el.textContent = fmt(sel.length);
    });
    $$('[data-fill-selection]').forEach((i) => {
        /* ข้ามหน้าต่างที่เปิดจากปุ่มในแถว — ค่าของมันคือครัวเรือนรายเดียวนั้น ไม่ใช่รายการที่ติ๊ก */
        if (i.closest('.js-modal')?.dataset.onlyHc) return;

        i.value = sel.join(',');
    });

    /* ไฮไลต์แถวที่เลือก */
    boxes.forEach((b) => {
        const row = b.closest('tr') || b.closest('.mcard');

        if (row) row.classList.toggle(row.tagName === 'TR' ? 'sel-row' : 'on', b.checked);
    });
}

function initSelection() {
    document.addEventListener('change', (e) => {
        const t = e.target;

        if (t.id === 'ckAll' || t.id === 'ckEnAll') {
            selectionBoxes().forEach((b) => {
                b.checked = t.checked;
            });
            syncSelection();
            return;
        }

        if (t.dataset.ck || t.dataset.cken) syncSelection();
    });

    /* กรณีเปิดหน้ามาพร้อมรายการที่ถูกเลือกไว้แล้ว (?hcs=...) */
    syncSelection();

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-sel-clear]')) {
            e.preventDefault();
            selectionBoxes().forEach((b) => {
                b.checked = false;
            });
            syncSelection();
        }
    });
}

/* ------------------------------------------------ ตัวกรอง / เรียงลำดับ --- */
function initFilters() {
    /* select เปลี่ยนค่า → ส่งฟอร์มทันที */
    document.addEventListener('change', (e) => {
        const t = e.target;

        if (t.matches('form.js-auto select') || t.matches('select.js-auto')) {
            t.form?.requestSubmit();
        }

        /* เปลี่ยนสถานะรายชื่อเข้าร่วม
           «สำเร็จ» และ «ออกกลางคัน» ต้องกรอกข้อมูลเพิ่ม จึงเปิดหน้าต่างแทนการส่งทันที */
        if (t.matches('select[data-status]')) {
            if (openStatusDetail(t)) return;

            t.form?.requestSubmit();
        }
    });

    /* ช่องค้นหา → ส่งฟอร์มแบบหน่วงเวลา */
    let timer = null;
    document.addEventListener('input', (e) => {
        const t = e.target;

        if (!t.matches('form.js-auto input[type="search"], form.js-auto input.js-search')) return;

        clearTimeout(timer);
        timer = setTimeout(() => t.form?.requestSubmit(), 450);
    });

    /* คืนตำแหน่งเคอร์เซอร์ให้ช่องค้นหาหลังโหลดหน้าใหม่ */
    const focusEl = $('[data-autofocus-end]');

    if (focusEl && focusEl.value) {
        focusEl.focus();
        focusEl.setSelectionRange(focusEl.value.length, focusEl.value.length);
    }
}

/* --------------------------------------------- ฟอร์มครัวเรือน (ลิ้นชัก) --- */
function initHouseholdForm() {
    const form = $('#hhf');

    if (!form) return;

    /* จัดรูปแบบเบอร์โทรอัตโนมัติ */
    form.addEventListener('input', (e) => {
        const t = e.target;

        if (t.name === 'phone') {
            const d = t.value.replace(/\D/g, '').slice(0, 10);
            t.value = d.length > 6 ? `${d.slice(0, 3)}-${d.slice(3, 6)}-${d.slice(6)}` : d.length > 3 ? `${d.slice(0, 3)}-${d.slice(3)}` : d;
        }

        if (t.name === 'income') {
            const hint = $('#incHint');

            if (hint) {
                hint.textContent = t.value
                    ? '≈ ' + fmt(Math.round(Number(t.value) / 12)) + ' บาท/เดือน'
                    : 'เว้นว่างได้หากยังไม่มีข้อมูล';
            }
        }

        if (t.name === 'fullname') showNameParts(t.value);

        if (['fullname', 'house'].includes(t.name)) dupCheck(form);
    });

    showNameParts(form.fullname?.value || '');

    /* พิกัดที่ตั้ง: วาง "ละติจูด, ลองจิจูด" ในช่องแรกแล้วแยกให้อัตโนมัติ + ลิงก์เปิดแผนที่ */
    const latInput = $('#latInput');
    const lngInput = $('#lngInput');
    const mapLink = $('#mapLink');
    const mapHint = $('#mapHint');

    const syncMapLink = () => {
        const lat = parseFloat(latInput?.value);
        const lng = parseFloat(lngInput?.value);
        const ok = Number.isFinite(lat) && Number.isFinite(lng);

        if (mapLink) {
            mapLink.hidden = !ok;

            if (ok) mapLink.href = `https://www.google.com/maps?q=${lat},${lng}`;
        }

        if (mapHint) mapHint.hidden = ok;
    };

    if (latInput) {
        latInput.addEventListener('input', () => {
            /* รองรับการวางพิกัดคู่จาก Google Maps เช่น "6.512345, 101.280123" */
            const pair = latInput.value.match(/^\s*(-?\d+(?:\.\d+)?)\s*[,\s]\s*(-?\d+(?:\.\d+)?)\s*$/);

            if (pair && lngInput) {
                latInput.value = pair[1];
                lngInput.value = pair[2];
            }

            syncMapLink();
        });

        lngInput?.addEventListener('input', syncMapLink);
        syncMapLink();
    }

    /* อำเภอ/ตำบลแบบต่อเนื่อง + ออกรหัส HC ให้อัตโนมัติ */
    const areas = readJson('#area-data') || [];
    const nrm = (s) => String(s || '').trim().replace(/\s+/g, '').replace(/กรงปีนัง/g, 'กรงปินัง');
    const tamName = (t) => String(t || '').replace(/\(\d+\)$/, '');
    const tamCode = (t) => {
        const m = String(t || '').match(/\((\d+)\)$/);

        return m ? Number(m[1]) : null;
    };

    const fillDistricts = () => {
        const prov = form.prov.value;
        const dists = [...new Set(areas.filter((a) => !prov || a.prov === prov).map((a) => a.dist))];
        form.dist.innerHTML =
            '<option value="">— เลือกอำเภอ —</option>' +
            dists.map((d) => `<option value="${esc(d)}">อ.${esc(d)}</option>`).join('');
    };

    const fillTambons = () => {
        const prov = form.prov.value;
        const dist = form.dist.value;
        const list = [
            ...new Set(
                areas
                    .filter((a) => (!prov || a.prov === prov) && (!dist || nrm(a.dist) === nrm(dist)))
                    .map((a) => `${a.tam}(${a.code})`)
            ),
        ];
        form.tam.innerHTML =
            '<option value="">— เลือกตำบล —</option>' +
            list.map((t) => `<option value="${esc(t)}">ต.${esc(tamName(t))} (รหัส ${tamCode(t)})</option>`).join('');
    };

    /* ตัวช่วยกรองชื่อหมู่บ้าน — ชื่อเก็บเป็นข้อความ พิมพ์อะไรก็ได้
       รายการแนะนำแคบลงตามตำบลที่เลือก เพื่อไม่ให้เสนอชื่อข้ามพื้นที่ */
    const villList = $('#villL');
    const villHint = $('#hhVillHint');
    const suggestions = readJson('#village-suggestions') || {};
    const tambonIds = readJson('#tambon-ids') || {};
    const allVillages = [...new Set(Object.values(suggestions).flat())];

    const fillVillages = () => {
        if (!villList) return;

        const label = form.tam?.value || '';
        const id = tambonIds[label] ?? tambonIds[tamName(label)];
        const scoped = id != null ? suggestions[id] || [] : [];
        const list = scoped.length ? scoped : allVillages;

        villList.innerHTML = list.map((v) => `<option value="${esc(v)}"></option>`).join('');

        if (villHint) {
            villHint.textContent = !label
                ? 'เก็บเป็นชื่อ พิมพ์ได้อิสระ — เลือกตำบลก่อนเพื่อกรองรายการแนะนำ'
                : scoped.length
                  ? `เก็บเป็นชื่อ พิมพ์ได้อิสระ — แนะนำ ${fmt(scoped.length)} ชื่อในต.${tamName(label)}`
                  : `ต.${tamName(label)} ยังไม่มีชื่อหมู่บ้านในระบบ — พิมพ์ชื่อใหม่ได้เลย`;
        }
    };

    /* หัวข้อ 4 · ปีงบ → โครงการหลัก → กิจกรรม (เลือกต่อกันเป็นชั้น) */
    const enFy = $('#enFy');
    const enPg = $('#enPg');
    const enPa = $('#enPa');
    const enPrograms = readJson('#enroll-programs') || {};
    const enActivities = readJson('#enroll-activities') || [];

    const fillPrograms = (keep) => {
        if (!enPg) return;

        const fy = enFy?.value || '';
        const list = enPrograms[fy] || [];
        enPg.disabled = !fy;
        enPg.innerHTML = fy
            ? '<option value="">— เลือกโครงการหลัก —</option>' +
              list.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('')
            : '<option value="">— เลือกปีงบก่อน —</option>';

        if (keep) enPg.value = keep;

        const hint = $('#enPgHint');

        if (hint) {
            hint.textContent = !fy
                ? 'มาจากตาราง programs'
                : list.length
                  ? `ปีงบ ${fy} มี ${fmt(list.length)} โครงการหลัก`
                  : `ปีงบ ${fy} ยังไม่มีโครงการหลัก`;
        }
    };

    const fillActivities = (keep) => {
        if (!enPa) return;

        const pg = enPg?.value || '';
        const list = enActivities.filter((a) => String(a.pg) === String(pg));
        enPa.disabled = !pg;
        enPa.innerHTML = pg
            ? '<option value="">— ไม่ระบุกิจกรรม —</option>' +
              list.map((a) => `<option value="${esc(a.pa)}">${esc(a.pa)} · ${esc(a.name)}</option>`).join('')
            : '<option value="">— เลือกโครงการหลักก่อน —</option>';

        if (keep) enPa.value = keep;

        const hint = $('#enPaHint');

        if (hint) {
            hint.textContent = !pg
                ? 'เลือกโครงการหลักก่อน'
                : list.length
                  ? 'เลือกแล้วระบบจะเพิ่มชื่อครัวเรือนนี้เข้ากิจกรรมให้ทันทีที่กดบันทึก'
                  : 'โครงการหลักนี้ยังไม่มีกิจกรรม';
        }
    };

    if (enFy) {
        /* คืนค่าที่เคยเลือกไว้ เมื่อฟอร์มถูกส่งกลับมาเพราะกรอกผิด */
        fillPrograms(enPg?.dataset.keep || enPg?.value || '');
        fillActivities(enPa?.dataset.keep || enPa?.value || '');
        enFy.addEventListener('change', () => { fillPrograms(); fillActivities(); });
        enPg?.addEventListener('change', () => fillActivities());
    }

    form.addEventListener('change', (e) => {
        const t = e.target;

        if (t.name === 'prov') {
            fillDistricts();
            fillTambons();
        }

        if (t.name === 'dist') fillTambons();

        if (['prov', 'dist', 'tam'].includes(t.name)) {
            updateHcHint(form, tamCode(form.tam.value), tamName(form.tam.value));
            fillVillages();
        }
    });

    fillVillages();

    /* ---------------------------------------------------------------------
       พิมพ์ชื่อ → ค้นครัวเรือนที่มีอยู่ในฐานข้อมูล → กดเลือกแล้วเติมข้อมูลทั่วไป
       เจตนา: ไม่ต้องกรอกที่อยู่/เบอร์/รายได้ใหม่ เมื่อคนเดิมจะเข้าร่วมกิจกรรมอื่น
       หัวข้อ «เข้าร่วมโครงการ/กิจกรรม» ไม่ถูกเติมให้ — ต้องเลือกเองทุกครั้ง
       --------------------------------------------------------------------- */
    const nameInput = $('#hhName');
    const nameList = $('#nameL');
    const foundBox = $('#hhFound');
    const known = readJson('#household-index') || [];
    const keyOf = (s) => String(s || '').trim().replace(/\s+/g, '');

    if (nameInput && nameList) {
        /* รายการแนะนำ = คำนำหน้า (ช่วยตอนเริ่มพิมพ์) + ชื่อที่มีอยู่แล้วในฐานข้อมูล */
        const prefixOptions = [...$$('#pfL option')].map((o) => o.value);
        nameList.innerHTML = [...new Set([...prefixOptions, ...known.map((h) => h.name)])]
            .map((v) => `<option value="${esc(v)}"></option>`)
            .join('');
    }

    const applyKnown = (h) => {
        /* ใช้ form.elements[...] ไม่ใช่ form[...] — ชื่อช่องบางชื่อจะทับ property
           ของ HTMLFormElement เอง (action, method, reset ฯลฯ) */
        const set = (field, value) => {
            const el = form.elements[field];

            if (el && value != null && value !== '') el.value = value;
        };

        set('house', h.house);
        set('moo', h.moo);
        set('vill', h.vill);
        set('phone', h.phone);
        set('income', h.income);
        set('lat', h.lat);
        set('lng', h.lng);

        /* พื้นที่: ยึด «ตำบล» เป็นหลัก แล้วหาอำเภอ/จังหวัดจากตารางพื้นที่
           เพราะข้อมูลเดิมมี 18 ครัวเรือนที่อำเภอไม่ตรงกับตำบล ถ้าเชื่ออำเภอที่เก็บไว้
           รายการตำบลจะถูกกรองจนไม่มีตัวที่ต้องการ แล้วช่องตำบลจะว่างแบบเงียบ ๆ */
        const area =
            areas.find((a) => `${a.tam}(${a.code})` === h.tam) ||
            areas.find((a) => nrm(a.tam) === nrm(tamName(h.tam)));

        set('prov', area?.prov || h.prov);
        fillDistricts();
        set('dist', area?.dist || h.dist);
        fillTambons();
        set('tam', h.tam);

        /* ถ้าตำบลยังว่าง แปลว่าตำบลนี้ไม่มีในตารางพื้นที่ — บอกให้รู้ ไม่ปล่อยเงียบ */
        if (h.tam && !form.elements.tam.value) {
            toast('เติมตำบลไม่ได้', 'ต.' + tamName(h.tam) + ' ไม่มีในข้อมูลพื้นที่ — เลือกเอง', 'warn');
        }

        updateHcHint(form, tamCode(form.tam.value), tamName(form.tam.value));
        fillVillages();
        showNameParts(nameInput.value);
    };

    const lookupName = () => {
        if (!foundBox || !nameInput) return;

        const key = keyOf(nameInput.value);
        const hits = key.length < 4 ? [] : known.filter((h) => keyOf(h.name) === key);

        if (!hits.length) {
            foundBox.innerHTML = '';
            delete foundBox.dataset.hits;

            return;
        }

        /* ชื่อเดียวกันอาจมีหลายครัวเรือน — ให้เลือกเองว่าหลังไหน ไม่เดาให้ */
        const rows = hits
            .map(
                (h, i) =>
                    `<button type="button" class="btn out xs" data-fill-known="${i}">` +
                    `${esc(h.hc)} · บ้านเลขที่ ${esc(h.house)} ม.${esc(h.moo ?? '-')}</button>`
            )
            .join(' ');

        foundBox.innerHTML =
            `<div class="inline-w" style="margin-bottom:14px">${svg('warn', 16, 2.2)}<div>` +
            `<b>พบชื่อนี้ในฐานข้อมูลแล้ว${hits.length > 1 ? ` ${fmt(hits.length)} รายการ` : ''}</b> — ` +
            `กดเพื่อเติมข้อมูลทั่วไป (ที่อยู่ · เบอร์โทร · รายได้ · พิกัด) ` +
            `ส่วนโครงการ/กิจกรรมให้เลือกเองด้านล่าง` +
            `<div style="margin-top:8px;display:flex;gap:7px;flex-wrap:wrap">${rows}</div>` +
            `<div style="margin-top:8px;font-size:11.5px;color:var(--ink-3)">` +
            `กดบันทึกต่อจะเป็นการสร้างครัวเรือน<b>รายการใหม่</b> — ` +
            `ถ้าต้องการเพิ่มคนเดิมเข้ากิจกรรมโดยไม่สร้างซ้ำ ให้กดปุ่มแก้ไขของรายการนั้นแทน` +
            `</div></div></div>`;

        foundBox.dataset.hits = JSON.stringify(hits);
    };

    foundBox?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-fill-known]');

        if (!btn) return;

        const hits = JSON.parse(foundBox.dataset.hits || '[]');
        const hit = hits[Number(btn.dataset.fillKnown)];

        if (!hit) return;

        applyKnown(hit);

        /* จำไว้ว่าหมายถึงครัวเรือนหลังไหน — ฝั่งเซิร์ฟเวอร์จะได้ไม่สร้างรายการใหม่ */
        const hidden = $('#hhExistingHc');

        if (hidden) hidden.value = hit.hc;

        markUsingExisting(hit);
        toast('ใช้ครัวเรือนเดิม ' + hit.hc, 'กดบันทึกจะเพิ่มเฉพาะกิจกรรม ไม่สร้างรายการใหม่', 'ok');
    });

    /** แสดงให้เห็นชัดว่ากำลังจะบันทึกทับครัวเรือนเดิม ไม่ใช่สร้างใหม่ */
    function markUsingExisting(hit) {
        const submit = form.querySelector('.dw-f .btn.pri');

        if (submit) {
            submit.innerHTML =
                `${svg('chk', 15, 2.4)} บันทึกการเข้าร่วมของ ${esc(hit.hc)}`;
        }
    }

    /** เลิกผูกกับครัวเรือนเดิม — กลับไปโหมดสร้างรายการใหม่ */
    function clearUsingExisting() {
        const hidden = $('#hhExistingHc');

        if (!hidden || !hidden.value) return;

        hidden.value = '';

        const submit = form.querySelector('.dw-f .btn.pri');

        if (submit) submit.innerHTML = `${svg('chk', 15, 2.4)} บันทึกครัวเรือน`;
    }

    nameInput?.addEventListener('input', () => {
        /* พิมพ์แก้ชื่อ = ไม่ได้หมายถึงคนที่เลือกไว้แล้ว ต้องล้างการผูกทิ้ง
           ไม่งั้นจะไปบันทึกให้ครัวเรือนผิดคนโดยที่หน้าจอไม่บอกอะไร */
        clearUsingExisting();
        lookupName();
    });
    nameInput?.addEventListener('change', lookupName);
    lookupName();
}

function updateHcHint(form, code, tam) {
    const hint = $('#hchint');
    const nextHc = form.dataset.nextSeq;

    if (!hint || !form.hc || form.dataset.editing === '1') return;

    if (code) {
        const hc = 'PY67' + String(code).padStart(2, '0') + nextHc;
        form.hc.value = hc;
        hint.innerHTML = `PY · 67 · <b>${String(code).padStart(2, '0')}</b> (ต.${esc(tam)}) · ลำดับ ${nextHc}`;
    } else {
        form.hc.value = '';
        hint.textContent = 'เลือกตำบลเพื่อออกรหัส HC';
    }
}

/* เตือนเมื่อชื่อ + บ้านเลขที่ ตรงกับครัวเรือนที่มีอยู่แล้ว */
/**
 * แสดงผลการแยก «คำนำหน้า ชื่อ - สกุล» สด ๆ ใต้ช่องกรอก
 * ผู้ใช้จึงเห็นทันทีว่าระบบตัดคำถูกไหม ก่อนกดบันทึก
 *
 * รายการคำนำหน้าอ่านจาก <datalist id="pfL"> เพื่อไม่ให้มีรายชื่อซ้ำสองที่
 * (ฝั่ง PHP ใช้ Thai::PREFIXES ชุดเดียวกัน)
 */
function showNameParts(value) {
    const hint = $('#hhNameHint');

    if (!hint) return;

    const raw = String(value || '').trim();

    if (!raw) {
        hint.textContent = 'พิมพ์ต่อกันได้เลย — ระบบแยกคำนำหน้า ชื่อ และนามสกุลให้เอง';

        return;
    }

    /* เทียบคำนำหน้าที่ยาวที่สุดก่อน ไม่ให้ «นางสาว» ถูกตัดเป็น «นาง» */
    const prefixes = [...$$('#pfL option')].map((o) => o.value).sort((a, b) => b.length - a.length);
    const pf = prefixes.find((p) => raw.startsWith(p)) || '';
    const rest = raw.slice(pf.length).trim().split(/\s+/).filter(Boolean);
    const fn = rest[0] || '';
    const ln = rest.slice(1).join(' ');

    const missing = [];

    if (!pf) missing.push('คำนำหน้า');

    if (!fn) missing.push('ชื่อ');

    if (!ln) missing.push('นามสกุล');

    hint.innerHTML = missing.length
        ? `ยังขาด <b>${missing.join(' · ')}</b> — รูปแบบที่ถูก: นางสาวซูรียะห์ มูซอ`
        : `คำนำหน้า <b>${esc(pf)}</b> · ชื่อ <b>${esc(fn)}</b> · นามสกุล <b>${esc(ln)}</b>`;
}

function dupCheck(form) {
    const box = $('#dupw');
    const list = readJson('#household-index') || [];

    if (!box) return;

    const nrm = (s) => String(s || '').trim().replace(/\s+/g, '');
    const name = form.fullname?.value || '';
    const house = form.house.value;
    const current = form.dataset.hc || '';

    if (!name.trim() || !house) {
        box.innerHTML = '';

        return;
    }

    const hit = list.filter((h) => h.hc !== current && nrm(h.name) === nrm(name) && nrm(h.house) === nrm(house));

    box.innerHTML = hit.length
        ? `<div class="inline-w" style="margin-bottom:18px">${svg('warn', 16, 2.2)}
           <div><b>พบครัวเรือนที่ข้อมูลตรงกันแล้ว</b> ${hit.map((h) => `<span class="code">${esc(h.hc)}</span>`).join(' · ')}
           — ตรวจสอบก่อนบันทึกเพื่อไม่ให้เกิดรายการซ้ำ</div></div>`
        : '';
}

/* ------------------------------------------------ ฟอร์มกิจกรรม (ลิ้นชัก) --- */
function initActivityForm() {
    const form = $('#pjf');

    if (!form) return;

    const phone = $('#lecturerPhone');
    const idCard = $('#lecturerIdCard');
    const idHint = $('#idCardHint');

    /* ชื่อโครงการหลัก: dropdown จากตาราง programs เปลี่ยนตามปีงบที่เลือก */
    const year = $('#pjFy');
    const yearNew = $('#pjFyNew');
    const program = $('#pjProgram');
    const programNew = $('#pjProgramNew');
    const programHint = $('#pjProgramHint');
    const programYearTag = $('#pjProgramYear');
    const byYear = readJson('#programs-by-year') || {};

    const NEW = '__new';

    /* เติมตัวเลือกโครงการหลักของปีงบที่ระบุ */
    const fillPrograms = (fy) => {
        const list = byYear[fy] || [];

        if (program) {
            program.innerHTML =
                (list.length
                    ? list.map((p) => `<option value="${p.id}">${esc(p.name)}</option>`).join('')
                    : '<option value="" disabled selected>— ปีงบนี้ยังไม่มีโครงการหลัก —</option>') +
                `<option value="${NEW}">＋ เพิ่มโครงการหลักใหม่…</option>`;

            if (!list.length) program.value = NEW;
        }

        if (programYearTag) programYearTag.textContent = 'ปีงบ ' + fy;

        if (programHint) {
            programHint.textContent = list.length
                ? `เลือกจากตาราง programs — ปีงบ ${fy} มี ${list.length} โครงการหลัก`
                : `ปีงบ ${fy} ยังไม่มีโครงการหลัก — พิมพ์ชื่อใหม่ในช่องด้านบน`;
        }

        toggleProgramNew();
    };

    /* โผล่/ซ่อนช่องพิมพ์ชื่อโครงการใหม่ */
    const toggleProgramNew = () => {
        if (!programNew) return;

        const show = program?.value === NEW;
        programNew.hidden = !show;

        if (show && document.activeElement === program) programNew.focus();
    };

    program?.addEventListener('change', toggleProgramNew);

    year?.addEventListener('change', () => {
        const isNewYear = year.value === NEW;

        if (yearNew) {
            yearNew.hidden = !isNewYear;

            if (isNewYear) yearNew.focus();
        }

        if (isNewYear) {
            /* ปีงบใหม่ยังไม่มีโครงการหลัก → บังคับโหมดพิมพ์ชื่อใหม่ */
            fillPrograms('__none');

            if (programYearTag) programYearTag.textContent = 'ปีงบใหม่';

            if (programHint) programHint.textContent = 'กรอกปีงบใหม่ด้านบน แล้วพิมพ์ชื่อโครงการหลักของปีนั้น';

            return;
        }

        fillPrograms(year.value);
    });

    toggleProgramNew();

    /* เบอร์โทร: 0xx-xxx-xxxx */
    phone?.addEventListener('input', () => {
        const d = phone.value.replace(/\D/g, '').slice(0, 10);
        phone.value = d.length > 6 ? `${d.slice(0, 3)}-${d.slice(3, 6)}-${d.slice(6)}` : d.length > 3 ? `${d.slice(0, 3)}-${d.slice(3)}` : d;
    });

    /* เลขประจำตัวประชาชน: x-xxxx-xxxxx-xx-x + ตรวจหลักสุดท้ายให้ทันที */
    idCard?.addEventListener('input', () => {
        const d = idCard.value.replace(/\D/g, '').slice(0, 13);
        const parts = [d.slice(0, 1), d.slice(1, 5), d.slice(5, 10), d.slice(10, 12), d.slice(12, 13)];
        idCard.value = parts.filter((p) => p !== '').join('-');

        if (!idHint) return;

        if (d.length === 0) {
            idHint.textContent = '13 หลัก · ระบบตรวจหลักสุดท้ายให้';
            idHint.style.color = '';
        } else if (d.length < 13) {
            idHint.textContent = `กรอกแล้ว ${d.length}/13 หลัก`;
            idHint.style.color = '';
        } else if (validCitizenId(d)) {
            idHint.textContent = '✓ เลขประจำตัวประชาชนถูกต้อง';
            idHint.style.color = 'var(--good-ink)';
        } else {
            idHint.textContent = '✕ หลักตรวจสอบไม่ถูกต้อง — ทบทวนเลขอีกครั้ง';
            idHint.style.color = 'var(--critical-ink)';
        }
    });
}

/* ตรวจหลักสุดท้ายของเลขประจำตัวประชาชนไทย (ตรงกับ Thai::isValidCitizenId ฝั่ง PHP) */
function validCitizenId(digits) {
    if (!/^\d{13}$/.test(digits)) return false;

    let sum = 0;

    for (let i = 0; i < 12; i++) sum += Number(digits[i]) * (13 - i);

    return (11 - (sum % 11)) % 10 === Number(digits[12]);
}

/* --------------------------------- หน้าต่างเลือกครัวเรือนเข้ากิจกรรม --- */
function initPicker() {
    const list = $('#pkList');

    if (!list) return;

    const q = $('#pkq');
    const vill = $('#pkv');
    const flag = $('#pkf');
    const count = $('#pkCnt');

    const apply = () => {
        const term = (q?.value || '').toLowerCase();
        const v = vill?.value || '';
        const f = flag?.value || '';
        let shown = 0;

        $$('label[data-hc]', list).forEach((row) => {
            const okTerm = !term || row.dataset.search.includes(term);
            const okVill = !v || row.dataset.vill === v;
            const okFlag =
                !f ||
                (f === 'ok' && row.dataset.blocked !== '1') ||
                (f === 'np' && row.dataset.enrolled === '0') ||
                (f === 'y' && row.dataset.income === '1');
            const show = okTerm && okVill && okFlag;
            row.hidden = !show;

            if (show) shown++;
        });

        const empty = $('#pkEmpty');

        if (empty) empty.hidden = shown > 0;

        updatePickCount();
    };

    const updatePickCount = () => {
        const n = $$('input[name="hcs[]"]:checked', list).length;

        if (count) count.textContent = `เลือก ${fmt(n)} รายการ`;
    };

    q?.addEventListener('input', apply);
    vill?.addEventListener('change', apply);
    flag?.addEventListener('change', apply);
    list.addEventListener('change', updatePickCount);
    apply();
}

/* ------------------------------------------------------------ ตัวช่วย --- */
function readJson(selector) {
    const tag = $(selector);

    if (!tag) return null;

    try {
        return JSON.parse(tag.textContent || 'null');
    } catch (e) {
        return null;
    }
}

/* -------------------------------------------------------------- เริ่ม --- */
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
    initTips();
    initOverlays();
    initConfirm();
    initSelection();
    initFilters();
    initHouseholdForm();
    initActivityForm();
    initPicker();
    flashToasts();
});
