<?php

namespace App\Models\Concerns;

/** Preserve old model callers while storing the approved M2 column names. */
trait HasLegacySchemaAliases
{
    public function setAttribute($key, $value)
    {
        parent::setAttribute($key, $value);
        foreach ($this->schemaAliases() as $canonical => $legacy) {
            if ($key === $canonical) {
                parent::setAttribute($legacy, $value);
            } elseif ($key === $legacy) {
                parent::setAttribute($canonical, $value);
            }
        }

        return $this;
    }
}
