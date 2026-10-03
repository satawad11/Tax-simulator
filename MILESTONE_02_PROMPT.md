# MILESTONE_02_PROMPT.md

## Codex Task: Milestone 02 — Database Foundation

You are working on:

**Thai Personal Income Tax Simulation Platform**

Before changing any file, you MUST read:

```text
PROJECT_REQUIREMENTS.md
CODING_RULES.md
MILESTONE_01_PROMPT.md
```

Treat those files as the source of truth.

Do not change the approved architecture, API versioning strategy, or UI direction.

Do not begin Milestone 03.

---

# Objective

Implement the database foundation for the project based on the approved ER design.

This milestone focuses on:

- Laravel migrations
- Eloquent models
- model relationships
- constraints
- indexes
- seeders
- factories where useful
- schema-focused tests

Do NOT implement the full tax calculation engine yet.

---

# Source Rules

The approved tax domain structure is based on:

- PND90
- PND91
- approved project requirements
- approved ER design

Important:

- Do not invent tax rules that are not explicitly approved.
- If a rule value is uncertain, create structure only and add TODO.
- Seed only values that are already approved.
- Do not silently "correct" or reinterpret requirements.

---

# Required Database Domains

Implement the following domains.

---

# 1. User Domain

Laravel default user table may already exist.

Ensure the user model supports:

```text
id
name
email
password
role
email_verified_at
remember_token
timestamps
```

Role values:

```text
member
admin
```

If role does not exist yet, add it safely via migration.

Do not break existing authentication scaffolding from Milestone 01.

---

# 2. Tax Master Domain

Create tables:

```text
tax_years
tax_forms
tax_rule_versions
tax_brackets
income_types
tax_form_income_types
income_rules
expense_rules
allowance_types
allowance_rules
donation_rules
recommendation_rules
```

## 2.1 tax_years

Fields:

```text
id BIGINT UNSIGNED PK
year SMALLINT UNSIGNED UNIQUE
name VARCHAR(100)
filing_start_date DATE NULL
filing_end_date DATE NULL
active BOOLEAN DEFAULT true
created_at
updated_at
```

## 2.2 tax_forms

Fields:

```text
id BIGINT UNSIGNED PK
tax_year_id BIGINT UNSIGNED FK
code VARCHAR(20)
name VARCHAR(100)
description TEXT NULL
active BOOLEAN DEFAULT true
created_at
updated_at
```

Constraints:

```text
tax_year_id -> tax_years.id
UNIQUE(tax_year_id, code)
```

Approved codes:

```text
PND90
PND91
```

## 2.3 tax_rule_versions

Fields:

```text
id BIGINT UNSIGNED PK
tax_year_id BIGINT UNSIGNED FK
version VARCHAR(30)
status VARCHAR(20)
effective_from DATETIME NULL
effective_to DATETIME NULL
description TEXT NULL
created_at
updated_at
```

Approved statuses:

```text
draft
published
retired
```

Constraints:

```text
UNIQUE(tax_year_id, version)
INDEX(status)
```

Published rule immutability will be enforced at application layer later.

## 2.4 tax_brackets

Fields:

```text
id BIGINT UNSIGNED PK
rule_version_id BIGINT UNSIGNED FK
min_amount DECIMAL(15,2)
max_amount DECIMAL(15,2) NULL
rate DECIMAL(6,3)
sort_order UNSIGNED INT
created_at
updated_at
```

Index:

```text
(rule_version_id, sort_order)
```

Do not use FLOAT or DOUBLE.

## 2.5 income_types

Fields:

```text
id BIGINT UNSIGNED PK
code VARCHAR(30) UNIQUE
section_code VARCHAR(20)
name VARCHAR(255)
description TEXT NULL
created_at
updated_at
```

Approved codes:

```text
SECTION_40_1
SECTION_40_2
SECTION_40_3
SECTION_40_4
SECTION_40_5
SECTION_40_6
SECTION_40_7
SECTION_40_8
```

## 2.6 tax_form_income_types

Pivot fields:

```text
id BIGINT UNSIGNED PK
tax_form_id BIGINT UNSIGNED FK
income_type_id BIGINT UNSIGNED FK
created_at
updated_at
```

Constraint:

```text
UNIQUE(tax_form_id, income_type_id)
```

Mappings:

```text
PND91 -> SECTION_40_1 only
PND90 -> SECTION_40_1 through SECTION_40_8
```

## 2.7 income_rules

Fields:

```text
id BIGINT UNSIGNED PK
rule_version_id BIGINT UNSIGNED FK
income_type_id BIGINT UNSIGNED FK
active BOOLEAN DEFAULT true
metadata JSON NULL
created_at
updated_at
```

