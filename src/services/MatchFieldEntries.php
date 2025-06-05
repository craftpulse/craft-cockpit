<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\services;

use Craft;
use craft\events\SiteEvent;
use craft\helpers\Queue;
use craft\queue\jobs\PropagateElements;

use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\elements\MatchFieldEntry;

use yii\base\Component;

/**
 * The MatchFieldEntries service provides APIs for managing match field entries.
 */
class MatchFieldEntries extends Component
{
    /**
     * Return a match field by its ID.
     *
     * @param int $id
     * @param array|int|string|null $siteId
     * @param array $criteria
     * @return MatchFieldEntry|null
     */
    public function getMatchFieldEntryById(int $id, array|int|string $siteId = null, array $criteria = []): ?MatchFieldEntry
    {
        if (!$id) {
            return null;
        }

        // Get the structure ID
        if (!isset($criteria['structureId'])) {
            $criteria['structureId'] = (new Query())
                ->select(['cockpit_matchfields.structureId'])
                ->from(['cockpit_matchfields_entries' => Table::MATCHFIELDS_ENTRIES])
                ->innerJoin(['cockpit_matchfields' => Table::MATCHFIELDS], '[[cockpit_matchfields.id]] = [[cockpit_matchfields_entries.matchFieldId]]')
                ->where(['[[cockpit_matchfields_entries.id]]' => $id])
                ->scalar();
        }

        return Craft::$app->elements->getElementById($id, MatchFieldEntry::class, $siteId, $criteria);
    }

    /**
     * Handle a Site being saved.
     */
    public function afterSaveSiteHandler(SiteEvent $event): void
    {
        if (
            $event->isNew &&
            isset($event->oldPrimarySiteId) &&
            Craft::$app->getPlugins()->isPluginInstalled(Cockpit::getInstance()->id)
        ) {
            Queue::push(new PropagateElements([
                'elementType' => MatchFieldEntry::class,
                'criteria' => [
                    'siteId' => $event->oldPrimarySiteId,
                    'status' => null,
                ],
                'siteId' => $event->site->id,
            ]));
        }
    }
}
