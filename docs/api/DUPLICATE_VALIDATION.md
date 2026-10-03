# Duplicate Validation

Duplicate validation applies to Guest calculation/planning payloads and to Member TaxReturn child-resource writes. Duplicate-sensitive Member writes run inside the existing locked TaxReturn transaction, so concurrent writes to the same draft are serialized.

## Rejected keys

| Collection | Unique key within one TaxReturn/request | Error code | Error attribute |
| --- | --- | --- | --- |
| `allowances` | `code` | `DUPLICATE_ALLOWANCE_CODE` | `allowances.{index}.code` or Member `code` |
| `donations` | `code` | `DUPLICATE_DONATION_CODE` | `donations.{index}.code` or Member `donation_code` |
| `withholdings` | `type`, only for `pnd93` and `pnd94` | `DUPLICATE_PREPAYMENT_TYPE` | `withholdings.{index}.type` or Member `type` |
| `dependents` | Parent relationship role, or `child_type + birth_order` when available | `DUPLICATE_DEPENDENT` | `dependents.{index}.relation_type` or Member `relation_type` |

Allowances and donations also have database unique indexes on their persisted keys. PND93/PND94 are enforced by the locked application transaction because the same table must continue to allow multiple ordinary `withholding` rows.

## Intentionally repeatable

- Income rows are never merged automatically. Multiple employers under 40(1), properties under 40(5), and activities under 40(8) are valid.
- Ordinary `withholding` rows are repeatable per payer or certificate.
- Dependents without a safe identity key are not rejected. The client warns on identical available facts.

## 422 response

The API returns its normal validation envelope. Stable codes prefix Thai messages so clients can map the error without parsing the wording.

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "donations.1.code": [
      "DUPLICATE_DONATION_CODE: พบประเภทเงินบริจาคซ้ำ กรุณากรอกยอดรวมรายปีเพียงรายการเดียวต่อประเภท"
    ]
  }
}
```

The frontend maps all four codes to Thai guidance, focuses the matching control where possible, and keeps backend validation authoritative.

## Required-fact codes

The same input-integrity gate reports stable prefixes for facts that become necessary only after a related choice:

- `REQUIRED_WHEN_SPOUSE_DECLARED`
- `REQUIRED_DEPENDENT_DECLARATION`
- `REQUIRED_CHILD_TYPE`
- `REQUIRED_CHILD_BIRTH_ORDER`
- `REQUIRED_CHILD_BIRTH_DATE`
- `REQUIRED_TAX_TREATMENT`

These checks choose an input path; they do not implement or modify a tax formula.

