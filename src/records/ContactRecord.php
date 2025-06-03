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
use Illuminate\Support\Collection;

/**
 * Class MatchField
 *
 * @property string $cockpitId
 * @property Collection $cockpitDepartmentIds
 * @property string $firstName
 * @property string $lastName
 * @property string $email
 * @property string $phone
 * @property string $functionTitle
 * @property string|null $fieldLayoutId
 */
class ContactRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::CONTACTS;
    }

}
