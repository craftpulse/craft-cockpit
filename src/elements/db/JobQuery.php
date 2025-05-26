<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

/**
 * Job query
 */
class JobQuery extends ElementQuery
{
    protected function beforePrepare(): bool
    {
        // todo: join the `jobs` table
        // $this->joinElementTable('jobs');

        // todo: apply any custom query params
        // ...

        return parent::beforePrepare();
    }
}
