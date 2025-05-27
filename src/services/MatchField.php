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
use craft\base\MemoizableArray;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\enums\PropagationMethod;
use craft\errors\SiteNotFoundException;
use craft\errors\StructureNotFoundException;
use craft\errors\UnsupportedSiteException;
use craft\events\ConfigEvent;
use craft\helpers\AdminTable;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\Queue;
use craft\helpers\StringHelper;
use craft\i18n\Translation;
use craft\models\FieldLayout;
use craft\models\Structure;
use craft\queue\jobs\ApplyNewPropagationMethod;
use craft\queue\jobs\ResaveElements;
use craft\services\ProjectConfig;

use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\elements\MatchFieldEntry;
use craftpulse\cockpit\errors\MatchFieldNotFoundException;
use craftpulse\cockpit\events\MatchFieldEvent;
use craftpulse\cockpit\models\MatchField as MatchFieldModel;
use craftpulse\cockpit\models\MatchField_SiteSettings;
use craftpulse\cockpit\records\MatchField as MatchFieldRecord;
use craftpulse\cockpit\records\MatchField_SiteSettings as MatchField_SiteSettingsRecord;

use Illuminate\Support\Collection;
use Throwable;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Exception;

/**
 * The MatchField service provides APIs for managing match fields.
 *
 */
class MatchField extends Component
{
    /**
     * @event MatchFieldEvent The event that is triggered before a match field is saved.
     */
    public const EVENT_BEFORE_SAVE_MATCHFIELD = 'beforeSaveMatchField';

    /**
     * @event MatchFieldEvent The event that is triggered after a match field is saved.
     */
    public const EVENT_AFTER_SAVE_MATCHFIELD = 'afterSaveMatchField';

    /**
     * @event MatchFieldEvent The event that is triggered before a match field is deleted.
     */
    public const EVENT_BEFORE_DELETE_MATCHFIELD = 'beforeDeleteMatchField';

    /**
     * @event MatchFieldEvent The event that is triggered after a match field is deleted.
     */
    public const EVENT_AFTER_DELETE_MATCHFIELD = 'afterDeleteMatchField';

    public const CONFIG_MATCHFIELDS_KEY = 'cockpit.matchFields';


    // Properties
    // =========================================================================

    /**
     * @var bool Whether match field entries should be resaved after a match field has been updated.
     *
     */
    public bool $autoResaveEntries = true;

    /**
     * @var MemoizableArray<MatchFieldModel>|null
     * @see _matchfields()
     */
    private ?MemoizableArray $_matchFields = null;

    /**
     * Serializer
     */
    public function __serialize()
    {
        $vars = get_object_vars($this);
        unset($vars['_matchFields']);
        return $vars;
    }

    // Public Methods
    // =========================================================================

    /**
     * Returns all of the match fields IDs.
     *
     * @return int[] All the match fields’ IDs.
     */
    public function getAllMatchFieldIds(): array
    {
        return array_values(array_map(fn(MatchFieldModel $matchField) => $matchField->id, $this->getAllMatchFields()));
    }

    /**
     * Returns all of the match fields IDs that are editable by the current user.
     *
     * @return int[] All the editable match fields’ IDs.
     */
    public function getEditableMatchFieldIds(): array
    {
        return array_values(array_map(fn(MatchFieldModel $matchField) => $matchField->id, $this->getEditableMatchFields()));
    }

