/**
 * Milestone 09.1 — the audit trail, written for a person.
 *
 * The overview listed raw constants — `CONTENT_UPDATED · Deleted unused category tax-knowledge-m8
 * · ผู้ดูแลระบบทดสอบ` — run together with separators and wrapped mid-phrase. That is the shape the
 * database stores, not the shape a reader wants: the code is for us and the English summary was
 * written for a log file.
 *
 * Each action is given a Thai phrase, a kind, and an icon, so the feed reads as a sentence about
 * what happened rather than a dump of an enum. Nothing here changes what is recorded; the record
 * is evidence and stays exactly as written.
 */

export const ACTION_LABELS = {
    CONTENT_CREATED: { text: 'สร้างเนื้อหา', kind: 'create' },
    CONTENT_UPDATED: { text: 'แก้ไขเนื้อหา', kind: 'update' },
    CONTENT_PUBLISHED: { text: 'เผยแพร่เนื้อหา', kind: 'publish' },
    CONTENT_UNPUBLISHED: { text: 'ยกเลิกเผยแพร่เนื้อหา', kind: 'update' },
    CONTENT_ARCHIVED: { text: 'จัดเก็บเนื้อหา', kind: 'archive' },
    CONTENT_DELETED: { text: 'ลบเนื้อหา', kind: 'delete' },
    RULE_VERSION_CREATED: { text: 'สร้างชุดกฎภาษี', kind: 'create' },
    RULE_VERSION_CLONED: { text: 'ทำสำเนาชุดกฎภาษี', kind: 'create' },
    RULE_VERSION_VALIDATED: { text: 'ตรวจสอบชุดกฎภาษี', kind: 'check' },
    RULE_VERSION_PUBLISHED: { text: 'เผยแพร่ชุดกฎภาษี', kind: 'publish' },
    RULE_VERSION_ARCHIVED: { text: 'จัดเก็บชุดกฎภาษี', kind: 'archive' },
    TAX_RULE_CREATED: { text: 'เพิ่มกฎในชุดร่าง', kind: 'create' },
    TAX_RULE_UPDATED: { text: 'แก้ไขกฎในชุดร่าง', kind: 'update' },
    TAX_RULE_DELETED: { text: 'ลบกฎออกจากชุดร่าง', kind: 'delete' },
    TAX_SOURCE_CREATED: { text: 'เพิ่มเอกสารอ้างอิง', kind: 'create' },
    TAX_SOURCE_UPDATED: { text: 'แก้ไขเอกสารอ้างอิง', kind: 'update' },
    TAX_SOURCE_ARCHIVED: { text: 'ปิดใช้งานเอกสารอ้างอิง', kind: 'archive' },
};

/**
 * Each kind gets a tint and a glyph, so the feed is scannable without reading every line.
 *
 * `glyph` names an icon in `resources/icons.json` rather than carrying its own copy of the path —
 * three of these were byte-identical to shapes the Blade component already drew.
 */
export const ACTION_KINDS = {
    create: { tone: 'bg-blue-100 text-blue-700', glyph: 'plus' },
    update: { tone: 'bg-amber-100 text-amber-700', glyph: 'pencil' },
    publish: { tone: 'bg-emerald-100 text-emerald-700', glyph: 'check' },
    archive: { tone: 'bg-slate-200 text-slate-600', glyph: 'archive' },
    delete: { tone: 'bg-rose-100 text-rose-700', glyph: 'close' },
    check: { tone: 'bg-violet-100 text-violet-700', glyph: 'shield' },
};

export function actionLabel(action) {
    return ACTION_LABELS[action] ?? { text: action, kind: 'update' };
}

/**
 * The entity a record is about, named in Thai.
 *
 * Only the kinds `admin_audit_logs` actually records appear here; the raw value is shown when a
 * new one turns up, which is better than hiding an entry we do not yet have wording for.
 */
const ENTITIES = {
    content_post: 'เนื้อหา',
    content_category: 'หมวดหมู่',
    content_tag: 'แท็ก',
    tax_rule_version: 'ชุดกฎภาษี',
    tax_bracket: 'ขั้นอัตราภาษี',
    expense_rule: 'กฎค่าใช้จ่าย',
    allowance_rule: 'กฎค่าลดหย่อน',
    allowance_cap_group: 'กลุ่มเพดานค่าลดหย่อน',
    donation_rule: 'กฎเงินบริจาค',
    recommendation_rule: 'กฎคำแนะนำ',
    tax_source: 'เอกสารอ้างอิง',
};

export const entityLabel = (entity) => ENTITIES[entity] ?? entity;

/** Full date and time, because an audit entry is evidence and "3 hours ago" is not. */
export const auditTimestamp = (value) => value
    ? new Intl.DateTimeFormat('th-TH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : '—';
