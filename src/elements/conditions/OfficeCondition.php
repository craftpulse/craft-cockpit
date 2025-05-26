<?php

namespace craftpulse\cockpit\elements\conditions;

use Craft;
use craft\elements\conditions\ElementCondition;

/**
 * Office condition
 */
class OfficeCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            // ...
        ]);
    }
}
