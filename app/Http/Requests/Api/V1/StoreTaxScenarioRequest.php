<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TaxReturn;
use App\Support\ScenarioPayloadRules;
use Illuminate\Validation\Validator;

class StoreTaxScenarioRequest extends StrictApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            ...ScenarioPayloadRules::rules('payload'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            $return = $this->route('taxReturn');
            ScenarioPayloadRules::validate($validator, 'payload', $this->input('payload'),
                $return instanceof TaxReturn ? $return->rule_version_id : null);
        });
    }
}
