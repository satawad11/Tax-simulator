<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/** @template TModel of \Illuminate\Database\Eloquent\Model
 * @extends Builder<TModel>
 */
class RuleBuilder extends Builder
{
    private bool $modelWrite = false;

    /** @internal Only guarded model persistence may enable writes. */
    public function forModelWrite(): static
    {
        $this->modelWrite = true;

        return $this;
    }

    private function assertModelWrite(): void
    {
        if (! $this->modelWrite) {
            throw new LogicException('Bulk rule writes are disabled. Save or delete individual models to enforce published-version protection.');
        }
    }

    public function update(array $values)
    {
        $this->assertModelWrite();

        return parent::update($values);
    }

    public function delete()
    {
        $this->assertModelWrite();

        return parent::delete();
    }

    public function forceDelete()
    {
        $this->assertModelWrite();

        return parent::forceDelete();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        $this->assertModelWrite();

        return parent::upsert($values, $uniqueBy, $update);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->assertModelWrite();

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->assertModelWrite();

        return parent::decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->assertModelWrite();

        return parent::incrementEach($columns, $extra);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->assertModelWrite();

        return parent::decrementEach($columns, $extra);
    }

    public function __call($method, $parameters)
    {
        if (in_array(strtolower($method), ['insert', 'insertgetid', 'insertorignore', 'insertusing', 'insertorignoreusing', 'insertorignorereturning', 'truncate', 'updateorinsert', 'updatereturning', 'deletereturning'], true)) {
            $this->assertModelWrite();
        }

        return parent::__call($method, $parameters);
    }
}
