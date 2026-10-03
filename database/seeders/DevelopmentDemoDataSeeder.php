<?php

namespace Database\Seeders;

use App\Models\TaxReturn;
use App\Models\User;
use App\Services\Tax\MemberTaxCalculationService;
use App\Services\Tax\TaxReturnInputService;
use App\Services\Tax\TaxReturnService;
use App\Services\Tax\TaxScenarioService;
use Database\Seeders\Concerns\RefusesProductionEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Milestone 09.1 — a small, synthetic tax-return set for the seeded development member.
 *
 * Every record here is built through the same services the member API uses:
 * TaxReturnService creates the return, TaxReturnInputService writes each input row, and
 * MemberTaxCalculationService produces the calculation. Nothing is inserted as hand-written
 * JSON, so a demo snapshot has exactly the structure a real one has, and the tax engine is
 * neither bypassed nor modified to make seeding work. Those services authorise through the
 * return's policy, so the seeder signs in as the demo member for the duration of the run and
 * signs out again afterwards.
 *
 * The amounts are invented round numbers for a fictional person. They demonstrate the UI; they
 * are not a worked example of anyone's tax position.
 *
 * Idempotency and safety: each return is looked up by its own `[DEMO] ...` name scoped to the
 * seeded member. A demo return that already exists is left exactly as it is — a developer who
 * has been editing it keeps their work — and no row belonging to any other user is ever read
 * or written.
 */
class DevelopmentDemoDataSeeder extends Seeder
{
    use RefusesProductionEnvironment;

    /** The marker that identifies every row this seeder owns. */
    public const MARKER = '[DEMO]';

    public const PND91_DRAFT = '[DEMO] ภ.ง.ด.91 เงินเดือน';

    public const PND90_DRAFT = '[DEMO] ภ.ง.ด.90 หลายประเภทเงินได้';

    public const PND91_COMPLETED = '[DEMO] ภ.ง.ด.91 ผลคำนวณสมบูรณ์';

    public const SCENARIO_NAME = '[DEMO] สถานการณ์: เพิ่มค่าลดหย่อนประกันสุขภาพ';

    public function __construct(
        private TaxReturnService $returns,
        private TaxReturnInputService $inputs,
        private MemberTaxCalculationService $calculations,
        private TaxScenarioService $scenarios,
    ) {}

    public function run(): void
    {
        $this->assertDevelopmentEnvironment();

        $member = User::where('email', DevelopmentAccountSeeder::MEMBER_EMAIL)->first();

        if ($member === null) {
            throw new RuntimeException('The development member account is missing. Run DevelopmentAccountSeeder first.');
        }

        $previous = Auth::user();
        Auth::setUser($member);

        try {
            $this->pnd91Draft($member);
            $this->pnd90Draft($member);
            $this->completedSimulation($member);
        } finally {
            $previous === null ? Auth::forgetUser() : Auth::setUser($previous);
        }

        $this->command?->info('Demo tax returns for '.DevelopmentAccountSeeder::MEMBER_EMAIL.' are present.');
    }

    /** A half-filled ภ.ง.ด.91 draft: salary and withholding entered, nothing calculated yet. */
    private function pnd91Draft(User $member): void
    {
        $return = $this->existing($member, self::PND91_DRAFT);

        if ($return !== null) {
            return;
        }

        $return = $this->create($member, 'PND91', self::PND91_DRAFT);
        $this->inputs->save($return, 'profile', ['birth_date' => '1992-04-15', 'marital_status' => 'single']);
        $this->inputs->save($return, 'incomes', ['income_type' => 'SECTION_40_1', 'gross_amount' => '540000.00',
            'exempt_amount' => '0.00', 'description' => 'เงินเดือนจากนายจ้าง (ข้อมูลตัวอย่าง)']);
        $this->inputs->save($return, 'allowances', ['code' => 'LIFE_INSURANCE', 'input_amount' => '24000.00']);
        $this->inputs->save($return, 'withholdings', ['type' => 'withholding', 'amount' => '18000.00']);
        $return->forceFill(['current_step' => 3])->save();
    }