Unique:

```text
UNIQUE(rule_version_id, income_type_id)
```

Do not add unverified tax values.

## 2.8 expense_rules

Fields:

```text
id BIGINT UNSIGNED PK
rule_version_id BIGINT UNSIGNED FK
income_type_id BIGINT UNSIGNED FK
method VARCHAR(50)
percentage DECIMAL(6,3) NULL
maximum_amount DECIMAL(15,2) NULL
minimum_amount DECIMAL(15,2) NULL
conditions JSON NULL
active BOOLEAN DEFAULT true
created_at
updated_at
```

Approved method values:

```text
fixed
percentage
percentage_limit
actual
percentage_or_actual
custom
```

Prefer string + application enum over DB ENUM.

## 2.9 allowance_types

Fields:

```text
id BIGINT UNSIGNED PK
code VARCHAR(50) UNIQUE
name VARCHAR(255)
category VARCHAR(100)
description TEXT NULL
created_at
updated_at
```

Seed master codes:

```text
PERSONAL
SPOUSE
CHILD
PARENT
DISABLED_PERSON
LIFE_INSURANCE
HEALTH_INSURANCE
PENSION_INSURANCE
PROVIDENT_FUND
NSF
RMF
HOME_LOAN_INTEREST
SOCIAL_SECURITY
EASY_E_RECEIPT
THAI_ESG
THAI_ESGX
OTHER
```

Do not guess numeric limits in this milestone.

## 2.10 allowance_rules

Fields:

```text
id BIGINT UNSIGNED PK
rule_version_id BIGINT UNSIGNED FK
allowance_type_id BIGINT UNSIGNED FK
method VARCHAR(50)
fixed_amount DECIMAL(15,2) NULL
percentage DECIMAL(6,3) NULL
maximum_amount DECIMAL(15,2) NULL
minimum_amount DECIMAL(15,2) NULL
conditions JSON NULL
active BOOLEAN DEFAULT true
created_at
updated_at
```

Unique:

```text
UNIQUE(rule_version_id, allowance_type_id)
```

## 2.11 donation_rules

Fields:

```text
id BIGINT UNSIGNED PK
rule_version_id BIGINT UNSIGNED FK
code VARCHAR(50)
name VARCHAR(255)
multiplier DECIMAL(6,3) DEFAULT 1.000
max_percentage DECIMAL(6,3) NULL
conditions JSON NULL
active BOOLEAN DEFAULT true
created_at
updated_at
```

Unique:

```text
UNIQUE(rule_version_id, code)
```

## 2.12 recommendation_rules

Fields:

```text
id BIGINT UNSIGNED PK
rule_version_id BIGINT UNSIGNED FK
code VARCHAR(100)
type VARCHAR(50)
priority VARCHAR(20)
title VARCHAR(255)
message_template TEXT
conditions JSON NULL
action_type VARCHAR(50) NULL
active BOOLEAN DEFAULT true
created_at
updated_at
```

Approved recommendation types:

```text
MISSING_INFORMATION
POTENTIAL_ALLOWANCE
TAX_PLANNING
PAYMENT
REFUND
```

Priority:

```text
low
medium
high
```

---

# 3. Tax Transaction Domain

Create:

```text
tax_returns
tax_return_profiles
tax_return_spouses
tax_return_dependents
tax_return_incomes
tax_return_allowances
tax_return_donations
tax_return_withholdings
tax_calculations
tax_calculation_brackets
tax_scenarios
```

## 3.1 tax_returns

Fields:

```text
id BIGINT UNSIGNED PK
user_id BIGINT UNSIGNED FK
tax_year_id BIGINT UNSIGNED FK
tax_form_id BIGINT UNSIGNED FK
rule_version_id BIGINT UNSIGNED FK
name VARCHAR(255)
status VARCHAR(30)
current_step UNSIGNED SMALLINT DEFAULT 1
completed_at DATETIME NULL
created_at
updated_at
deleted_at
```

Statuses:

```text
draft
completed
archived
```

Use SoftDeletes.

Indexes:

```text
(user_id, status)
(user_id, tax_year_id)
(tax_year_id, tax_form_id)
```

Guest calculations do not require a tax_returns row.

## 3.2 tax_return_profiles

Fields:

```text
id BIGINT UNSIGNED PK
tax_return_id BIGINT UNSIGNED UNIQUE FK
birth_date DATE NULL
marital_status VARCHAR(30) NULL
filing_status VARCHAR(50) NULL
extra_data JSON NULL
created_at
updated_at
```

