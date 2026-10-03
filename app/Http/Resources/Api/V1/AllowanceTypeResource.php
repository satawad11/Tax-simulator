<?php

namespace App\Http\Resources\Api\V1;

use App\Services\Tax\AllowanceCoverageCatalogue;
use Illuminate\Http\Request;

class AllowanceTypeResource extends MetadataResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code, 'name' => $this->name, 'category' => $this->category,
            'description' => $this->description,
            'rule' => AllowanceRuleResource::make($this->allowanceRules->first()),
            'coverage' => $this->coverage(),
        ];
    }

    /**
     * Why this code cannot be calculated, in the words of the source that blocks it.
     *
     * Milestone 09.1. The catalogue has always held a specific, source-cited reason per code, but
     * it only reached a caller who submitted a positive amount and got a 422 back. A client
     * choosing what to render had nothing but the absence of a rule to go on, so every blocked
     * code looked alike and the interface explained them with one generic sentence — including the
     * umbrella categories, which ใบแนบ never prints as a line at all and which therefore do not
     * belong in a list of the form's own items.
     *
     * `status` says whether the engine can calculate the line; `printed_on_form` says whether
     * ใบแนบ prints it at all. Those were once conflated, and the result hid four printed deduction
     * lines — items 13, 16, 20 and 22 — from the product entirely while showing three umbrella
     * categories the attachment never prints.
     *
     * @return array{status: string, reason_code: string|null, reason: string|null, printed_on_form: bool}
     */
    private function coverage(): array
    {
        if (! AllowanceCoverageCatalogue::blocks($this->code)) {
            return ['status' => 'SUPPORTED', 'reason_code' => null, 'reason' => null,
                'printed_on_form' => true];
        }

        return ['status' => AllowanceCoverageCatalogue::status($this->code),
            'reason_code' => AllowanceCoverageCatalogue::errorCode($this->code),
            'reason' => AllowanceCoverageCatalogue::message($this->code),
            /*
             * Whether ใบแนบ prints this as a line, which is a different question from whether the
             * engine can calculate it. A client shows a reader every line the form prints —
             * including the ones it cannot compute, with the reason — and never shows an umbrella
             * category the attachment does not print, because that would send them looking for a
             * line that does not exist.
             */
            'printed_on_form' => AllowanceCoverageCatalogue::printedOnForm($this->code)];
    }
}
