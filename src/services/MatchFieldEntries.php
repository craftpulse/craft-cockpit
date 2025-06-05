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
use craft\errors\ElementNotFoundException;
use craft\events\SiteEvent;
use craft\helpers\Queue;
use craft\queue\jobs\PropagateElements;

use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\elements\MatchFieldEntry;

use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\Exception;

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
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function saveMatchFieldEntry(Collection $data): bool
    {
        $matchFieldEntry = MatchFieldEntry::find()->cockpitId($data->get('id'))->one() ?? new MatchFieldEntry();

        // Save the native fields
        $matchFieldEntry->cockpitId = $data->get('id');
        $matchFieldEntry->matchFieldId = (int)$data->get('matchFieldId');
        $matchFieldEntry->title = $data->get('name');

        Craft::info('Saving match field: ' . json_encode($matchFieldEntry) . 'data: ' . json_encode($data), __METHOD__);

        // Validate our match field entry
        if (!$matchFieldEntry->validate()) {
            Craft::error('Match field element invalid: ' . print_r($matchFieldEntry->getErrors(), true), __METHOD__);
            return false;
        }

        // Save our match field
        if (!Craft::$app->elements->saveElement($matchFieldEntry)) {
            Craft::error('Unable to save match field.', __METHOD__);
            return false;
        }

        return true;
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
