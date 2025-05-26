<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

/**
 * Office query
 */
class OfficeQuery extends ElementQuery
{
    protected function beforePrepare(): bool
    {
        // todo: join the `offices` table
        // $this->joinElementTable('offices');

        // todo: apply any custom query params
        // ...

        return parent::beforePrepare();
    }
}
