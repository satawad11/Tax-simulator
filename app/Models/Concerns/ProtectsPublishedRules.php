<?php

namespace App\Models\Concerns;

use App\Models\Builders\RuleBuilder;
use App\Models\TaxRuleVersion;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

trait ProtectsPublishedRules
{
    public function newEloquentBuilder($query): RuleBuilder
    {
        return new RuleBuilder($query);
    }

    protected function performInsert(Builder $query)
    {
        return $this->withEditableVersion(fn () => parent::performInsert($query->forModelWrite()));
    }

    protected function performInsertOrIgnore(Builder $query, array|string|null $uniqueBy)
    {
        return $this->withEditableVersion(fn () => parent::performInsertOrIgnore($query->forModelWrite(), $uniqueBy));
    }

    protected function performUpdate(Builder $query)
    {
        return $this->withEditableVersion(fn () => parent::performUpdate($query->forModelWrite()));
    }

    protected function performDeleteOnModel(): void
    {
        $this->withEditableVersion(function (): void {
            $this->setKeysForSaveQuery($this->newModelQuery())->forModelWrite()->delete();
            $this->exists = false;
        });
    }

    /** Locks serialize publication with edits, including quiet model writes. */
    private function withEditableVersion(Closure $write): mixed
    {
        return $this->getConnection()->transaction(function () use ($write): mixed {
            $current = $this->exists
                ? $this->getConnection()->table($this->getTable())->where('id', $this->getKey())->lockForUpdate()->first()
                : null;

            if ($this instanceof TaxRuleVersion) {
                if ($current?->status === 'published') {
                    throw new LogicException('Published tax rule versions are immutable. Create a new version.');
                }

                if (! $this->exists && $this->status === 'published') {
                    throw new LogicException('Create a draft version before publishing it.');
                }

                if ($this->status === 'published' && $this->published_at === null) {
                    $this->published_at = now();
                }
            } else {
                $versionIds = array_unique(array_filter([$current?->rule_version_id, $this->rule_version_id]));
                sort($versionIds);

                foreach ($versionIds as $id) {
                    $version = $this->getConnection()->table('tax_rule_versions')->where('id', $id)->lockForUpdate()->first();

                    if ($version?->status === 'published') {
                        throw new LogicException('Rules belonging to a published version are immutable. Create a new version.');
                    }
                }
            }

            return $write();
        });
    }
}
