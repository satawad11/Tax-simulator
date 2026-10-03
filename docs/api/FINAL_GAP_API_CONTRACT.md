# Final gap API contract

This document records the only additive calculation contract accepted in the final-gap pass and
the explicit reasons every other proposed contract remains unavailable. The published 2568.1
contract is unchanged.

## Disabled-person relationship discriminator (draft 2568.2)

| Property | Contract |
|---|---|
| Request path | `dependents.*.disabled_person_relationship` |
| Type | nullable string |
| Enum | `family_member`, `other_person` |
| Valid on | a dependent whose `relation_type` is `disabled_person` |
| Validation | rejected on every other relation; required at calculation time when the resolved rule version contains `PND90_2568_DISABLED_PERSON_OTHER_LIMIT` |
| Guest persistence | none |
| Member persistence | nullable `tax_return_dependents.disabled_person_relationship` |
| Response path | `dependents.*.disabled_person_relationship` on Member return resources |
| Calculation impact | none under 2568.1; under a future published 2568.2, eligible `family_member` rows are counted and at most one eligible `other_person` row is counted |
| Backward compatibility | additive nullable field; historical rows and snapshots are untouched; a 2568.1 return keeps its original warning/result behavior |

The field records a source-defined discriminator, not a free-text relationship and not an
eligibility decision. `eligible` remains the taxpayer's declaration.

## Contracts deliberately not opened

| Gap | Required future structured input | Why no request field is accepted now |
|---|---|---|
| BE-05 dividend credit | eligible dividend amount, payer corporate-tax rate, taxpayer residency/eligibility evidence | credit/gross-up rounding is not source-defined |
| BE-06 separate-tax property | property acquisition class, proceeds, supported expense election, holding years, withholding, explicit separate election | rate treatment for the first 150,000 after removal of the exemption is not source-defined |
| BE-07 excluded aggregation | explicit source subtype and election code | complete eligible subtype and settlement contract is not established |
| BE-11 exempt fund components | component code, paid amount, wage base where applicable, shared-basket allocation | shared retirement-cap interaction/order is not fully settled |

Unknown keys continue to be rejected. Mobile clients will use the same versioned `/api/v1`
contract when a draft is reviewed and published. No frontend formula is introduced.
