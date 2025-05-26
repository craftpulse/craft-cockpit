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
use craft\db\SoftDeleteTrait;
use craft\records\Structure;
use yii\db\ActiveQueryInterface;
use yii2tech\ar\softdelete\SoftDeleteBehavior;

/**
 * Class MatchField record.
 *
 * @property int $id ID
 * @property int|null $structureId Structure ID
 * @property string $name Name
 * @property string $handle Handle
 * @property string $type Type
 * @property bool $enableVersioning Enable versioning
 * @property bool $propagationMethod Propagation method
 * @property string $defaultPlacement Default placement
 * @property array|null $previewTargets Preview targets
 * @property string $cockpitId
 * @property MatchField_SiteSettings[] $siteSettings Site settings
 * @property Structure $structure Structure
 * @mixin SoftDeleteBehavior
 */
class MatchField extends ActiveRecord
{
    use SoftDeleteTrait;

    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return Table::MATCHFIELDS;
    }

    /**
     * Returns the associated site settings.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getSiteSettings(): ActiveQueryInterface
    {
        return $this->hasMany(MatchField_SiteSettings::class, ['matchFieldId' => 'id']);
    }

    /**
     * Returns the match fields’ structure.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getStructure(): ActiveQueryInterface
    {
        return $this->hasOne(Structure::class, ['id' => 'structureId']);
    }
}
