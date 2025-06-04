<?php

namespace craftpulse\cockpit\records;

use Craft;
use craft\db\ActiveRecord;
use craftpulse\cockpit\db\Table;

/**
 * Candidate record
 *
 * @property int $id ID
 * @property string $dateCreated Date created
 * @property string $dateUpdated Date updated
 * @property string $uid Uid
 * @property int $userId User ID
 * @property string $cockpitId Cockpit ID
 */
class Candidate extends ActiveRecord
{
    public static function tableName()
    {
        return Table::CANDIDATES;
    }
}
