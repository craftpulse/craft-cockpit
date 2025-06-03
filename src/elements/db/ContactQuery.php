<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craftpulse\cockpit\db\Table;

/**
 * Contact query
 */
class ContactQuery extends ElementQuery
{
    public ?string $cockpitId = null;

    public function cockpitId($value)
    {
        $this->cockpitId = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable(Table::CONTACTS);

        $this->query->select([
            'cockpit_contacts.cockpitId',
            'cockpit_contacts.cockpitDepartmentIds',
            'cockpit_contacts.firstName',
            'cockpit_contacts.lastName',
            'cockpit_contacts.email',
            'cockpit_contacts.phone',
            'cockpit_contacts.functionTitle',
        ]);

        if ($this->cockpitId) {
            $this->subQuery->andWhere(Db::parseParam('cockpit_contacts.cockpitId', $this->cockpitId));
        }

        return parent::beforePrepare();
    }
}
