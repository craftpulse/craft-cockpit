<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craftpulse\cockpit\db\Table;

/**
 * Department query
 */
class DepartmentQuery extends ElementQuery
{
    public ?string $cockpitId = null;

    public function cockpitId($value)
    {
        $this->cockpitId = $value;
        return $this;
    }

    protected function beforePrepare(): bool
    {
        // todo: join the `companies` table
        $this->joinElementTable(Table::DEPARTMENTS);

        // todo: apply any custom query params
        $this->query->select([
            'cockpit_departments.cockpitId',
        ]);

        if ($this->cockpitId) {
            $this->subQuery->andWhere(Db::parseParam('cockpit_departments.cockpitId', $this->cockpitId));
        }

        return parent::beforePrepare();
    }
}
