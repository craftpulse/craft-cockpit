<?php

namespace craftpulse\cockpit\elements;

use Craft;
use craft\base\Colorable;
use craft\base\Element;
use craft\base\ExpirableElementInterface;
use craft\base\Iconic;
use craft\base\NestedElementInterface;
use craft\base\NestedElementTrait;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\enums\Color;
use craft\errors\OperationAbortedException;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\services\ElementSources;
use craft\services\Structures;
use craft\web\CpScreenResponseBehavior;

use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\elements\conditions\MatchFieldEntryCondition;
use craftpulse\cockpit\elements\db\MatchFieldEntryQuery;
use craftpulse\cockpit\models\MatchField as MatchFieldModel;
use craftpulse\cockpit\records\MatchFieldEntry as MatchFieldEntryRecord;

use DateTime;
use Exception;
use Throwable;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Match Field Entry element type
 */
class MatchFieldEntry extends Element implements NestedElementInterface, ExpirableElementInterface, Iconic, Colorable
{
    use NestedElementTrait {
        eagerLoadingMap as traitEagerLoadingMap;
        attributes as traitAttributes;
        extraFields as traitExtraFields;
        setEagerLoadedElements as traitSetEagerLoadedElements;
    }

    // Public Properties
    // =========================================================================

    public const STATUS_ENABLED = 'enabled';

    public static function displayName(): string
    {
        return Craft::t('cockpit', 'Match field entry');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('cockpit', 'match field entry');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('cockpit', 'Match field entries');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('cockpit', 'match field entries');
    }

    public static function refHandle(): ?string
    {
        return 'matchfieldentry';
    }

