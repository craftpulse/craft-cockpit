<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

/**
 * Department query
 */
class DepartmentQuery extends ElementQuery
{
    protected function beforePrepare(): bool
    {
        // todo: join the `cockpit_departments` table
        // $this->joinElementTable('cockpit_departments');

        // todo: apply any custom query params
        // ...

        return parent::beforePrepare();
    }
}
