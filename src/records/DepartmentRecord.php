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
 * @property string $cockpitId
 * @property string|null $title
 */
class DepartmentRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::DEPARTMENTS;
    }

}
