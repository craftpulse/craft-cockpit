<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;

/**
 * Match Field Entry query
 */
class MatchFieldEntryQuery extends ElementQuery
{
    protected function beforePrepare(): bool
    {
        // todo: join the `matchfieldentries` table
        // $this->joinElementTable('matchfieldentries');

        // todo: apply any custom query params
        // ...

        return parent::beforePrepare();
    }
}