    /**
     * @inheritdoc
     */
    public static function hasDrafts(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_ENABLED => Craft::t('cockpit', 'Enabled'),
            self::STATUS_DISABLED => Craft::t('cockpit', 'Disabled'),
        ];
    }

    /**
     * @inheritdoc
     * @return MatchFieldEntryQuery The newly created [[EntryQuery]] instance.
     */
    public static function find(): MatchFieldEntryQuery
    {
        return new MatchFieldEntryQuery(static::class);
    }

    /**
     * @inheritdoc
     * @return MatchFieldEntryCondition
     * @throws InvalidConfigException
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(MatchFieldEntryCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     * @throws Throwable
     */
    protected static function defineSources(string $context): array
    {
        $sources = [];

        if ($context === ElementSources::CONTEXT_INDEX) {
            $matchFields = Cockpit::$plugin->getMatchFields()->getEditableMatchFields();
            $editable = true;
        } else {
            $matchFields = Cockpit::$plugin->getMatchFields()->getAllMatchFields();
            $editable = false;
        }

        $matchFieldIds = [];

        foreach ($matchFields as $matchField) {
            $matchFieldIds[] = $matchField->id;
        }

        foreach ($matchFields as $matchField) {
            $sources[] = [
                'key' => 'matchfield:' . $matchField->uid,
                'label' => Craft::t('site', $matchField->name),
                'data' => ['handle' => $matchField->handle],
                'criteria' => [
                    'matchFieldId' => $matchFieldIds,
                    'editable' => $editable,
                ],
                'structureId' => $matchField->structureId,
                'structureEditable' => Craft::$app->getRequest()->getIsConsoleRequest() || Craft::$app->getUser()->checkPermission("cockpit:view-match-fields:$matchField->uid"),
            ];
        }

        return $sources;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    protected static function defineFieldLayouts(?string $source): array
    {
        if ($source !== null && preg_match('/^matchfield:(.+)$/', $source, $matches)) {
            $matchFields = array_filter([
                Cockpit::$plugin->getMatchFields()->getMatchFieldByUid($matches[1]),
            ]);
        } else {
            $matchFields = Cockpit::$plugin->getMatchFields()->getAllMatchFields();
        }

        return array_map(fn(MatchFieldModel $matchField) => $matchField->getFieldLayout(), $matchFields);
    }

    protected static function defineActions(string $source): array
    {
        // List any bulk element actions here
        return [];
    }

    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    protected static function defineSortOptions(): array
    {
        return [
            'title' => Craft::t('app', 'Title'),
            'slug' => Craft::t('app', 'Slug'),
            'uri' => Craft::t('app', 'URI'),
            [
                'label' => Craft::t('app', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
            // ...
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return [
            'slug' => ['label' => Craft::t('app', 'Slug')],
            'uri' => ['label' => Craft::t('app', 'URI')],
            'link' => ['label' => Craft::t('app', 'Link'), 'icon' => 'world'],
            'id' => ['label' => Craft::t('app', 'ID')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
            // ...
        ];
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'status',
            'link',
            'dateCreated',
            // ...
        ];
    }

    /**
     * @var DateTime|null Post date
     */
    public ?DateTime $postDate = null;

    /**
     * @var DateTime|null Expiry date
     */
    public ?DateTime $expiryDate = null;

    /**
     * @var int|null Match field ID
     */
    public ?int $matchFieldId = null;

    /**
     * @var bool Whether the category was deleted along with its group
     * @see beforeDelete()
     */
    public bool $deletedWithGroup = false;

    public function getUriFormat(): ?string
    {
        // If match field entries should have URLs, define their URI format here
        return null;
    }

    protected function previewTargets(): array
    {
        $previewTargets = [];
        $url = $this->getUrl();
        if ($url) {
            $previewTargets[] = [
                'label' => Craft::t('app', 'Primary {type} page', [
                    'type' => self::lowerDisplayName(),
                ]),
                'url' => $url,
            ];
        }
        return $previewTargets;
    }

    /**
     * @inheritdoc
     */
    protected function route(): array|string|null
    {
        // Make sure the match field is set to have URLs for this site
        $matchFieldSiteSettings = $this->getMatchField()->getSiteSettings()[$this->siteId] ?? null;

        if (!$matchFieldSiteSettings?->hasUrls) {
            return null;
        }

        return [
            'templates/render', [
                'template' => (string)$matchFieldSiteSettings->template,
                'variables' => [
                    'matchField' => $this,
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:view-match-field-entries');
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-match-field-entries');
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:duplicate-match-field-entries');
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:delete-match-field-entries');
    }

    /**
     * @inheritdoc
     */
    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        return sprintf('match-field-entries/%s', $this->getCanonicalId());
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('match-field-entries');
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?FieldLayout
    {
        try {
            return $this->getMatchField()->getFieldLayout();
        } catch (InvalidConfigException) {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('match-field-entries'),
            ],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getColor(): ?Color
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getIcon(): ?string
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getExpiryDate(): ?DateTime
    {
        return $this->expiryDate;
    }

    /**
     * Returns the match field entry type
     *
     * @return MatchFieldModel
     * @throws InvalidConfigException if [[groupId]] is missing or invalid
     */
    public function getMatchField(): MatchFieldModel
    {
        if (!isset($this->matchFieldId)) {
            throw new InvalidConfigException('Match field is missing its match field ID');
        }

        $matchField = Cockpit::$plugin->getMatchFields()->getMatchFieldById($this->matchFieldId);

        if (!$matchField) {
            throw new InvalidConfigException('Invalid match field ID: ' . $this->matchFieldId);
        }

        return $matchField;
    }

    // Events
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     * @throws Exception if reasons
     * @throws InvalidConfigException
     */
    public function beforeSave(bool $isNew): bool
    {
        // Set the structure ID for Element::attributes() and afterSave()
        $this->structureId = $this->getMatchField()->structureId;

        // Has the match field been assigned a new parent?
        if (!$this->duplicateOf && $this->hasNewParent()) {
            if ($parentId = $this->getParentId()) {
                // getCategories - should this fetch my match fields or match field entries?
                $parentMatchField = Craft::$app->getCategories()->getCategoryById($parentId, $this->siteId, [
                    'drafts' => null,
                    'draftOf' => false,
                ]);

                if (!$parentMatchField) {
                    throw new InvalidConfigException("Invalid match field ID: $parentId");
                }
            } else {
                $parentMatchField = null;
            }

            $this->setParent($parentMatchField);
        }

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     * @throws \yii\db\Exception
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            $matchField = $this->getMatchField();

            // Get the match field entry record
            if (!$isNew) {
                $record = MatchFieldEntryRecord::findOne($this->id);

                if (!$record) {
                    throw new InvalidConfigException("Invalid match field ID: $this->id");
                }
            } else {
                $record = new MatchFieldEntryRecord();
                $record->id = (int)$this->id;
            }

            $record->matchFieldId = (int)$this->matchFieldId;
            $record->save(false);

            if (!$this->duplicateOf) {
                // Has the parent changed?
                if ($this->hasNewParent()) {
                    $this->_placeInStructure($isNew, $matchField);
                }

                // Update the category's descendants, who may be using this category's URI in their own URIs
                if (!$isNew && $this->getIsCanonical()) {
                    Craft::$app->getElements()->updateDescendantSlugsAndUris($this, true, true);
                }
            }
        }

        parent::afterSave($isNew);
    }

    /**
     * @throws \yii\base\Exception
     */
    private function _placeInStructure(bool $isNew, MatchFieldModel $group): void
    {
        $parentId = $this->getParentId();
        $structuresService = Craft::$app->getStructures();

        // If this is a provisional draft and its new parent matches the canonical entry’s, just drop it from the structure
        if ($this->isProvisionalDraft) {
            $canonicalParentId = self::find()
                ->select(['elements.id'])
                ->ancestorOf($this->getCanonicalId())
                ->ancestorDist(1)
                ->status(null)
                ->scalar();

            if ($parentId == $canonicalParentId) {
                $structuresService->remove($this->structureId, $this);
                return;
            }
        }

        $mode = $isNew ? Structures::MODE_INSERT : Structures::MODE_AUTO;

        if (!$parentId) {
            if ($group->defaultPlacement === MatchFieldModel::DEFAULT_PLACEMENT_BEGINNING) {
                $structuresService->prependToRoot($this->structureId, $this, $mode);
            } else {
                $structuresService->appendToRoot($this->structureId, $this, $mode);
            }
        } else {
            if ($group->defaultPlacement === MatchFieldModel::DEFAULT_PLACEMENT_BEGINNING) {
                $structuresService->prepend($this->structureId, $this, $this->getParent(), $mode);
            } else {
                $structuresService->append($this->structureId, $this, $this->getParent(), $mode);
            }
        }
    }

    /**
     * @inheritdoc
     * @throws \yii\db\Exception
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Update the category record
        $data = [
            'deletedWithGroup' => $this->deletedWithGroup,
            'parentId' => null,
        ];

        if ($this->structureId) {
            // Remember the parent ID, in case the category needs to be restored later
            $parentId = $this->ancestors()
                ->ancestorDist(1)
                ->status(null)
                ->select(['elements.id'])
                ->scalar();
            if ($parentId) {
                $data['parentId'] = $parentId;
            }
        }

        Db::update(Table::MATCHFIELDS_ENTRIES, $data, [
            'id' => $this->id,
        ], [], false);

        return true;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException|\yii\base\Exception
     */
    public function afterRestore(): void
    {
        $structureId = $this->getMatchField()->structureId;

        // Add the match field back into its structure
        /** @var self|null $parent */
        $parent = self::find()
            ->structureId($structureId)
            ->innerJoin(['j' => Table::MATCHFIELDS_ENTRIES], '[[j.parentId]] = [[elements.id]]')
            ->andWhere(['j.id' => $this->id])
            ->one();

        if (!$parent) {
            Craft::$app->getStructures()->appendToRoot($structureId, $this);
        } else {
            Craft::$app->getStructures()->append($structureId, $this, $parent);
        }

        parent::afterRestore();
    }

    /**
     * @inheritdoc
     * @param int $structureId
     * @throws InvalidConfigException
     * @throws OperationAbortedException
     * @throws \yii\db\Exception
     */
    public function afterMoveInStructure(int $structureId): void
    {
        // Was the category moved within its group's structure?
        if ($this->getMatchField()->structureId == $structureId) {
            // Update its URI
            Craft::$app->getElements()->updateElementSlugAndUri($this, true, true, true);

            // Make sure that each of the category's ancestors are related wherever the category is related
            $newRelationValues = [];

            $ancestorIds = $this->ancestors()
                ->status(null)
                ->ids();

            $sources = (new Query())
                ->select(['fieldId', 'sourceId', 'sourceSiteId'])
                ->from([CraftTable::RELATIONS])
                ->where(['targetId' => $this->id])
                ->all();

            foreach ($sources as $source) {
                $existingAncestorRelations = (new Query())
                    ->select(['targetId'])
                    ->from([CraftTable::RELATIONS])
                    ->where([
                        'fieldId' => $source['fieldId'],
                        'sourceId' => $source['sourceId'],
                        'sourceSiteId' => $source['sourceSiteId'],
                        'targetId' => $ancestorIds,
                    ])
                    ->column();

                $missingAncestorRelations = array_diff($ancestorIds, $existingAncestorRelations);

                foreach ($missingAncestorRelations as $categoryId) {
                    $newRelationValues[] = [
                        $source['fieldId'],
                        $source['sourceId'],
                        $source['sourceSiteId'],
                        $categoryId,
                    ];
                }
            }

            if (!empty($newRelationValues)) {
                Db::batchInsert(CraftTable::RELATIONS, ['fieldId', 'sourceId', 'sourceSiteId', 'targetId'], $newRelationValues);
            }
        }

        parent::afterMoveInStructure($structureId);
    }
}
