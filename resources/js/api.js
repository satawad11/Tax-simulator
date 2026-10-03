const API_BASE = '/api/v1';

/**
 * One session for the whole product.
 *
 * Milestone 09.1 — the public site and the admin console kept their tokens under two different
 * keys in the same session storage, so an administrator who signed in on the site had to sign in
 * again at /admin. There was no security difference between the two: same storage, same
 * lifetime. They are now one key, and the old two are read once and migrated so a session open
 * across the change is not thrown away.
 */
const TOKEN_KEY = 'tax-simulator.session-token';
const LEGACY_TOKEN_KEYS = ['tax-simulator.member-token', 'tax-simulator.admin-token'];

export class ApiError extends Error {
    constructor(status, message, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

export function authToken() {
    const current = window.sessionStorage.getItem(TOKEN_KEY);
    if (current) return current;

    for (const legacy of LEGACY_TOKEN_KEYS) {
        const value = window.sessionStorage.getItem(legacy);
        if (value) {
            window.sessionStorage.setItem(TOKEN_KEY, value);
            window.sessionStorage.removeItem(legacy);

            return value;
        }
    }

    return null;
}

export function setAuthToken(value) {
    // Signing out must clear the old keys too, or a stale one would be migrated back in.
    LEGACY_TOKEN_KEYS.forEach((legacy) => window.sessionStorage.removeItem(legacy));
    if (value) window.sessionStorage.setItem(TOKEN_KEY, value);
    else window.sessionStorage.removeItem(TOKEN_KEY);
}

export async function api(path, options = {}) {
    const token = authToken();
    const headers = { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...options.headers };
    if (token) headers.Authorization = `Bearer ${token}`;
    let response;
    try {
        response = await fetch(`${API_BASE}${path}`, { ...options, headers });
    } catch {
        throw new ApiError(0, 'ไม่สามารถเชื่อมต่อระบบได้ โปรดลองอีกครั้ง');
    }
    const payload = response.status === 204 ? null : await response.json().catch(() => null);
    if (!response.ok) {
        const messages = { 401: 'กรุณาเข้าสู่ระบบ', 403: 'บัญชีนี้ไม่มีสิทธิ์ทำรายการ', 404: 'ไม่พบข้อมูลที่ต้องการ', 409: 'รายการนี้เสร็จสิ้นแล้วและแก้ไขไม่ได้', 422: 'ข้อมูลบางรายการไม่ถูกต้อง', 429: 'ส่งคำขอบ่อยเกินไป โปรดรอสักครู่แล้วลองใหม่', 500: 'ระบบขัดข้องชั่วคราว โปรดลองอีกครั้ง', 503: 'ระบบยังไม่พร้อมให้บริการ โปรดลองอีกครั้งภายหลัง' };
        throw new ApiError(response.status, messages[response.status] || payload?.message || 'เกิดข้อผิดพลาด', payload?.errors || {});
    }
    return payload?.data ?? payload;
}

export function errorText(error) {
    const fields = Object.entries(error.errors || {}).flatMap(([path, messages]) => (Array.isArray(messages) ? messages : [messages]).map((message) => `${fieldLabel(path)}: ${validationMessage(message)}`));
    return [error.message, ...fields].filter(Boolean).join('\n');
}

export function fieldNameFromPath(path) {
    const key = String(path).split('.').at(-1);
    return {
        birth_date: 'birth_date', marital_status: 'marital_status', relation_type: 'relation_type',
        child_type: 'child_type', birth_order: 'birth_order', eligible: 'eligible', income_type: 'income_type',
        income_subtype: 'income_subtype', expense_activity: 'expense_activity', holding_years: 'holding_years',
        expense_method_selection: 'expense_method_selection', actual_expense: 'actual_expense', tax_treatment: 'tax_treatment',
        gross_amount: 'gross_amount', exempt_amount: 'exempt_amount', amount: 'withholding_amount',
    }[key] || key;
}

function fieldLabel(path) {
    const index = Number(String(path).match(/\.(\d+)\./)?.[1] || 0) + 1;
    const key = String(path).split('.').at(-1);
    const labels = {
        tax_year: 'ปีภาษี', form_code: 'แบบภาษี', birth_date: 'วันเดือนปีเกิด', marital_status: 'สถานภาพ',
        relation_type: `ความสัมพันธ์ของผู้พึ่งพาคนที่ ${index}`, child_type: `ประเภทบุตรคนที่ ${index}`,
        birth_order: `ลำดับบุตรคนที่ ${index}`, eligible: `การยืนยันสิทธิผู้พึ่งพาคนที่ ${index}`,
        income_type: `ประเภทเงินได้รายการที่ ${index}`, income_subtype: `ประเภทย่อยของเงินได้รายการที่ ${index}`,
        expense_activity: `กิจกรรมตามตารางที่ 2 ของเงินได้รายการที่ ${index}`, holding_years: `จำนวนปีถือครองของเงินได้รายการที่ ${index}`,
        expense_method_selection: `วิธีหักค่าใช้จ่ายของเงินได้รายการที่ ${index}`, actual_expense: `ค่าใช้จ่ายจริงของเงินได้รายการที่ ${index}`,
        tax_treatment: `วิธีคำนวณเงินได้รายการที่ ${index}`, gross_amount: `เงินได้ทั้งปีรายการที่ ${index}`,
        exempt_amount: `เงินได้ที่ได้รับยกเว้นรายการที่ ${index}`, input_amount: `จำนวนเงินรายการที่ ${index}`,
        donation_code: `ประเภทเงินบริจาครายการที่ ${index}`, type: `ประเภทภาษีที่ชำระไว้รายการที่ ${index}`,
    };
    return labels[key] || 'ข้อมูลที่กรอก';
}

function validationMessage(message) {
    const text = String(message || 'โปรดตรวจสอบข้อมูล');
    const translations = [
        [/DUPLICATE_ALLOWANCE_CODE/i, 'พบรายการค่าลดหย่อนซ้ำ กรุณาแก้ไขรายการเดิม'],
        [/DUPLICATE_DONATION_CODE/i, 'พบประเภทเงินบริจาคซ้ำ กรุณากรอกยอดรวมรายปีเพียงรายการเดียว'],
        [/DUPLICATE_PREPAYMENT_TYPE/i, 'พบยอด ภ.ง.ด.93/94 ซ้ำ กรุณากรอกยอดรวมรายปีเพียงรายการเดียว'],
        [/DUPLICATE_DEPENDENT/i, 'พบผู้พึ่งพาที่มีบทบาทหรือลำดับเดียวกันซ้ำ'],
        [/required/i, 'จำเป็นต้องกรอกข้อมูลนี้'],
        [/must not exceed gross amount/i, 'ยอดยกเว้นต้องไม่เกินเงินได้ทั้งปี'],
        [/actual expense must not exceed/i, 'ค่าใช้จ่ายจริงต้องไม่เกินเงินได้หลังหักส่วนยกเว้น'],
        [/state which one applies|state the number|takes its expense rate/i, 'โปรดเลือกรายละเอียดที่แบบกำหนดให้ครบ'],
        [/must be percentage or actual/i, 'โปรดเลือกหักแบบเหมาหรือหักตามค่าใช้จ่ายจริง'],
        [/not supported|not been verified|simulation is not available/i, 'รายการนี้ยังไม่รองรับการคำนวณอัตโนมัติในรุ่นปัจจุบัน'],
        [/must be a valid date|date format/i, 'โปรดกรอกวันที่ให้ถูกต้อง'],
    ];
    const translated = translations.find(([pattern]) => pattern.test(text))?.[1];
    if (translated) return translated;

    /*
     * A backend validation message may be prefixed with the internal constant that raised it —
     * "REQUIRED_CHILD_BIRTH_ORDER: บุตรชอบด้วยกฎหมายต้องระบุลำดับบุตร". The sentence after the
     * colon is written for a reader; the constant before it is for us. Show the sentence.
     */
    return text.replace(/^[A-Z][A-Z0-9_]{3,}:\s*/, '');
}
