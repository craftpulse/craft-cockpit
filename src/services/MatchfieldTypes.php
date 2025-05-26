<?php

namespace craftpulse\cockpit\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\StringHelper;

use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\events\MatchfieldTypeEvent;
use craftpulse\cockpit\records\MatchfieldType as MatchfieldTypeRecord;
use craftpulse\cockpit\models\MatchfieldType;

use Illuminate\Support\Collection;

/**
 * Class MatchfieldTypes
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class MatchfieldTypes extends Component
{
    /**
     * @event MatchFieldTypeEvent The event that is triggered before a matchfield type is saved.
     */
    public const EVENT_BEFORE_SAVE_MATCHFIELDTYPE = 'beforeSaveMatchfieldType';

    /**
     * @event MatchFieldTypeEvent The event that is triggered after a matchfield type is saved.
     */
    public const EVENT_AFTER_SAVE_MATCHFIELDTYPE = 'afterSaveMatchfieldType';

    public const CONFIG_MATCHFIELDTYPES_KEY = 'cockpit.matchfieldTypes';

    /**
     * @var array|null
     */
    private ?array $_allMatchfieldTypes = null;

    /**
     * @var array interim storage for matchfield types being saved via control panel
     */
    private array $_savingMatchfieldTypes = [];

    /**
     * Returns all the matchfield type IDs.
     *
     * @return array An array of all the matchfield types’ IDs.
     */
    public function getAllMatchfieldTypeIds(): array
    {
        return Make::collection($this->getAllMatchfieldTypes())->pluck('id')->all();
    }

    /**
     * Returns all matchfield types.
     *
     * @return MatchfieldType[] An array of all matchfield types.
     */
    public function getAllMatchfieldTypes(): array
    {
        if ($this->_allMatchfieldTypes !== null) {
            return $this->_allMatchfieldTypes;
        }

        $this->_allMatchfieldTypes = [];

        $results = $this->_createMatchfieldTypeQuery()->all();
        foreach ($results as $result) {
            $this->_allMatchfieldTypes[] = new MatchfieldType($result);
        }

        return $this->_allMatchfieldTypes;
    }

    /**
     * Saves a matchfield type.
     *
     * @param MatchfieldType $matchfieldType The matchfield type model.
     * @param bool $runValidation If validation should be ran.
     * @return bool Whether the matchfield type was saved successfully.
     * @throws Throwable if reasons
     */
    public function saveMatchfieldType(MatchfieldType $matchfieldType, bool $runValidation = true): bool
    {
        $isNewMatchfieldType = !$matchfieldType->id;

        // Fire a 'beforeSaveMatchfieldType' event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_MATCHFIELDTYPE)) {
            $this->trigger(self::EVENT_BEFORE_SAVE_MATCHFIELDTYPE, new MatchfieldTypeEvent([
                'matchfieldType' => $matchfieldType,
                'isNew' => $isNewMatchfieldType,
            ]));
        }

        Craft::dd($matchfieldType);

        if ($runValidation && !$matchfieldType->validate()) {
            Craft::info('Matchfield type not saved due to validation error.', __METHOD__);

            return false;
        }

        if ($isNewMatchfieldType) {
            $matchfieldType->uid = StringHelper::UUID();
        } else {
            /** @var MatchfieldTypeRecord|null $existingMatchfieldTypeRecord */
            $existingMatchfieldTypeRecord = MatchfieldTypeRecord::find()
                ->where(['id' => $matchfieldType->id])
                ->one();

            if (!$existingMatchfieldTypeRecord) {
                throw new MatchfieldTypeNotFoundException("No matchfield type exists with the ID '$matchfieldType->id'");
            }

            $matchfieldType->uid = $existingMatchfieldTypeRecord->uid;
        }

        $this->_savingMatchfieldTypes[$matchfieldType->uid] = $matchfieldType;

        $projectConfig = Craft::$app->getProjectConfig();

        $configData = $matchfieldType->getConfig();

        $configPath = self::CONFIG_MATCHFIELDTYPES_KEY . '.' . $matchfieldType->uid;
        $projectConfig->set($configPath, $configData);

        if ($isNewMatchfieldType) {
            $matchfieldType->id = Db::idByUid(Table::MATCHFIELD_TYPES, $matchfieldType->uid);
        }

        return true;
    }

    /**
     * Deletes a matchfield type by its ID.
     *
     * @param int $id the matchfield type's ID
     * @return bool Whether the matchfield type was deleted successfully.
     * @throws Throwable if reasons
     */
    public function deleteMatchfieldTypeById(int $id): bool
    {
        $matchfieldType = $this->getMatchfieldTypeById($id);
        Craft::$app->getProjectConfig()->remove(self::CONFIG_MATCHFIELDTYPES_KEY . '.' . $matchfieldType->uid);
        return true;
    }

    /**
     * Handle a matchfield type change.
     *
     * @throws Throwable if reasons
     */
    public function handleChangedMatchfieldType(ConfigEvent $event): void
    {
        $matchfieldTypeUid = $event->tokenMatches[0];
        $data = $event->newValue;

        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            // Basic data
            $matchfieldTypeRecord = $this->_getMatchfieldTypeRecord($matchfieldTypeUid);
            $isNewMatchfieldType = $matchfieldTypeRecord->getIsNewRecord();
            $fieldsService = Craft::$app->getFields();

            $matchfieldTypeRecord->uid = $matchfieldTypeUid;
            $matchfieldTypeRecord->name = $data['name'];
            $matchfieldTypeRecord->handle = $data['handle'];

            $matchfieldTypeRecord->save(false);

            $transaction->commit();
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }

        // Clear caches
        $this->_allMatchfieldTypes = null;

        // Fire an 'afterSaveMatchfieldType' event
        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_MATCHFIELDTYPE)) {
            $this->trigger(self::EVENT_AFTER_SAVE_MATCHFIELDTYPE, new MatchfieldTypeEvent([
                'matchfieldType' => $this->getMatchfieldTypeById($matchfieldTypeRecord->id),
                'isNew' => empty($this->_savingMatchfieldTypes[$matchfieldTypeUid]),
            ]));
        }
    }

    /**
     * Returns a Query object prepped for retrieving purchasables.
     *
     * @return Query The query object.
     */
    private function _createMatchfieldTypeQuery(): Query
    {
        $query = (new Query())
            ->select([
                'matchfieldTypes.id',
                'matchfieldTypes.name',
                'matchfieldTypes.handle',
                'matchfieldTypes.title',
                'matchfieldTypes.dateCreated',
                'matchfieldTypes.dateUpdated',
                'matchfieldTypes.cockpitId',
                'matchfieldTypes.uid',
            ])
            ->from([Table::MATCHFIELD_TYPES . ' matchfieldTypes']);

        return $query;
    }

    /**
     * Gets a matchfield type's record by uid.
     */
    private function _getMatchfieldTypeRecord(string $uid): MatchfieldTypeRecord
    {
        if ($matchfieldType = MatchfieldTypeRecord::findOne(['uid' => $uid])) {
            return $matchfieldType;
        }

        return new MatchfieldTypeRecord();
    }
}