    /**
     * Returns a memoizable array of all match fields.
     *
     * @return MemoizableArray<MatchFieldModel>
     */
    private function _matchFields(): MemoizableArray
    {
        if (!isset($this->_matchFields)) {
            $results = $this->_createMatchFieldQuery()->all();
            $siteSettingsByMatchField = [];

            if (!empty($results) && Craft::$app->getRequest()->getIsCpRequest()) {
                // Eager load the site settings
                $matchFieldIds = array_map(fn(array $result) => $result['id'], $results);
                $siteSettingsByMatchField = ArrayHelper::index(
                    $this->_createMatchFieldSiteSettingsQuery()->where(['cockpit_matchfields_sites.matchFieldId' => $matchFieldIds])->all(),
                    null,
                    ['matchFieldId'],
                );
            }

            $this->_matchFields = new MemoizableArray($results, function(array $result) use (&$siteSettingsByMatchField) {
                if (!empty($result['previewTargets']) && is_string($result['previewTargets'])) {
                    $result['previewTargets'] = Json::decode($result['previewTargets']);
                } else {
                    $result['previewTargets'] = [];
                }
                $matchField = new MatchFieldModel($result);
                /** @phpstan-ignore-next-line */
                $siteSettings = ArrayHelper::remove($siteSettingsByMatchField, $matchField->id);
                if ($siteSettings !== null) {
                    $matchField->setSiteSettings(
                        array_map(fn(array $config) => new MatchField_SiteSettings($config), $siteSettings),
                    );
                }
                return $matchField;
            });
        }

        return $this->_matchFields;
    }

    /**
     * Returns a Query object prepped for retrieving match fields.
     *
     * @return Query
     */
    private function _createMatchFieldQuery(): Query
    {
        return (new Query())
            ->select([
                'cockpit_matchfields.id',
                'cockpit_matchfields.structureId',
                'cockpit_matchfields.fieldLayoutId',
                'cockpit_matchfields.name',
                'cockpit_matchfields.handle',
                'cockpit_matchfields.type',
                'cockpit_matchfields.enableVersioning',
                'cockpit_matchfields.defaultPlacement',
                'cockpit_matchfields.propagationMethod',
                'cockpit_matchfields.previewTargets',
                'cockpit_matchfields.uid',
                'structures.maxLevels',
            ])
            ->leftJoin(['structures' => CraftTable::STRUCTURES], [
                'and',
                '[[structures.id]] = [[cockpit_matchfields.structureId]]',
                ['structures.dateDeleted' => null],
            ])
            ->from(['cockpit_matchfields' => Table::MATCHFIELDS])
            ->where(['cockpit_matchfields.dateDeleted' => null])
            ->orderBy(['name' => SORT_ASC]);
    }

    /**
     * Returns all match fields.
     *
     * @return MatchFieldModel[] All the match fields.
     */
    public function getAllMatchFields(): array
    {
        return $this->_matchFields()->all();
    }

