# Simulator Flow

1. Select PND90 or PND91. The browser loads available tax years, forms, income types, subtypes, and allowances from metadata APIs.
2. Enter profile, spouse/dependent facts, income, allowance, donation, and withholding data. The canonical browser state mirrors the calculation request and is saved only in `sessionStorage`.
3. Review the collected sections, then call `POST /api/v1/tax/calculate`.
4. Render status, amounts, trace, warnings, recommendations, refund/payment guidance, and disclaimers directly from the response.
5. Planning calls `POST /api/v1/tax/plan` and renders `before`, `after`, `difference`, and `estimated_tax_saving` returned by the backend.
6. An authenticated user may map the same browser state into the Member CRUD APIs. An unauthenticated user returns to the same browser state after login or registration.

JavaScript validates only required fields, nonnegative money formatting, and UI state. Backend 401, 403, 404, 409, 422, network, and server errors are shown without exception details.
