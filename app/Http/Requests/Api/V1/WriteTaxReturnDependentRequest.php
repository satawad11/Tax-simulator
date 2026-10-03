<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Tax\Allowances\ChildAllowanceStrategy;
use App\Services\Tax\Allowances\DisabledPersonAllowanceStrategy;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WriteTaxReturnDependentRequest extends StrictApiRequest
{
    public const RELATION_TYPES = ['child', 'father', 'mother', 'spouse_father', 'spouse_mother', 'disabled_person'];

    /** บุตรชอบด้วยกฎหมาย (ใบแนบ 3.1) vs บุตรบุญธรรม (ใบแนบ 3.2). */
    public const CHILD_TYPES = [ChildAllowanceStrategy::LEGITIMATE, ChildAllowanceStrategy::ADOPTED];

    public function rules(): array
    {
        $required = $this->isMethod('PATCH') ? 'sometimes' : 'required';

        return [
            'relation_type' => [$required, Rule::in(self::RELATION_TYPES)],
            'disabled_person_relationship' => ['sometimes', 'nullable', Rule::in(DisabledPersonAllowanceStrategy::RELATIONSHIPS)],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            // M7.3 — the two facts ใบแนบ item 3 needs and the row cannot otherwise supply.
            'child_type' => ['sometimes', 'nullable', Rule::in(self::CHILD_TYPES)],
            'birth_order' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            // The taxpayer's declaration that the printed conditions are met. The engine never
            // decides eligibility; it only counts people the taxpayer has declared eligible.
            'eligible' => [$required, 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $relationship = $this->input('disabled_person_relationship');
            $relationType = $this->input('relation_type');
            if ($relationship !== null && $relationType !== 'disabled_person') {
                $validator->errors()->add('disabled_person_relationship',
                    'This discriminator is accepted only for a disabled_person dependent.');
            }
        }];
    }
}