    /**
     * Returns all editable match fields.
     *
     * @return MatchFieldModel[] All the editable match fields.
     * @throws Throwable
     */
    public function getEditableMatchFields(): array
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return $this->getAllMatchFields();
        }

        $user = Craft::$app->getUser()->getIdentity();

        if (!$user) {
            return [];
        }

        return ArrayHelper::where($this->getAllMatchFields(), fn(MatchFieldModel $matchField) => $user->can("cockpit:view-match-fields:$matchField->uid"), true, true, false);
    }

    /**
     * Returns a match field by its ID.
     *
     * @param int $matchFieldId
     * @return MatchFieldModel|null
     */
    public function getMatchFieldById(int $matchFieldId): ?MatchFieldModel
    {
        return $this->_matchFields()->firstWhere('id', $matchFieldId);
    }

    /**
     * Gets a match field by its UID.
     *
     * @param string $uid
     * @return MatchFieldModel|null
     */
    public function getMatchFieldByUid(string $uid): ?MatchFieldModel
    {
        return $this->_matchFields()->firstWhere('uid', $uid, true);
    }

    /**
     * Gets a match field by its handle.
     *
     * @param string $matchFieldHandle
     * @return MatchFieldModel|null
     */
    public function getMatchFieldByHandle(string $matchFieldHandle): ?MatchFieldModel
    {
        return $this->_matchFields()->firstWhere('handle', $matchFieldHandle, true);
    }

    /**
     * Returns a match fields’ site-specific settings.
     *
     * @param int $matchFieldId
     * @return MatchField_SiteSettings[] The match fields’ site-specific settings.
     */
    public function getMatchFieldSiteSettings(int $matchFieldId): array
    {
        $siteSettings = $this->_createMatchFieldSiteSettingsQuery()
            ->where(['cockpit_matchfields_sites.matchFieldId' => $matchFieldId])
            ->all();

        foreach ($siteSettings as $key => $value) {
            $siteSettings[$key] = new MatchField_SiteSettings($value);
        }

        return $siteSettings;
    }

    /**
     * Returns a new match fields site settings query.
     *
     * @return Query
     */
    private function _createMatchFieldSiteSettingsQuery(): Query
    {
        return (new Query())
            ->select([
                'cockpit_matchfields_sites.id',
                'cockpit_matchfields_sites.matchFieldId',
                'cockpit_matchfields_sites.siteId',
                'cockpit_matchfields_sites.enabledByDefault',
                'cockpit_matchfields_sites.hasUrls',
                'cockpit_matchfields_sites.uriFormat',
                'cockpit_matchfields_sites.template',
            ])
            ->from(['cockpit_matchfields_sites' => Table::MATCHFIELDS_SITES])
            ->innerJoin(['sites' => CraftTable::SITES], [
                'and',
                '[[sites.id]] = [[cockpit_matchfields_sites.siteId]]',
                ['sites.dateDeleted' => null],
            ])
            ->orderBy(['sites.sortOrder' => SORT_ASC]);
    }

    /**
     * Saves a match field.
     *
     * @param MatchFieldModel $matchField The match field to be saved
     * @param bool $runValidation Whether the match field should be validated
     * @return bool
     * @throws MatchFieldNotFoundException if $matchField->id is invalid
     * @throws Throwable if reasons
     * @since 5.0.0
     */
    public function saveMatchField(MatchFieldModel $matchField, bool $runValidation = true): bool
    {
        $isNewMatchField = !$matchField->id;

        // Fire a 'beforeSaveMatchField' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_MATCHFIELD)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_MATCHFIELD, new MatchFieldEvent([
                'matchField' => $matchField,
                'isNew' => $isNewMatchField,
            ]));
        }

        if ($runValidation && !$matchField->validate()) {
            Craft::info('Match field not saved due to validation error.', __METHOD__);
            return false;
        }

        if ($isNewMatchField) {
            if (!$matchField->uid) {
                $matchField->uid = StringHelper::UUID();
            }
        } elseif (!$matchField->uid) {
            $matchField->uid = Db::uidById(Table::MATCHFIELDS, $matchField->id);
        }

        // If they've set maxLevels to 0 (don't ask why), then pretend like there are none.
        if ((int)$matchField->maxLevels === 0) {
            $matchField->maxLevels = null;
        }

        // Make sure the match field isn't missing any site settings
        $allSiteSettings = $matchField->getSiteSettings();
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            if (!isset($allSiteSettings[$siteId])) {
                throw new Exception('Tried to save a match field that is missing site settings');
            }
        }

        // Save the match field config
        // -----------------------------------------------------------------

        $configPath = self::CONFIG_MATCHFIELDS_KEY . '.' . $matchField->uid;
        $configData = $matchField->getConfig();
        Craft::$app->getProjectConfig()->set($configPath, $configData, "Save match field “{$matchField->handle}”");

        if ($isNewMatchField) {
            $matchField->id = Db::idByUid(Table::MATCHFIELDS, $matchField->uid);
        }

        return true;
    }

    /**
     * Handle match field change
     *
     * @param ConfigEvent $event
     * @throws Throwable
     * @throws SiteNotFoundException
     * @throws StructureNotFoundException
     * @throws UnsupportedSiteException
     * @throws Exception
     */
    public function handleChangedMatchField(ConfigEvent $event): void
    {
        ProjectConfigHelper::ensureAllSitesProcessed();
        ProjectConfigHelper::ensureAllFieldsProcessed();

        $matchFieldUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $siteSettingData = $data['siteSettings'];

            // Basic data
            $matchFieldRecord = $this->_getMatchFieldRecord($matchFieldUid, true);
            $matchFieldRecord->uid = $matchFieldUid;
            $matchFieldRecord->name = $data['name'];
            $matchFieldRecord->handle = $data['handle'];
            $matchFieldRecord->type = $data['type'];
            $matchFieldRecord->enableVersioning = (bool)$data['enableVersioning'];
            $matchFieldRecord->propagationMethod = $data['propagationMethod'] ?? PropagationMethod::All->value;
            $matchFieldRecord->defaultPlacement = $data['defaultPlacement'] ?? MatchFieldModel::DEFAULT_PLACEMENT_END;
            $matchFieldRecord->previewTargets = isset($data['previewTargets']) && is_array($data['previewTargets'])
                ? ProjectConfigHelper::unpackAssociativeArray($data['previewTargets'])
                : null;

            $isNewMatchField = $matchFieldRecord->getIsNewRecord();
            $propagationMethodChanged = $matchFieldRecord->propagationMethod != $matchFieldRecord->getOldAttribute('propagationMethod');

            $structuresService = Craft::$app->getStructures();

            // Save the structure
            $structureUid = $data['structure']['uid'];
            $structure = $structuresService->getStructureByUid($structureUid, true) ?? new Structure(['uid' => $structureUid]);
            $isNewStructure = empty($structure->id);
            $structure->maxLevels = $data['structure']['maxLevels'];

            // check if we need to soft-delete an old structure
            if (
                $isNewStructure &&
                ($event->oldValue['structure']['uid'] ?? null) !== $structureUid &&
                $matchFieldRecord->structureId
            ) {
                $structuresService->deleteStructureById($matchFieldRecord->structureId);
            }

            $structuresService->saveStructure($structure);
            $matchFieldRecord->structureId = $structure->id;

            // Save the field layout
            if (!empty($data['fieldLayouts'])) {
                // Save the field layout
                $layout = FieldLayout::createFromConfig(reset($data['fieldLayouts']));
                $layout->id = $matchFieldRecord->fieldLayoutId;
                $layout->type = MatchFieldEntry::class;
                $layout->uid = key($data['fieldLayouts']);
                Craft::$app->getFields()->saveLayout($layout, false);
                $matchFieldRecord->fieldLayoutId = $layout->id;
            } elseif ($matchFieldRecord->fieldLayoutId) {
                // Delete the field layout
                Craft::$app->getFields()->deleteLayoutById($matchFieldRecord->fieldLayoutId);
                $matchFieldRecord->fieldLayoutId = null;
            }

            $resaveEntries = (
                $matchFieldRecord->handle !== $matchFieldRecord->getOldAttribute('handle') ||
                $matchFieldRecord->type !== $matchFieldRecord->getOldAttribute('type') ||
                $propagationMethodChanged ||
                $matchFieldRecord->structureId != $matchFieldRecord->getOldAttribute('structureId')
            );

            $wasTrashed = $matchFieldRecord->dateDeleted;
            if ($wasTrashed) {
                $matchFieldRecord->restore();
                $resaveEntries = true;
            } else {
                $matchFieldRecord->save(false);
            }

            // Update the site settings
            // -----------------------------------------------------------------

            if (!$isNewMatchField) {
                // Get the old match field site settings
                $allOldSiteSettingsRecords = MatchField_SiteSettingsRecord::find()
                    ->where(['matchFieldId' => $matchFieldRecord->id])
                    ->indexBy('siteId')
                    ->all();
            } else {
                $allOldSiteSettingsRecords = [];
            }

            $siteIdMap = Db::idsByUids(CraftTable::SITES, array_keys($siteSettingData));
            $hasNewSite = false;

            foreach ($siteSettingData as $siteUid => $siteSettings) {
                $siteId = $siteIdMap[$siteUid];

                // Was this already selected?
                if (!$isNewMatchField && isset($allOldSiteSettingsRecords[$siteId])) {
                    /** @var MatchField_SiteSettingsRecord $siteSettingsRecord */
                    $siteSettingsRecord = $allOldSiteSettingsRecords[$siteId];
                } else {
                    $siteSettingsRecord = new MatchField_SiteSettingsRecord();
                    $siteSettingsRecord->matchFieldId = $matchFieldRecord->id;
                    $siteSettingsRecord->siteId = $siteId;
                    $resaveEntries = true;
                    $hasNewSite = true;
                }

                $siteSettingsRecord->enabledByDefault = $siteSettings['enabledByDefault'];

                if ($siteSettingsRecord->hasUrls = $siteSettings['hasUrls']) {
                    $siteSettingsRecord->uriFormat = $siteSettings['uriFormat'];
                    $siteSettingsRecord->template = $siteSettings['template'];
                } else {
                    $siteSettingsRecord->uriFormat = $siteSettings['uriFormat'] = null;
                    $siteSettingsRecord->template = $siteSettings['template'] = null;
                }

                $resaveEntries = (
                    $resaveEntries ||
                    $siteSettingsRecord->hasUrls != $siteSettingsRecord->getOldAttribute('hasUrls') ||
                    $siteSettingsRecord->uriFormat !== $siteSettingsRecord->getOldAttribute('uriFormat')
                );

                $siteSettingsRecord->save(false);
            }

            if (!$isNewMatchField) {
                // Drop any sites that are no longer being used, as well as the associated entry/element site
                // rows
                $affectedSiteUids = array_keys($siteSettingData);

                foreach ($allOldSiteSettingsRecords as $siteId => $siteSettingsRecord) {
                    $siteUid = array_search($siteId, $siteIdMap, false);
                    if (!in_array($siteUid, $affectedSiteUids, false)) {
                        $siteSettingsRecord->delete();
                        $resaveEntries = true;
                    }
                }
            }

            // Finally, deal with the existing entries...
            // -----------------------------------------------------------------

            if (!$isNewMatchField && $resaveEntries) {
                // If the propagation method just changed, we definitely need to update match field entries for that
                if ($propagationMethodChanged) {
                    Queue::push(new ApplyNewPropagationMethod([
                        'description' => Translation::prep('cockpit', 'Applying new propagation method to {name} match field', [
                            'name' => $matchFieldRecord->name,
                        ]),
                        'elementType' => MatchFieldEntry::class,
                        'criteria' => [
                            'matchFieldId' => $matchFieldRecord->id,
                            'structureId' => $matchFieldRecord->structureId,
                        ],
                    ]));
                } elseif ($this->autoResaveEntries) {
                    Queue::push(new ResaveElements([
                        'description' => Translation::prep('cockpit', 'Resaving {name} match field entries', [
                            'name' => $matchFieldRecord->name,
                        ]),
                        'elementType' => MatchFieldEntry::class,
                        'criteria' => [
                            'matchFieldId' => $matchFieldRecord->id,
                            'siteId' => array_values($siteIdMap),
                            'preferSites' => [Craft::$app->getSites()->getPrimarySite()->id],
                            'unique' => true,
                            'status' => null,
                            'drafts' => null,
                            'provisionalDrafts' => null,
                            'revisions' => null,
                        ],
                        'updateSearchIndex' => $hasNewSite,
                    ]));
                }
            }

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_matchFields = null;

        if ($wasTrashed) {
            /** @var MatchFieldEntry[] $matchFieldEntries */
            $matchFieldEntries = MatchFieldEntry::find()
                ->matchFieldId($matchFieldRecord->id)
                ->drafts(null)
                ->draftOf(false)
                ->status(null)
                ->trashed()
                ->site('*')
                ->unique()
                ->andWhere(['cockpit_matchfield_entries.deletedWithMatchField' => true])
                ->all();
            /** @var MatchFieldEntry[][] $matchFieldEntriesByType */
            $matchFieldEntriesByType = ArrayHelper::index($matchFieldEntries, null, ['typeId']);
            foreach ($matchFieldEntriesByType as $typeEntries) {
                try {
                    array_walk($typeEntries, function(MatchFieldEntry $matchFieldEntry) {
                        $matchFieldEntry->deletedWithMatchField = false;
                    });
                    Craft::$app->getElements()->restoreElements($typeEntries);
                } catch (InvalidConfigException) {
                    // the entry type probably wasn't restored
                }
            }
        }

        /** @var MatchFieldModel $matchField */
        $matchField = $this->getMatchFieldById($matchFieldRecord->id);

        // Fire an 'afterSaveMatchField' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_MATCHFIELD)) {
            $this->trigger(self::EVENT_AFTER_SAVE_MATCHFIELD, new MatchFieldEvent([
                'matchField' => $matchField,
                'isNew' => $isNewMatchField,
            ]));
        }

        // Invalidate entry caches
        Craft::$app->getElements()->invalidateCachesForElementType(MatchFieldEntry::class);
    }

    /**
     * Returns data for the Match field index page in the control panel.
     *
     * @param int $page
     * @param int $limit
     * @param string|null $searchTerm
     * @param string $orderBy
     * @param int $sortDir
     * @return array
     */
    public function getMatchFieldTableData(
        int $page,
        int $limit,
        ?string $searchTerm,
        string $orderBy = 'name',
        int $sortDir = SORT_ASC,
    ): array {
        [$results, $total] = $this->prepTableData($this->_createMatchFieldQuery(), $page, $limit, $searchTerm, $orderBy, $sortDir);

        /** @var MatchField[] $matchFields */
        $matchFields = array_values(array_filter(
            array_map(fn(array $result) => $this->_matchFields()->firstWhere('id', $result['id']), $results)
        ));

        $tableData = [];

        foreach ($matchFields as $matchField) {
            $label = $matchField->getUiLabel();
            $tableData[] = [
                'id' => $matchField->id,
                'title' => $label,
                'name' => $label,
                'url' => $matchField->getCpEditUrl(),
                'handle' => $matchField->handle,
                // @TODO: add type data from API
                'type' => '1',
            ];
        }

        $pagination = AdminTable::paginationLinks($page, $total, $limit);

        return [$pagination, $tableData];
    }

    /**
     * Returns query results needed for the VueAdminTable accounting for the pagination, search terms and sorting options.
     *
     * @param Query $query
     * @param int $page
     * @param int $limit
     * @param string|null $searchTerm
     * @param string $orderBy
     * @param int $sortDir
     * @return array
     */
    private function prepTableData(
        Query $query,
        int $page,
        int $limit,
        ?string $searchTerm,
        string $orderBy = 'name',
        int $sortDir = SORT_ASC,
    ): array {
        $searchTerm = $searchTerm ? trim($searchTerm) : $searchTerm;

        $offset = ($page - 1) * $limit;
        $query = $query
            ->orderBy([$orderBy => $sortDir]);

        if ($orderBy === 'name') {
            $query->addOrderBy(['name' => $sortDir]);
        }

        if ($searchTerm !== null && $searchTerm !== '') {
            $searchParams = $this->_getSearchParams($searchTerm);
            if (!empty($searchParams)) {
                $query->andWhere(['or', ...$searchParams]);
            }
        }

        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        return [$query->all(), $total];
    }

    /**
     * Returns the sql expression to be used in the 'where' param for the query.
     *
     * @param string $term
     * @return array
     */
    private function _getSearchParams(string $term): array
    {
        $searchParams = ['name', 'handle'];
        $searchQueries = [];

        if ($term !== '') {
            foreach ($searchParams as $param) {
                $searchQueries[] = ['like', $param, '%' . $term . '%', false];
            }
        }

        return $searchQueries;
    }

    /**
     * Gets a match field' record by uid.
     *
     * @param string $uid
     * @param bool $withTrashed Whether to include trashed sections in search
     * @return MatchFieldRecord
     */
    private function _getMatchFieldRecord(string $uid, bool $withTrashed = false): MatchFieldRecord
    {
        $query = $withTrashed ? MatchFieldRecord::findWithTrashed() : MatchFieldRecord::find();
        $query->andWhere(['uid' => $uid]);
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        /** @var MatchFieldRecord */
        return $query->one() ?? new MatchFieldRecord();
    }
}
