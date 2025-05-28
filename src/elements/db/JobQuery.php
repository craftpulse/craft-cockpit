<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\elements\db\ElementQuery;
use craftpulse\cockpit\db\Table;

/**
 * Job query
 */
class JobQuery extends ElementQuery
{
    protected function beforePrepare(): bool
    {
        // todo: join the `companies` table
        $this->joinElementTable(Table::JOBS);

        // todo: apply any custom query params
        $this->query->select([
            'cockpit_jobs.applicationCount',
            'cockpit_jobs.city',
            'cockpit_jobs.cockpitCompanyId',
            'cockpit_jobs.cockpitId',
            'cockpit_jobs.cockpitJobRequestId',
            'cockpit_jobs.cockpitOfficeId',
            'cockpit_jobs.companyName',
            'cockpit_jobs.expiryDate',
            'cockpit_jobs.fieldLayoutId',
            'cockpit_jobs.latitude',
            'cockpit_jobs.longitude',
            'cockpit_jobs.openPositions',
            'cockpit_jobs.postCode',
            'cockpit_jobs.postDate',
            'cockpit_jobs.street',
        ]);

        return parent::beforePrepare();
    }
}
