<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

/**
 * Contact query
 */
class ContactQuery extends ElementQuery
{
    protected function beforePrepare(): bool
    {
        // todo: join the `contacts` table
        // $this->joinElementTable('contacts');

        // todo: apply any custom query params
        // ...

        return parent::beforePrepare();
    }
}
