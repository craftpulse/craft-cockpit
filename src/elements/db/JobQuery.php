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

    /**
     * @return bool
     * @throws \craft\db\QueryAbortedException
     */
    protected function beforePrepare(): bool
    {
        // todo: join the `companies` table
        $this->joinElementTable(Table::JOBS);

        // todo: apply any custom query params
        $this->query->select([
            'cockpit_jobs.applicationCount',
            'cockpit_jobs.cockpitCompanyId',
            'cockpit_jobs.cockpitId',
            'cockpit_jobs.cockpitJobRequestId',
            'cockpit_jobs.cockpitOfficeId',
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

        return parent::beforePrepare();
    }
}
