<?php

namespace App\Services\Tax;

use App\Exceptions\TaxMetadataConflictException;
use App\Models\TaxForm;

class TaxFormRecommendationService
{
    public function __construct(private TaxMetadataService $metadata) {}

    /** @param list<string> $incomeTypes
     * @return array{recommended_form: TaxForm, reason_code: string, reason: string}
     */
    public function recommend(int $year, array $incomeTypes): array
    {
        $context = $this->metadata->context($year);
        $salaryOnly = $incomeTypes === ['SECTION_40_1'];
        $code = $salaryOnly ? 'PND91' : 'PND90';
        $form = $context->forms()->where('active', true)->where('code', $code)->with('incomeTypes')->first();
        if ($form === null || array_diff($incomeTypes, $form->incomeTypes->pluck('code')->all()) !== []) {
            throw new TaxMetadataConflictException('The recommended form is unavailable for the selected income types.');
        }

        return [
            'recommended_form' => $form,
            'reason_code' => $salaryOnly ? 'ONLY_SECTION_40_1' : 'OTHER_INCOME_TYPES',
            'reason' => $salaryOnly ? 'มีเงินได้ตามมาตรา 40(1) ประเภทเดียว' : 'มีเงินได้ประเภทอื่นนอกเหนือจากมาตรา 40(1)',
        ];
    }
}
