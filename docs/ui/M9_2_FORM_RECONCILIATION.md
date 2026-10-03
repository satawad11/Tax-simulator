# Milestone 09.2 form reconciliation

## Method

The simulator was compared page-by-page with both approved forms and their filing instructions. The review used the rendered forms for layout/section order and the text-readable instructions for definitions, subtypes, expense elections, Table 2 activities, holding-period bands, allowances, donations, credits, and prepayments.

## Closed gaps

- Replaced generic income labels with legal section plus plain-language descriptions and examples.
- Fixed income metadata ambiguity for 40(8) rules keyed by activity or holding band.
- Exposed all 44 approved Table 2 activity labels through the public metadata API.
- Added UI collection for subtype, activity, holding years, expense method, actual expense, and the supported ข้อ 9 treatment election.
- Preserved all detailed income fields when a Member resumes a draft.
- Grouped supported allowances and separated family-derived and unsupported source items.
- Disabled unsupported foreign/other credit entry.
- Reworked review content into the source form sequence.
- Added per-section jump-back editing and human-readable prepayment labels.
- Kept the PND90 minimum-tax row out of PND91 results.
- Mapped backend paths to Thai field names and focused the first invalid control.
- Aligned the optional income-source description validation between Guest and Member flows.
- Added completion/attention semantics to the wizard stepper.

## Classification boundary

Printed identification, address, signature, attachment, filing, and payment-channel boxes are outside calculation-simulator scope. Guarded M7.5 paths remain visible as unsupported and were not converted into numeric rules.

## Integrity

No calculation service, tax formula, published rule value, historical snapshot, or database schema was changed. The API additions are presentation metadata for clients; existing request fields were already part of the supported DTO/persistence shape. The only request-validation adjustment permits `null` for the already-optional income source description.