## 3.3 tax_return_spouses

Fields:

```text
id BIGINT UNSIGNED PK
tax_return_id BIGINT UNSIGNED UNIQUE FK
birth_date DATE NULL
has_income BOOLEAN DEFAULT false
filing_status VARCHAR(50) NULL
extra_data JSON NULL
created_at
updated_at
```

## 3.4 tax_return_dependents

Fields:

```text
id BIGINT UNSIGNED PK
tax_return_id BIGINT UNSIGNED FK
relation_type VARCHAR(50)
birth_date DATE NULL
eligible BOOLEAN DEFAULT false
allowance_amount DECIMAL(15,2) DEFAULT 0
metadata JSON NULL
created_at
updated_at
```

Approved relation types include:

```text
child
father
mother
spouse_father
spouse_mother
disabled_person
```

## 3.5 tax_return_incomes

Fields:

```text
id BIGINT UNSIGNED PK
tax_return_id BIGINT UNSIGNED FK
income_type_id BIGINT UNSIGNED FK
description VARCHAR(255) NULL
gross_amount DECIMAL(15,2)
exempt_amount DECIMAL(15,2) DEFAULT 0
expense_method VARCHAR(50) NULL
actual_expense DECIMAL(15,2) NULL
calculated_expense DECIMAL(15,2) DEFAULT 0
net_amount DECIMAL(15,2) DEFAULT 0
metadata JSON NULL
created_at
updated_at
```

Remember calculated_expense and net_amount are backend-calculated values.

## 3.6 tax_return_allowances

Fields:

```text
id BIGINT UNSIGNED PK
tax_return_id BIGINT UNSIGNED FK
allowance_type_id BIGINT UNSIGNED FK
input_amount DECIMAL(15,2) DEFAULT 0
eligible_amount DECIMAL(15,2) DEFAULT 0
metadata JSON NULL
created_at
updated_at
```

Do not force uniqueness by allowance type unless justified.

## 3.7 tax_return_donations

Fields:

```text
id BIGINT UNSIGNED PK
tax_return_id BIGINT UNSIGNED FK
donation_code VARCHAR(50)
input_amount DECIMAL(15,2) DEFAULT 0
eligible_amount DECIMAL(15,2) NULL
metadata JSON NULL
created_at
updated_at
```

## 3.8 tax_return_withholdings

Fields:

```text
id BIGINT UNSIGNED PK
tax_return_id BIGINT UNSIGNED FK
type VARCHAR(50)
payer_name VARCHAR(255) NULL
payer_tax_id VARCHAR(30) NULL
amount DECIMAL(15,2) DEFAULT 0
metadata JSON NULL
created_at
updated_at
```

Approved domain types:

```text
withholding
foreign_tax_credit
pnd93
pnd94
other_credit
```

## 3.9 tax_calculations

Fields:

```text
id BIGINT UNSIGNED PK
tax_return_id BIGINT UNSIGNED FK
gross_income DECIMAL(15,2) DEFAULT 0
exempt_income DECIMAL(15,2) DEFAULT 0
total_expense DECIMAL(15,2) DEFAULT 0
income_after_expense DECIMAL(15,2) DEFAULT 0
total_allowance DECIMAL(15,2) DEFAULT 0
total_donation DECIMAL(15,2) DEFAULT 0
net_income DECIMAL(15,2) DEFAULT 0
calculated_tax DECIMAL(15,2) DEFAULT 0
tax_credit DECIMAL(15,2) DEFAULT 0
withholding_tax DECIMAL(15,2) DEFAULT 0
prepaid_tax DECIMAL(15,2) DEFAULT 0
final_tax DECIMAL(15,2) DEFAULT 0
result_status VARCHAR(20)
calculation_trace JSON NULL
calculated_at DATETIME
created_at
updated_at
```

Approved statuses:

```text
PAYABLE
REFUND
ZERO
```

## 3.10 tax_calculation_brackets

Fields:

```text
id BIGINT UNSIGNED PK
tax_calculation_id BIGINT UNSIGNED FK
from_amount DECIMAL(15,2)
to_amount DECIMAL(15,2) NULL
rate DECIMAL(6,3)
taxable_amount DECIMAL(15,2)
tax_amount DECIMAL(15,2)
sort_order UNSIGNED INT
created_at
updated_at
```

## 3.11 tax_scenarios

Fields:

```text
id BIGINT UNSIGNED PK
user_id BIGINT UNSIGNED FK
source_tax_return_id BIGINT UNSIGNED FK
tax_year_id BIGINT UNSIGNED FK
rule_version_id BIGINT UNSIGNED FK
name VARCHAR(255)
payload JSON
calculation_result JSON NULL
created_at
updated_at
```

