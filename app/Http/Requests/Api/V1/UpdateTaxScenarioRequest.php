<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TaxReturn;
use App\Support\ScenarioPayloadRules;
use Illuminate\Validation\Validator;

class UpdateTaxScenarioRequest extends StrictApiRequest
{
    public function rules(): array
    {
        $rules = ['name' => ['sometimes', 'required', 'string', 'min:1', 'max:255'],
            ...ScenarioPayloadRules::rules('payload')];
        $rules['payload'] = ['sometimes', ...array_slice($rules['payload'], 1)];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator): void {
            if ($this->all() === []) {
                $validator->errors()->add('name', 'Provide a name or a payload to update.');
            }
            $return = $this->route('taxReturn');
            ScenarioPayloadRules::validate($validator, 'payload', $this->input('payload'),
                $return instanceof TaxReturn ? $return->rule_version_id : null);
        });
    }
}
