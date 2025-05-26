<?php

namespace craftpulse\cockpit\elements\conditions;

use Craft;
use craft\elements\conditions\ElementCondition;

/**
 * Match Field Entry condition
 */
class MatchFieldEntryCondition extends ElementCondition
{
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::conditionRuleTypes(), [
            // ...
        ]);
    }
}
