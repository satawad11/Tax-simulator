<?php

namespace App\Services\Admin;

use App\Models\TaxForm;
use App\Models\TaxYear;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opening, describing and retiring a tax year.
 *
 * Milestone 09.1. A year is the container every rule version, form and saved return hangs from,
 * and until now it could only be created by editing a seeder — which left the one workflow the
 * rule console exists for, "next year's rates changed", half-built.
 *
 * Two properties are the whole point of this service.
 *
 * A new year is useless without its forms. `tax_forms` and the income types each form accepts
 * belong to the year, not to a rule version, so a year created on its own would offer no
 * ภ.ง.ด.90 and no ภ.ง.ด.91 and the simulator could not start. Creating a year therefore copies
 * that structure from an existing year by default.
 *
 * A year is never deleted. Retiring one only clears its active flag, so it stops being offered
 * for a *new* simulation while every saved return, every stored calculation and every published
 * rule version belonging to it keeps resolving exactly as before.
 */
class TaxYearService
{
    public function __construct(private AdminAuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): TaxYear
    {
        return DB::transaction(function () use ($actor, $data): TaxYear {
            if (TaxYear::where('year', $data['year'])->exists()) {
                throw ValidationException::withMessages([
                    'year' => 'TAX_YEAR_DUPLICATE: ปีภาษีนี้มีอยู่แล้วในระบบ',
                ]);
            }

            $year = TaxYear::create([
                'year' => $data['year'],
                'name' => $data['name'] ?? 'ปีภาษี '.$data['year'],
                'active' => $data['active'] ?? true,
                'filing_start_date' => $data['filing_start_date'] ?? null,
                'filing_end_date' => $data['filing_end_date'] ?? null,
            ]);

            $copied = [];
            if (! empty($data['copy_forms_from_year'])) {
                $copied = $this->copyForms((int) $data['copy_forms_from_year'], $year);
            }

            $this->audit->record($actor, AdminAuditService::TAX_YEAR_CREATED, 'tax_year', $year->id,
                'Opened tax year '.$year->year.($copied === [] ? ' with no forms yet'
                    : '; copied forms '.implode(', ', $copied).' from '.$data['copy_forms_from_year']),
                null, $year->only(['year', 'name', 'active']));

            return $year->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, TaxYear $year, array $data): TaxYear
    {
        return DB::transaction(function () use ($actor, $year, $data): TaxYear {
            $before = $year->only(['name', 'active', 'filing_start_date', 'filing_end_date']);

            // `year` itself is never editable. Rule versions, forms and saved returns all point
            // at this row, and renaming the year underneath them would silently relabel history.
            $year->fill(array_intersect_key($data, array_flip(['name', 'active', 'filing_start_date', 'filing_end_date'])));
            $year->save();

            $this->audit->record($actor, AdminAuditService::TAX_YEAR_UPDATED, 'tax_year', $year->id,
                'Updated tax year '.$year->year, $before,
                $year->only(['name', 'active', 'filing_start_date', 'filing_end_date']));

            return $year->fresh();
        });
    }

    /**
     * Retires a year: it stops being offered, and nothing that already uses it changes.
     *
     * A year carrying a published rule version cannot be retired, because that version is what a
     * saved return of that year resolves against and an inactive year would make the product
     * quietly inconsistent about which years it still stands behind.
     */
    public function deactivate(User $actor, TaxYear $year): TaxYear
    {
        return DB::transaction(function () use ($actor, $year): TaxYear {
            if ($year->ruleVersions()->where('status', 'published')->exists()) {
                throw ValidationException::withMessages([
                    'id' => 'TAX_YEAR_HAS_PUBLISHED_RULES: ปีภาษีนี้มีชุดกฎที่เผยแพร่อยู่ '
                        .'หากต้องการหยุดให้บริการปีนี้ ให้จัดเก็บชุดกฎก่อน',
                ]);
            }

            $year->active = false;
            $year->save();

            $this->audit->record($actor, AdminAuditService::TAX_YEAR_DEACTIVATED, 'tax_year', $year->id,
                'Deactivated tax year '.$year->year.'; existing data is unchanged',
                ['active' => true], ['active' => false]);

            return $year->fresh();
        });
    }

    /**
     * Copies a year's form structure — the forms themselves and the income types each accepts.
     *
     * No rule data is copied here: rates and thresholds belong to a rule version, and are carried
     * over separately by cloning that version into the new year.
     *
     * @return list<string> the form codes that were created
     */
    private function copyForms(int $sourceYear, TaxYear $target): array
    {
        $source = TaxYear::where('year', $sourceYear)->first();
        if (! $source) {
            throw ValidationException::withMessages([
                'copy_forms_from_year' => 'ไม่พบปีภาษีต้นทางที่ต้องการคัดลอกโครงสร้างแบบฟอร์ม',
            ]);
        }

        $created = [];
        foreach ($source->forms()->with('incomeTypes')->orderBy('code')->get() as $form) {
            $copy = TaxForm::create([
                'tax_year_id' => $target->id,
                'code' => $form->code,
                'name' => $form->name,
                'description' => $form->description,
                'is_active' => true,
            ]);
            foreach ($form->incomeTypes as $incomeType) {
                $copy->incomeTypes()->attach($incomeType->id);
            }
            $created[] = $form->code;
        }

        return $created;
    }
}
