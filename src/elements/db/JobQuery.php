<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use craftpulse\cockpit\db\Table;

/**
 * Job query
 */
class JobQuery extends ElementQuery
{
    public ?string $cockpitId = null;
    public ?string $cockpitJobRequestId = null;
    public ?string $cockpitDepartmentId = null;

    public function cockpitId($value)
    {
        $this->cockpitId = $value;
        return $this;
    }

    public function cockpitJobRequestId($value)
    {
        $this->cockpitJobRequestId = $value;
        return $this;
    }

    public function cockpitDepartmentId($value)
    {
        $this->cockpitDepartmentId = $value;
        return $this;
    }

    /**
     * @return bool
     * @throws \craft\db\QueryAbortedException
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable(Table::JOBS);

        // Check if sorting by department
        $orderByKeys = array_keys($this->orderBy ?? []);
        $sortingByDepartment = in_array('department', $orderByKeys, true);

        if ($sortingByDepartment) {
            $this->subQuery->leftJoin(
                '{{%cockpit_departments}} departments',
                'departments.id = cockpit_jobs.cockpitDepartmentId'
            );

            $this->subQuery->addSelect(['departments.title AS department']);

            // Apply explicit order by with direction (asc/desc)
            $direction = reset($this->orderBy) ?: SORT_ASC; // get the direction for 'department'
            $this->subQuery->orderBy(['department' => $direction]);
        }

        $this->query->select([
            'cockpit_jobs.applicationCount',
            'cockpit_jobs.cockpitCompanyId',
            'cockpit_jobs.cockpitId',
            'cockpit_jobs.cockpitJobRequestId',
            'cockpit_jobs.cockpitContactId',
            'cockpit_jobs.cockpitDepartmentId',
            'cockpit_jobs.companyName',
            'cockpit_jobs.expiryDate',
            'cockpit_jobs.fieldLayoutId',
            'cockpit_jobs.openPositions',
            'cockpit_jobs.postDate',
            'cockpit_jobs.title',
        ]);

        if ($this->cockpitId) {
            $this->subQuery->andWhere(Db::parseParam('cockpit_jobs.cockpitId', $this->cockpitId));
        }

        if ($this->cockpitJobRequestId) {
            $this->subQuery->andWhere(Db::parseParam('cockpit_jobs.cockpitJobRequestId', $this->cockpitJobRequestId));
        }

        if ($this->cockpitDepartmentId) {
            $this->subQuery->andWhere(Db::parseParam('cockpit_jobs.cockpitDepartmentId', $this->cockpitDepartmentId));
        }

        return parent::beforePrepare();
    }
}
