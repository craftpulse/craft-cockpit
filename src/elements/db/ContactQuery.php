<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;
use craftpulse\cockpit\db\Table;

/**
 * Contact query
 */
class ContactQuery extends ElementQuery
{
    protected function beforePrepare(): bool
    {
        $this->joinElementTable(Table::CONTACTS);

        $this->query->select([
            'cockpit_contacts.cockpitId',
        ]);

        return parent::beforePrepare();
    }
}
