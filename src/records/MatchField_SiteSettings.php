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

use craft\db\ActiveRecord;
use craft\records\Site;

use craftpulse\cockpit\db\Table;
use yii\db\ActiveQueryInterface;

/**
 * Class MatchField_SiteSettings record.
 *
 * @property int $id ID
 * @property int $matchFieldId Match field ID
 * @property int $siteId Site ID
 * @property bool $enabledByDefault Enabled by default
 * @property bool $hasUrls Has URLs
 * @property string|null $uriFormat URI format
 * @property string|null $template Template
 * @property MatchField $matchField MatchField
 * @property Site $site Site
 */
class MatchField_SiteSettings extends ActiveRecord
{
    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return Table::MATCHFIELDS_SITES;
    }

    /**
     * Returns the associated match field.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getMatchField(): ActiveQueryInterface
    {
        return $this->hasOne(MatchField::class, ['id' => 'matchFieldId']);
    }

    /**
     * Returns the associated site.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getSite(): ActiveQueryInterface
    {
        return $this->hasOne(Site::class, ['id' => 'siteId']);
    }

}
