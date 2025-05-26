<?php

namespace craftpulse\cockpit\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\db\Table as CraftTable;

use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\records\MatchfieldType as MatchfieldTypeRecord;
use craftpulse\models\MatchfieldType;

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
    public const CONFIG_MATCHFIELDTYPES_KEY = 'cockpit.matchfieldTypes';

    /**
     * @var array|null
     */
    private ?array $_allMatchfieldTypes = null;

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
     * Returns a Query object prepped for retrieving purchasables.
     *
     * @return Query The query object.
     */
    private function _createMatchfieldTypeQuery(): Query
    {
        $query = (new Query())
            ->select([
                'matchfieldTypes.id',
                'matchfieldTypes.fieldLayoutId',
                'matchfieldTypes.name',
                'matchfieldTypes.handle',
                'matchfieldTypes.enableVersioning',
                'matchfieldTypes.title',
                'matchfieldTypes.titleFormat',
                'matchfieldTypes.titleTranslationMethod',
                'matchfieldTypes.titleTranslationKeyFormat',
                'matchfieldTypes.propagationMethod',
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
