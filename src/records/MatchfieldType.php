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

use craftpulse\cockpit\db\Table;
use craft\db\ActiveRecord;
use craft\records\FieldLayout;
use yii\db\ActiveQueryInterface;

/**
 * Matchfield type record.
 *
 * @property int $id
 * @property FieldLayout $fieldLayout
 * @property string $name
 * @property string $handle
 * @property bool $enableVersioning
 * @property string $titleFormat
 * @property string $titleTranslationMethod
 * @property string $titleTranslationKeyFormat
 * @property string $propagationMethod
 * @property string $cockpitId
 */
class MatchfieldType extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::MATCHFIELD_TYPES;
    }

    public function getFieldLayout(): ActiveQueryInterface
    {
        return $this->hasOne(FieldLayout::class, ['id' => 'fieldLayoutId']);
    }
}
