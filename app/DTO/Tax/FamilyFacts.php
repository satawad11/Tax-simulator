<?php

namespace App\DTO\Tax;

/**
 * The taxpayer-declared family facts the printed ใบแนบ family allowances are derived from.
 *
 * This object carries facts only — never amounts. Every แสดง amount is produced by a
 * strategy from these facts, so a client cannot influence a family allowance by sending a
 * number (Milestone 07.3, ใบแนบ items 1–5).
 */
final readonly class FamilyFacts
{
    /**
     * @param  array{birth_date?: string|null, marital_status?: string|null}|null  $profile
     * @param  array{birth_date?: string|null, has_income?: bool}|null  $spouse
     * @param  list<array{relation_type: string, disabled_person_relationship?: string|null, child_type?: string|null, birth_order?: int|null, eligible?: bool, birth_date?: string|null}>  $dependents
     */
    public function __construct(
        public ?array $profile = null,
        public ?array $spouse = null,
        public array $dependents = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $dependents = array_values(array_filter($data['dependents'] ?? [], is_array(...)));

        return new self(
            is_array($data['profile'] ?? null) ? $data['profile'] : null,
            is_array($data['spouse'] ?? null) ? $data['spouse'] : null,
            $dependents,
        );
    }

    public function maritalStatus(): ?string
    {
        $status = $this->profile['marital_status'] ?? null;

        return is_string($status) ? $status : null;
    }

    public function isMarried(): bool
    {
        return $this->maritalStatus() === 'married';
    }

    /** Null when no spouse block was declared at all, which is not the same as "has no income". */
    public function spouseHasIncome(): ?bool
    {
        if ($this->spouse === null || ! array_key_exists('has_income', $this->spouse)) {
            return null;
        }

        return (bool) $this->spouse['has_income'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dependentsOf(string ...$relationTypes): array
    {
        return array_values(array_filter(
            $this->dependents,
            fn (array $row): bool => in_array($row['relation_type'] ?? null, $relationTypes, true),
        ));
    }

    public function isEmpty(): bool
    {
        return $this->profile === null && $this->spouse === null && $this->dependents === [];
    }
}