Scenario must not overwrite source tax return.

---

# 4. Content Domain

Create:

```text
content_categories
content_tags
content_posts
content_post_tags
```

## 4.1 content_categories

```text
id BIGINT UNSIGNED PK
name VARCHAR(150)
slug VARCHAR(180) UNIQUE
description TEXT NULL
created_at
updated_at
```

## 4.2 content_tags

```text
id BIGINT UNSIGNED PK
name VARCHAR(150)
slug VARCHAR(180) UNIQUE
created_at
updated_at
```

## 4.3 content_posts

```text
id BIGINT UNSIGNED PK
author_id BIGINT UNSIGNED FK -> users.id
category_id BIGINT UNSIGNED NULL FK
type VARCHAR(30)
title VARCHAR(255)
slug VARCHAR(255) UNIQUE
excerpt TEXT NULL
content LONGTEXT
cover_image VARCHAR(500) NULL
source_name VARCHAR(255) NULL
source_url VARCHAR(1000) NULL
status VARCHAR(30)
published_at DATETIME NULL
created_at
updated_at
deleted_at
```

Use SoftDeletes.

Approved types:

```text
article
news
```

Approved statuses:

```text
draft
published
archived
```

## 4.4 content_post_tags

Pivot:

```text
content_post_id BIGINT UNSIGNED FK
content_tag_id BIGINT UNSIGNED FK
UNIQUE(content_post_id, content_tag_id)
```

---

# 5. Foreign Key Delete Strategy

Recommended:

Master tables:

```text
restrictOnDelete()
```

Transactional children:

```text
cascadeOnDelete()
```

Examples:

```text
tax_returns -> tax_return_incomes = cascade
tax_returns -> tax_return_allowances = cascade
tax_returns -> tax_calculations = cascade

tax_years -> tax_forms = restrict
tax_rule_versions -> tax_brackets = restrict
```

Do not cascade-delete historical rule data casually.

---

# 6. Eloquent Models

Create corresponding models:

```text
TaxYear
TaxForm
TaxRuleVersion
TaxBracket
IncomeType
IncomeRule
ExpenseRule
AllowanceType
AllowanceRule
DonationRule
RecommendationRule

TaxReturn
TaxReturnProfile
TaxReturnSpouse
TaxReturnDependent
TaxReturnIncome
TaxReturnAllowance
TaxReturnDonation
TaxReturnWithholding
TaxCalculation
TaxCalculationBracket
TaxScenario

ContentCategory
ContentTag
ContentPost
```

---

# 7. Required Relationships

Implement explicit relationships.

Examples:

```text
User hasMany TaxReturn
User hasMany TaxScenario
User hasMany ContentPost as author

TaxYear hasMany TaxForm
TaxYear hasMany TaxRuleVersion
TaxYear hasMany TaxReturn

TaxForm belongsTo TaxYear
TaxForm belongsToMany IncomeType
TaxForm hasMany TaxReturn

TaxRuleVersion belongsTo TaxYear
TaxRuleVersion hasMany TaxBracket
TaxRuleVersion hasMany IncomeRule
TaxRuleVersion hasMany ExpenseRule
TaxRuleVersion hasMany AllowanceRule
TaxRuleVersion hasMany DonationRule
TaxRuleVersion hasMany RecommendationRule
TaxRuleVersion hasMany TaxReturn

IncomeType belongsToMany TaxForm
IncomeType hasMany IncomeRule
IncomeType hasMany ExpenseRule
IncomeType hasMany TaxReturnIncome

AllowanceType hasMany AllowanceRule
AllowanceType hasMany TaxReturnAllowance

TaxReturn belongsTo User
TaxReturn belongsTo TaxYear
TaxReturn belongsTo TaxForm
TaxReturn belongsTo TaxRuleVersion
TaxReturn hasOne TaxReturnProfile
TaxReturn hasOne TaxReturnSpouse
TaxReturn hasMany TaxReturnDependent
TaxReturn hasMany TaxReturnIncome
TaxReturn hasMany TaxReturnAllowance
TaxReturn hasMany TaxReturnDonation
TaxReturn hasMany TaxReturnWithholding
TaxReturn hasMany TaxCalculation

TaxCalculation belongsTo TaxReturn
TaxCalculation hasMany TaxCalculationBracket

ContentPost belongsTo User as author
ContentPost belongsTo ContentCategory
ContentPost belongsToMany ContentTag
```

Use typed relationship return types where practical.

---

