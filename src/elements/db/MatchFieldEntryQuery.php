<?php

namespace craftpulse\cockpit\elements\db;

use Craft;
use craft\db\Query;
use craft\db\QueryAbortedException;
use craft\elements\db\ElementQuery;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;

use craft\helpers\StringHelper;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\elements\MatchFieldEntry;
use craftpulse\cockpit\models\MatchField as MatchFieldModel;

use DateTime;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Expression;

/**
 * Match Field Entry query
 */
class MatchFieldEntryQuery extends ElementQuery
{
    /**
     * @var bool Whether to only return match fields that the user has permission to edit.
     */
    public bool $editable = false;

    /**
     * @var mixed The Post Date that the resulting match fields must have.
     */
    public mixed $expiryDate = null;

    /**
     * @var mixed The Post Date that the resulting match fields must have.
     */
    public mixed $postDate = null;

    /**
     * @var mixed The match field ID(s) that the resulting match fields must have.
     */
    public mixed $matchFieldId = null;

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        if ($name === 'matchField') {
            $this->matchField($value);
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        if (!isset($this->withStructure)) {
            $this->withStructure = true;
        }

        parent::init();
    }

    /**
     * Sets the [[$editable]] property.
     *
     * @param bool $value The property value (defaults to true)
     * @return static self reference
     * @uses $editable
     */
    public function editable(bool $value = true): static
    {
        $this->editable = $value;
        return $this;
    }

    /**
     * Narrows the query results based on the match field types the match fields belong to.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @throws InvalidConfigException
     * @uses $matchFieldId
     */
    public function matchField(mixed $value): static
    {
        if ($value instanceof MatchFieldModel) {
            // Special case for a single category group, since we also want to capture the structure ID
            $this->structureId = ($value->structureId ?: false);
            $this->matchFieldId = [$value->id];
        } elseif (Db::normalizeParam($value, function($item) {
            if (is_string($item)) {
                $item = Cockpit::$plugin->getMatchFields()->getMatchFieldByHandle($item);
            }
            return $item instanceof MatchFieldModel ? $item->id : null;
        })) {
            $this->matchFieldId = $value;
        } else {
            $this->matchFieldId = (new Query())
                ->select(['id'])
                ->from(Table::MATCHFIELDS)
                ->where(Db::parseParam('handle', $value))
                ->column();
        }

        return $this;
    }

    /**
     * Narrows the query results based on the match field types the match fields belong to, per the match fields’ IDs.
     *
     * @param mixed $value The property value
     * @return static self reference
     * @uses $groupId
     */
    public function matchFieldId(mixed $value): static
    {
        $this->matchFieldId = $value;
        return $this;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    protected function beforePrepare(): bool
    {
        if (!parent::beforePrepare()) {
            return false;
        }

        $this->_normalizeMatchFieldId();

        // See if 'group' was set to an invalid handle
        if ($this->matchFieldId === []) {
            return false;
        }

        $this->joinElementTable(Table::MATCHFIELDS_ENTRIES);

        $this->query->addSelect([
            'cockpit_matchfields_entries.matchFieldId',
        ]);

        $this->_applyEditableParam();
        $this->_applyMatchFieldIdParam();
        $this->_applyRefParam();

        return true;
    }

    /**
     * Applies the 'editable' param to the query being prepared.
     *
     * @throws InvalidConfigException
     */
    private function _applyEditableParam(): void
    {
        if ($this->editable) {
            // Limit the query to only the category groups the user has permission to edit
            $this->subQuery->andWhere([
                'cockpit_matchfields_entries.matchFieldId' => Cockpit::$plugin->getMatchFields()->getEditableMatchFieldIds(),
            ]);
        }
    }

    /**
     * Applies the 'matchFieldId' param to the query being prepared.
     */
    private function _applyMatchFieldIdParam(): void
    {
        if ($this->matchFieldId) {
            $this->subQuery->andWhere(['cockpit_matchfields_entries.matchFieldId' => $this->matchFieldId]);

            // Should we set the structureId param?
            if (!isset($this->structureId) && count($this->matchFieldId) === 1) {
                $structureId = (new Query())
                    ->select(['structureId'])
                    ->from([Table::MATCHFIELDS])
                    ->where(Db::parseNumericParam('id', $this->matchFieldId))
                    ->scalar();
                $this->structureId = (int)$structureId ?: false;
            }
        }
    }

    /**
     * Normalizes the matchFieldId param to an array of IDs or null
     */
    private function _normalizeMatchFieldId(): void
    {
        if (empty($this->groupId)) {
            $this->matchFieldId = is_array($this->matchFieldId) ? [] : null;
        } elseif (is_numeric($this->matchFieldId)) {
            $this->matchFieldId = [$this->matchFieldId];
        } elseif (!is_array($this->matchFieldId) || !ArrayHelper::isNumeric($this->matchFieldId)) {
            $this->matchFieldId = (new Query())
                ->select(['id'])
                ->from([Table::MATCHFIELDS])
                ->where(Db::parseNumericParam('id', $this->matchFieldId))
                ->column();
        }
    }

    /**
     * Applies the 'ref' param to the query being prepared.
     */
    private function _applyRefParam(): void
    {
        if (!$this->ref) {
            return;
        }

        $refs = $this->ref;
        if (!is_array($refs)) {
            $refs = is_string($refs) ? StringHelper::split($refs) : [$refs];
        }

        $condition = ['or'];
        $joinMatchFields = false;

        foreach ($refs as $ref) {
            $parts = array_filter(explode('/', $ref));

            if (!empty($parts)) {
                if (count($parts) == 1) {
                    $condition[] = Db::parseParam('elements_sites.slug', $parts[0]);
                } else {
                    $condition[] = [
                        'and',
                        Db::parseParam('cockpit_matchfields.handle', $parts[0]),
                        Db::parseParam('elements_sites.slug', $parts[1]),
                    ];
                    $joinMatchFields = true;
                }
            }
        }

        $this->subQuery->andWhere($condition);

        if ($joinMatchFields) {
            $this->subQuery->innerJoin(['cockpit_matchfields' => Table::MATCHFIELDS], '[[cockpit_matchfields.id]] = [[cockpit_matchfields_entries.matchFieldId]]');
        }
    }
    /**
     * @inheritdoc
     */
    protected function cacheTags(): array
    {
        $tags = [];
        if ($this->matchFieldId) {
            foreach ($this->matchFieldId as $matchFieldId) {
                $tags[] = "group:$matchFieldId";
            }
        }
        return $tags;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    protected function fieldLayouts(): array
    {
        if ($this->matchFieldId) {
            $fieldLayouts = [];
            $matchFieldService = Cockpit::$plugin->getMatchFields();
            foreach ($this->matchFieldId as $matchFieldId) {
                $matchField = $matchFieldService->getMatchFieldEntryById($matchFieldId);
                if ($matchField) {
                    $fieldLayouts[] = $matchField->getFieldLayout();
                }
            }
            return $fieldLayouts;
        }

        return parent::fieldLayouts();
    }
}
