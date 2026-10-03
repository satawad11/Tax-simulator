/**
 * The editable fields of each draft rule entity, as the console needs to render them.
 *
 * The authority for what may be written is `DraftRuleRegistry` on the server — this file only
 * describes how to *ask* for those fields. The two are kept in step by
 * `AdminDraftRuleFieldParityTest`, which fails if the registry gains or loses a field that this
 * spec does not, so the console can never quietly offer a field the API rejects, nor omit one it
 * requires.
 *
 * `required` here mirrors the registry's `required` on create; a PATCH sends only what changed.
 */

const SOURCE_REFERENCE = {
    name: 'source_reference',
    label: 'แหล่งอ้างอิง',
    type: 'text',
    help: 'เอกสารและหน้าที่เป็นที่มาของค่านี้',
};

const ACTIVE = { name: 'active', label: 'เปิดใช้งาน', type: 'boolean' };

export const EXPENSE_METHODS = ['fixed', 'percentage', 'percentage_limit', 'actual', 'percentage_or_actual', 'tiered_or_actual'];
export const ALLOWANCE_METHODS = ['fixed', 'actual', 'percentage_limit'];
export const PERCENTAGE_BASES = ['GROSS_INCOME', 'GROSS_AFTER_EXEMPTION', 'INCOME_AFTER_EXPENSE'];

/** Thai wording for the resource itself, used in headings and confirmations. */
export const RESOURCE_LABELS = {
    'tax-brackets': 'ขั้นอัตราภาษี',
    'expense-rules': 'กฎค่าใช้จ่าย',
    'allowance-rules': 'กฎค่าลดหย่อน',
    'allowance-cap-groups': 'กลุ่มเพดานค่าลดหย่อนรวม',
    'donation-rules': 'กฎเงินบริจาค',
    'recommendation-rules': 'กฎคำแนะนำ',
};

export const RULE_FIELDS = {
    'tax-brackets': [
        { name: 'sort_order', label: 'ลำดับขั้น', type: 'integer', required: true },
        { name: 'min_amount', label: 'เงินได้สุทธิตั้งแต่', type: 'decimal', required: true },
        { name: 'max_amount', label: 'ถึง', type: 'decimal', help: 'เว้นว่างสำหรับขั้นสูงสุด' },
        { name: 'rate', label: 'อัตรา (%)', type: 'decimal', required: true },
        SOURCE_REFERENCE,
    ],
    'expense-rules': [
        { name: 'code', label: 'รหัสกฎ', type: 'text', required: true },
        { name: 'income_type_id', label: 'ประเภทเงินได้', type: 'reference', reference: 'income-types', required: true },
        { name: 'income_subtype', label: 'ประเภทย่อย', type: 'text' },
        { name: 'expense_activity', label: 'กิจกรรมตามตารางที่ 2', type: 'text' },
        { name: 'holding_years_min', label: 'ถือครองตั้งแต่ (ปี)', type: 'integer' },
        { name: 'holding_years_max', label: 'ถือครองถึง (ปี)', type: 'integer' },
        { name: 'expense_group', label: 'กลุ่มค่าใช้จ่าย', type: 'text' },
        { name: 'method', label: 'วิธีคำนวณ', type: 'select', options: EXPENSE_METHODS, required: true },
        { name: 'percentage', label: 'อัตรา (%)', type: 'decimal' },
        { name: 'maximum_amount', label: 'เพดาน', type: 'decimal' },
        { name: 'fixed_amount', label: 'จำนวนคงที่', type: 'decimal' },
        { name: 'minimum_amount', label: 'ขั้นต่ำ', type: 'decimal' },
        ACTIVE,
        { ...SOURCE_REFERENCE, required: true },
    ],
    'allowance-rules': [
        { name: 'code', label: 'รหัสกฎ', type: 'text', required: true },
        { name: 'allowance_type_id', label: 'ประเภทค่าลดหย่อน', type: 'reference', reference: 'allowance-types', required: true },
        { name: 'method', label: 'วิธีคำนวณ', type: 'select', options: ALLOWANCE_METHODS, required: true },
        { name: 'percentage', label: 'อัตรา (%)', type: 'decimal' },
        { name: 'percentage_base', label: 'ฐานคำนวณอัตรา', type: 'select', options: PERCENTAGE_BASES },
        { name: 'maximum_amount', label: 'เพดาน', type: 'decimal' },
        { name: 'fixed_amount', label: 'จำนวนคงที่', type: 'decimal' },
        { name: 'minimum_amount', label: 'ขั้นต่ำ', type: 'decimal' },
        ACTIVE,
        { ...SOURCE_REFERENCE, required: true },
    ],
    'allowance-cap-groups': [
        { name: 'code', label: 'รหัสกลุ่ม', type: 'text', required: true },
        { name: 'name', label: 'ชื่อกลุ่ม', type: 'text', required: true },
        { name: 'maximum_amount', label: 'เพดานรวม', type: 'decimal' },
        { name: 'percentage', label: 'อัตรา (%)', type: 'decimal' },
        { name: 'percentage_base', label: 'ฐานคำนวณอัตรา', type: 'select', options: PERCENTAGE_BASES },
        { name: 'member_allowance_type_ids', label: 'ประเภทค่าลดหย่อนในกลุ่ม', type: 'reference_many', reference: 'allowance-types' },
        ACTIVE,
        { ...SOURCE_REFERENCE, required: true },
    ],
    'donation-rules': [
        { name: 'code', label: 'รหัสกฎ', type: 'text', required: true },
        { name: 'name', label: 'ชื่อรายการ', type: 'text', required: true },
        { name: 'donation_type', label: 'ประเภท', type: 'select', options: ['special', 'general'], required: true },
        { name: 'multiplier', label: 'ตัวคูณ', type: 'decimal', required: true },
        { name: 'max_percentage', label: 'เพดาน (% ของฐาน)', type: 'decimal', required: true },
        ACTIVE,
        { ...SOURCE_REFERENCE, required: true },
    ],
    'recommendation-rules': [
        { name: 'code', label: 'รหัสกฎ', type: 'text', required: true },
        { name: 'type', label: 'ประเภท', type: 'text', required: true },
        { name: 'priority', label: 'ความสำคัญ', type: 'select', options: ['high', 'medium', 'low'], required: true },
        { name: 'title', label: 'หัวข้อ', type: 'text', required: true },
        { name: 'message_template', label: 'ข้อความ', type: 'textarea', required: true },
        { name: 'action_type', label: 'ประเภทการกระทำ', type: 'text' },
        { name: 'conditions', label: 'เงื่อนไข (JSON)', type: 'json' },
        ACTIVE,
        SOURCE_REFERENCE,
    ],
};

/** The columns a rule table shows; the rest are visible when a row is opened for editing. */
export const RULE_COLUMNS = {
    'tax-brackets': ['sort_order', 'min_amount', 'max_amount', 'rate'],
    'expense-rules': ['code', 'method', 'percentage', 'maximum_amount', 'active'],
    'allowance-rules': ['code', 'method', 'percentage', 'maximum_amount', 'active'],
    'allowance-cap-groups': ['code', 'name', 'maximum_amount', 'percentage', 'active'],
    'donation-rules': ['code', 'name', 'donation_type', 'multiplier', 'max_percentage', 'active'],
    'recommendation-rules': ['code', 'type', 'priority', 'title', 'active'],
};