    /**
     * A ภ.ง.ด.90 draft with two income types, so the multi-income UI has something to show,
     * plus one planning scenario against it.
     */
    private function pnd90Draft(User $member): void
    {
        $return = $this->existing($member, self::PND90_DRAFT);

        if ($return === null) {
            $return = $this->create($member, 'PND90', self::PND90_DRAFT);
            $this->inputs->save($return, 'profile', ['birth_date' => '1985-09-02', 'marital_status' => 'married']);
            $this->inputs->save($return, 'spouse', ['has_income' => false, 'birth_date' => '1987-01-20']);
            $this->inputs->save($return, 'dependents', ['relation_type' => 'child', 'child_type' => 'legitimate',
                'birth_order' => 1, 'birth_date' => '2015-06-10', 'eligible' => true]);
            $this->inputs->save($return, 'incomes', ['income_type' => 'SECTION_40_1', 'gross_amount' => '720000.00',
                'exempt_amount' => '0.00', 'description' => 'เงินเดือน (ข้อมูลตัวอย่าง)']);
            $this->inputs->save($return, 'incomes', ['income_type' => 'SECTION_40_2', 'gross_amount' => '180000.00',
                'exempt_amount' => '0.00', 'description' => 'ค่านายหน้า (ข้อมูลตัวอย่าง)']);
            $this->inputs->save($return, 'allowances', ['code' => 'LIFE_INSURANCE', 'input_amount' => '30000.00']);
            $this->inputs->save($return, 'donations', ['donation_code' => 'GENERAL_DONATION', 'input_amount' => '5000.00']);
            $this->inputs->save($return, 'withholdings', ['type' => 'withholding', 'amount' => '42000.00']);
            $return->forceFill(['current_step' => 5])->save();
        }

        $this->scenario($return);
    }

    /**
     * A completed ภ.ง.ด.91 with two calculations, so the history page has more than one row.
     *
     * The first calculation is run against the return as first filled in; the second is run
     * after one allowance is added, exactly as a member revising a draft would produce it. The
     * return is marked completed only on the final calculation.
     */
    private function completedSimulation(User $member): void
    {
        if ($this->existing($member, self::PND91_COMPLETED) !== null) {
            return;
        }

        $return = $this->create($member, 'PND91', self::PND91_COMPLETED);
        $this->inputs->save($return, 'profile', ['birth_date' => '1979-11-30', 'marital_status' => 'single']);
        $this->inputs->save($return, 'incomes', ['income_type' => 'SECTION_40_1', 'gross_amount' => '960000.00',
            'exempt_amount' => '0.00', 'description' => 'เงินเดือนทั้งปี (ข้อมูลตัวอย่าง)']);
        $this->inputs->save($return, 'allowances', ['code' => 'LIFE_INSURANCE', 'input_amount' => '40000.00']);
        $this->inputs->save($return, 'withholdings', ['type' => 'withholding', 'amount' => '60000.00']);

        $this->calculations->calculate($return);

        $this->inputs->save($return->fresh(), 'allowances', ['code' => 'HEALTH_INSURANCE', 'input_amount' => '20000.00']);
        $this->calculations->calculate($return->fresh(), complete: true);
    }

    /** One planning scenario on the ภ.ง.ด.90 draft, calculated so the comparison is populated. */
    private function scenario(TaxReturn $return): void
    {
        if ($return->scenarios()->where('name', self::SCENARIO_NAME)->exists()) {
            return;
        }

        $payload = $this->calculations->payload($return);
        $payload['allowances'][] = ['code' => 'HEALTH_INSURANCE', 'amount' => '15000.00'];

        $scenario = $this->scenarios->create($return, ['name' => self::SCENARIO_NAME, 'payload' => $payload]);
        // The service computes and stores the before/after comparison; the seeder derives nothing.
        $this->scenarios->calculate($return, $scenario);
    }

    private function existing(User $member, string $name): ?TaxReturn
    {
        return $member->taxReturns()->where('name', $name)->first();
    }

    private function create(User $member, string $formCode, string $name): TaxReturn
    {
        return $this->returns->create($member, ['tax_year' => 2568, 'form_code' => $formCode, 'name' => $name]);
    }
}