# 8. Model Casts

Use casts for:

```text
boolean
date
datetime
array/json
decimal where appropriate
```

Do not cast money to float.

---

# 9. Seeders

Create maintainable seeders, preferably:

```text
TaxYearSeeder
TaxFormSeeder
IncomeTypeSeeder
TaxRuleVersionSeeder
TaxBracketSeeder
AllowanceTypeSeeder
DatabaseSeeder
```

## Required seed data

Tax year:

```text
2568
```

Tax forms:

```text
PND90
PND91
```

Rule version:

```text
2568.1
```

Income types:

```text
SECTION_40_1 through SECTION_40_8
```

Mappings:

```text
PND91 -> SECTION_40_1
PND90 -> SECTION_40_1 through SECTION_40_8
```

Tax brackets:

```text
0 - 150000         0%
150000 - 300000    5%
300000 - 500000    10%
500000 - 750000    15%
750000 - 1000000   20%
1000000 - 2000000  25%
2000000 - 5000000  30%
5000000 - NULL     35%
```

Allowance types:

```text
PERSONAL
SPOUSE
CHILD
PARENT
DISABLED_PERSON
LIFE_INSURANCE
HEALTH_INSURANCE
PENSION_INSURANCE
PROVIDENT_FUND
NSF
RMF
HOME_LOAN_INTEREST
SOCIAL_SECURITY
EASY_E_RECEIPT
THAI_ESG
THAI_ESGX
OTHER
```

Do not seed unverified allowance limits or other unverified tax values.

---

# 10. Tests

Add database-focused tests.

At minimum verify:

```text
tax year 2568 exists
PND90 exists
PND91 exists
PND91 maps only to SECTION_40_1
PND90 maps to SECTION_40_1 through SECTION_40_8
rule version 2568.1 belongs to tax year 2568
all 8 tax brackets exist in correct order
key allowance types exist
representative model relationships work
important unique constraints work where practical
```

Do not implement or test tax calculations yet.

---

# 11. Migration Order

Respect dependencies.

Suggested order:

```text
users role alteration
tax_years
tax_forms
tax_rule_versions
income_types
tax_form_income_types
tax_brackets
income_rules
expense_rules
allowance_types
allowance_rules
donation_rules
recommendation_rules

tax_returns
tax_return_profiles
tax_return_spouses
tax_return_dependents
tax_return_incomes
tax_return_allowances
tax_return_donations
tax_return_withholdings
tax_calculations
tax_calculation_brackets
tax_scenarios

content_categories
content_tags
content_posts
content_post_tags
```

Every migration must have a correct down().

---

# 12. Do Not Implement Yet

Do NOT implement:

```text
TaxCalculationService
ProgressiveTaxCalculator logic
Expense calculations
Allowance calculations
Donation calculations
Tax recommendation evaluation
Tax planning comparison
Refund engine
Public metadata controllers
Member Tax Return CRUD API
Admin rule management
Full authentication flow
Frontend simulator
```

Those belong to later milestones.

---

# 13. No UI Changes

This milestone is database-focused.

Do not redesign the approved UI.

Only modify UI-related files if technically required, and explain why.

---

# 14. Docker Commands

Use the working M1 Docker environment.

Inspect first:

```bash
docker compose ps
git status
```

Then run:

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
```

If needed:

```bash
docker compose up -d
```

Do not rebuild unnecessarily.

---

# 15. Completion Criteria

Milestone 02 is complete only when:

```text
[ ] all required migrations exist
[ ] migrate:fresh --seed succeeds
[ ] required seed records exist
[ ] Eloquent models exist
[ ] approved relationships work
[ ] monetary fields use DECIMAL
[ ] foreign keys are correct
[ ] important unique constraints exist
[ ] tax_returns use SoftDeletes
[ ] content_posts use SoftDeletes
[ ] database tests pass
[ ] full php artisan test passes
[ ] no tax calculation logic was prematurely implemented
```

---

# 16. Required Final Report

At the end, report exactly:

## Summary

## Migrations Created

## Models Created

## Relationships Implemented

## Seeders Created

## Seed Data

Report counts for:

```text
tax years
tax forms
rule versions
tax brackets
income types
form-income mappings
allowance types
```

## Database Constraints

## Commands Executed

## Migration Result

## Test Results

## Known Issues

## TODO

## Architecture Compliance

Confirm:

```text
No tax calculation business logic was added.
No API milestone beyond database foundation was started.
No approved UI redesign was performed.
```

## Next Step

Recommend:

```text
Milestone 03 — Public Tax Metadata API
```

Do not begin Milestone 03 until approved.
