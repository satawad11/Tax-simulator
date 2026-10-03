const PREFIX = 'tax-simulator.guest.';

// `income_exemptions` is ใบแนบ ข้อ 13 and ข้อ 20 — deducted after expenses rather than in the
// allowance block, so it is its own list and never folded into `allowances`.
export const emptyState = (formCode) => ({ tax_year: null, form_code: formCode, profile: { birth_date: '', marital_status: 'single' }, spouse: null, dependents: [], incomes: [], allowances: [], income_exemptions: [], donations: [], withholdings: [] });
export const loadState = (formCode) => {
    try { return { ...emptyState(formCode), ...JSON.parse(window.sessionStorage.getItem(`${PREFIX}${formCode}`) || '{}'), form_code: formCode }; }
    catch { return emptyState(formCode); }
};
export const saveState = (state) => window.sessionStorage.setItem(`${PREFIX}${state.form_code}`, JSON.stringify(state));
export const clearState = (formCode) => window.sessionStorage.removeItem(`${PREFIX}${formCode}`);
export const setGuestResult = (result) => window.sessionStorage.setItem(`${PREFIX}result`, JSON.stringify(result));
export const guestResult = () => JSON.parse(window.sessionStorage.getItem(`${PREFIX}result`) || 'null');
