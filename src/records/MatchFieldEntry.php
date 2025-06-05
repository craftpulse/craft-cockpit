<?php

namespace craftpulse\cockpit\records;

use craft\db\ActiveRecord;
use craft\records\Element;

use craftpulse\cockpit\db\Table;

use yii\db\ActiveQueryInterface;

/**
 * Match field entry record.
 *
 * @property int $id ID
 * @property int $matchFieldId Group ID
 * @property Element $element Element
 * @property MatchField $matchField Group
 * @property string $cockpitId
 */
class MatchFieldEntry extends ActiveRecord
{
    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return Table::MATCHFIELDS_ENTRIES;
    }

    /**
     * Returns the match fields’ element.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getElement(): ActiveQueryInterface
    {
        return $this->hasOne(Element::class, ['id' => 'id']);
    }

    /**
     * Returns the match fields’ type.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getMatchField(): ActiveQueryInterface
    {
        return $this->hasOne(MatchField::class, ['id' => 'matchFieldId']);
    }
}
