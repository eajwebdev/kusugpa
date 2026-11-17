<?php

namespace App\Models\Concerns;

use App\Support\PlanterCode;

/**
 * Saves planter_code as 5 digits with leading zeros (9997 → 09997) whenever it is set on the model
 */
trait NormalizesPlanterCode
{
    public function setPlanterCodeAttribute($value): void
    {
        $this->attributes['planter_code'] = $value === null ? null : PlanterCode::normalize($value);
    }
}
