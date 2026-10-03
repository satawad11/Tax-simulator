import { api, authToken } from './api.js';

export async function persistGuestState(state) {
    if (!authToken()) throw new Error('LOGIN_REQUIRED');
    const created = await api('/tax-returns', { method: 'POST', body: JSON.stringify({ tax_year: state.tax_year, form_code: state.form_code, name: state.simulation_name || `แบบจำลอง ${state.form_code}` }) });
    const id = created.id;
    if (state.profile) await api(`/tax-returns/${id}/profile`, { method: 'PUT', body: JSON.stringify({ ...state.profile, filing_status: state.filing_status || null }) });
    if (state.spouse) await api(`/tax-returns/${id}/spouse`, { method: 'PUT', body: JSON.stringify({ ...state.spouse, filing_status: state.filing_status || null }) });
    const collections = [
        ['dependents', state.dependents],
        ['incomes', state.incomes],
        ['allowances', state.allowances.map((item) => ({ code: item.code, input_amount: item.amount }))],
        // ใบแนบ ข้อ 13 และ ข้อ 20. The affirmation is saved with the amount: without it the return
        // could not recalculate itself, because the guard refuses a positive amount that lacks it.
        ['income-exemptions', (state.income_exemptions || []).map((item) => ({ code: item.code,
            input_amount: item.amount, declarations_confirmed: Boolean(item.declarations_confirmed) }))],
        ['donations', state.donations.map((item) => ({ donation_code: item.code, input_amount: item.amount }))],
        ['withholdings', state.withholdings],
    ];
    for (const [path, items] of collections) {
        for (const item of items) await api(`/tax-returns/${id}/${path}`, { method: 'POST', body: JSON.stringify(item) });
    }
    return id;
}

export async function persistExistingState(state) {
    const id = state.member_return_id;
    await api(`/tax-returns/${id}`, { method: 'PATCH', body: JSON.stringify({ name: state.simulation_name || `แบบจำลอง ${state.form_code}`, current_step: state.current_step || 7 }) });
    const { id: profileId, ...profile } = state.profile;
    await api(`/tax-returns/${id}/profile`, { method: 'PUT', body: JSON.stringify({ ...profile, filing_status: state.filing_status || profile.filing_status || null }) });
    if (state.spouse) {
        const { id: spouseId, ...spouse } = state.spouse;
        await api(`/tax-returns/${id}/spouse`, { method: 'PUT', body: JSON.stringify({ ...spouse, filing_status: state.filing_status || spouse.filing_status || null }) });
    }
    else if (state._original?.spouse) await api(`/tax-returns/${id}/spouse`, { method: 'DELETE' });
    // [state key, url segment, how the row is written]. The url segment is carried explicitly
    // because `income_exemptions` is addressed as `income-exemptions`, and deriving one from the
    // other would be a rule that holds for exactly one entry.
    const specifications = [
        ['dependents', 'dependents', (item) => item],
        ['incomes', 'incomes', (item) => item],
        ['allowances', 'allowances', (item) => ({ code: item.code, input_amount: item.amount })],
        ['income_exemptions', 'income-exemptions', (item) => ({ code: item.code,
            input_amount: item.amount, declarations_confirmed: Boolean(item.declarations_confirmed) })],
        ['donations', 'donations', (item) => ({ donation_code: item.code, input_amount: item.amount })],
        ['withholdings', 'withholdings', (item) => item],
    ];
    for (const [collection, segment, map] of specifications) {
        const current = state[collection] || [];
        const originalIds = state._original?.[collection] || [];
        for (const removedId of originalIds.filter((originalId) => !current.some((item) => Number(item.id) === Number(originalId)))) {
            await api(`/tax-returns/${id}/${segment}/${removedId}`, { method: 'DELETE' });
        }
        for (const item of current) {
            const clean = Object.fromEntries(Object.entries(item).filter(([key]) => key !== 'id'));
            const path = item.id ? `/tax-returns/${id}/${segment}/${item.id}` : `/tax-returns/${id}/${segment}`;
            await api(path, { method: item.id ? 'PATCH' : 'POST', body: JSON.stringify(map(clean)) });
        }
    }
    return id;
}
