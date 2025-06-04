<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\records;

use DateTime;
use craft\db\ActiveRecord;
use craftpulse\cockpit\db\Table;

/**
 * Class MatchField
 *
 * @property string|null $applicationCount
 * @property string $city
 * @property DateTime $postDate
 * @property DateTime $expiryDate
 * @property string $cockpitCompanyId
 * @property string $cockpitId
 * @property string $cockpitJobRequestId
 * @property string $cockpitDepartmentId
 * @property string $cockpitContactId
 * @property string $companyName
 * @property string|null $fieldLayoutId
 * @property string|int|null $id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $openPositions
 * @property string|null $postCode
 * @property string|null $street
 * @property string|null $title
 */
class JobRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::JOBS;
    }

}
