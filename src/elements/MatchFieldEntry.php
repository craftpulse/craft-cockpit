<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\elements;

use Craft;
use craft\base\Element;
use craft\behaviors\DraftBehavior;
use craft\controllers\ElementIndexesController;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\actions\Delete;
use craft\elements\actions\Duplicate;
use craft\elements\actions\NewChild;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\enums\Color;
use craft\enums\PropagationMethod;
use craft\errors\OperationAbortedException;
use craft\errors\SiteNotFoundException;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\Site;
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
 *
 * Class MatchFieldEntry
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class MatchFieldEntry extends Element
{

    // Public Properties
    // =========================================================================

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
        // @TODO create the entry conditions
        return Craft::createObject(MatchFieldEntryCondition::class, [static::class]);
    }

    public static function gqlTypeName(MatchFieldModel $matchFieldType): string
    {
        return sprintf('%s_MatchFieldType', $matchFieldType->handle);
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     * @throws Throwable
     */
    protected static function defineSources(string $context): array
    {
        if ($context === ElementSources::CONTEXT_INDEX) {
            $matchFields = Cockpit::$plugin->getMatchFields()->getEditableMatchFields();
        } else {
            $matchFields = Cockpit::$plugin->getMatchFields()->getAllMatchFields();
        }

        foreach ($matchFields as $matchField) {
            $sources[] = [
                'key' => 'matchFieldType:' . $matchField->uid,
                'label' => Craft::t('cockpit', $matchField->name),
                'data' => ['handle' => $matchField->handle],
                'criteria' => ['matchFieldId' => $matchField->id],
                'structureId' => $matchField->structureId,
                'structureEditable' => Craft::$app->getRequest()->getIsConsoleRequest() || Craft::$app->getUser()->checkPermission("view:match-field-entries:$matchField->uid"),
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
        if ($source !== null && preg_match('/^matchFieldType:(.+)$/', $source, $matches)) {
            $matchFieldTypes = array_filter([
                Cockpit::$plugin->getMatchFields()->getMatchFieldByUid($matches[1]),
            ]);
        } else {
            $matchFieldTypes = Cockpit::$plugin->getMatchFields()->getAllMatchFields();
        }

        return array_map(fn(MatchFieldModel $matchField) => $matchField->getFieldLayout(), $matchFieldTypes);
    }

    /**
     * @throws SiteNotFoundException
     * @throws InvalidConfigException
     */
    protected static function defineActions(string $source): array
    {
        // @TODO: add action to re-sync all match field elements?

        // Get the selected site
        $controller = Craft::$app->controller;
        if ($controller instanceof ElementIndexesController) {
            /** @var ElementQuery $elementQuery */
            $elementQuery = $controller->getElementQuery();
        } else {
            $elementQuery = null;
        }
        $site = $elementQuery && $elementQuery->siteId
            ? Craft::$app->getSites()->getSiteById($elementQuery->siteId)
            : Craft::$app->getSites()->getCurrentSite();

        // Get the group we need to check permissions on
        if (preg_match('/^matchFieldType:(\d+)$/', $source, $matches)) {
            $matchFieldType = Cockpit::$plugin->getMatchFields()->getMatchFieldById((int)$matches[1]);
        } elseif (preg_match('/^matchFieldType:(.+)$/', $source, $matches)) {
            $matchFieldType = Cockpit::$plugin->getMatchFields()->getMatchFieldByUid($matches[1]);
        } else {
            $matchFieldType = null;
        }

        // Now figure out what we can do with it
        $actions = [];
        $elementsService = Craft::$app->getElements();

        if ($matchFieldType) {
            // New Child
            if ($matchFieldType->maxLevels != 1) {
                $newChildUrl = 'match-field-entries/' . $matchFieldType->handle . '/new';

                if (Craft::$app->getIsMultiSite()) {
                    $newChildUrl .= '?site=' . $site->handle;
                }

                $actions[] = $elementsService->createAction([
                    'type' => NewChild::class,
                    'maxLevels' => $matchFieldType->maxLevels,
                    'newChildUrl' => $newChildUrl,
                ]);
            }

            // Duplicate
            $actions[] = Duplicate::class;

            if ($matchFieldType->maxLevels != 1) {
                $actions[] = [
                    'type' => Duplicate::class,
                    'deep' => true,
                ];
            }

            // Delete
            $actions[] = Delete::class;

            if ($matchFieldType->maxLevels != 1) {
                $actions[] = [
                    'type' => Delete::class,
                    'withDescendants' => true,
                ];
            }
        }

        // Restore
        $actions[] = Restore::class;

        return $actions;
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
        ];
    }

    protected static function defineTableAttributes(): array
    {
        return array_merge(parent::defineTableAttributes(), [
            'ancestors' => ['label' => Craft::t('app', 'Ancestors')],
            'cockpitId' => ['label' => Craft::t('cockpit', 'Cockpit ID')],
            'parent' => ['label' => Craft::t('app', 'Parent')],
        ]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        $attributes[] = 'status';
        $attributes[] = 'link';
        $attributes[] = 'cockpitId';

        return $attributes;
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
     * @var string|null Cockpit ID
     */
    public ?string $cockpitId = null;

    /**
     * @var bool Whether the category was deleted along with its group
     * @see beforeDelete()
     */
    public bool $deletedWithMatchField = false;

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'matchField';
        return $names;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['matchFieldId'], 'number', 'integerOnly' => true];
        return $rules;
    }

    /**
     * @inheritdoc
     */
    protected function cacheTags(): array
    {
        return [
            "matchFieldType:$this->matchFieldId",
        ];
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function getUriFormat(): ?string
    {
        $matchFieldTypeSiteSettings = $this->getMatchField()->getSiteSettings();

        if (!isset($matchFieldTypeSiteSettings[$this->siteId])) {
            throw new InvalidConfigException('The "' . $this->getMatchField()->name . '" match field type is not enabled for the "' . $this->getSite()->name . '" site.');
        }

        return $matchFieldTypeSiteSettings[$this->siteId]->uriFormat;
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
     * @throws InvalidConfigException
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
    public function __toString(): string
    {
        return (string)$this->title;
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }

        $matchFieldType = $this->getMatchField();

        if ($this->getIsDraft() && $this->getIsDerivative()) {
            /** @var static|DraftBehavior $this */
            return (
                $this->creatorId === $user->id ||
                $user->can("cockpit:view-match-field-entries:$matchFieldType->uid")
            );
        }

        return $user->can("cockpit:view-match-field-entries:$matchFieldType->uid");
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }

        $matchFieldType = $this->getMatchField();

        if ($this->getIsDraft()) {
            /** @var static|DraftBehavior $this */
            return (
                $this->creatorId === $user->id ||
                $user->can("cockpit:save-match-field-entries:$matchFieldType->uid")
            );
        }

        return $user->can("cockpit:save-match-field-entries:$matchFieldType->uid");
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
        return $user->can('cockpit:save-match-field-entries');
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function canDelete(User $user): bool
    {
        $matchFieldType = $this->getMatchField();

        if (parent::canDelete($user)) {
            return true;
        }

        if ($this->getIsDraft() && $this->getIsDerivative()) {
            /** @var static|DraftBehavior $this */
            return (
                $this->creatorId === $user->id ||
                $user->can("cockpit:delete-match-field-entries:$matchFieldType->uid")
            );
        }

        return $user->can("cockpit:delete-match-field-entries:$matchFieldType->uid");
    }

    /**
     * @inheritdoc
     */
    public function canDeleteForSite(User $user): bool
    {
        return Craft::$app->getElements()->canDelete($this, $user);
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
     * @throws Throwable
     */
    protected function crumbs(): array
    {
        $matchFieldType = $this->getMatchField();

        $crumbs = [
            [
                'label' => Craft::t('app', 'Categories'),
                'url' => UrlHelper::url('categories'),
            ],
            [
                'label' => Craft::t('site', $matchFieldType->name),
                'url' => UrlHelper::url('cockpit/match-field-entries/' . $matchFieldType->handle),
            ],
        ];

        $elementsService = Craft::$app->getElements();
        $user = Craft::$app->getUser()->getIdentity();

        $ancestors = $this->getAncestors();
        if ($ancestors instanceof ElementQueryInterface) {
            $ancestors->status(null);
        }

        foreach ($ancestors->all() as $ancestor) {
            if ($elementsService->canView($ancestor, $user)) {
                $crumbs[] = [
                    'html' => Cp::elementChipHtml($ancestor, ['class' => 'chromeless']),
                ];
            }
        }

        return $crumbs;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function createAnother(): ?self
    {
        $matchFieldType = $this->getMatchField();

        /** @var self $matchFieldEntry */
        $matchFieldEntry = Craft::createObject([
            'class' => self::class,
            'groupId' => $this->matchFieldId,
            'siteId' => $this->siteId,
        ]);

        $matchFieldEntry->enabled = $this->enabled;
        $matchFieldEntry->setEnabledForSite($this->getEnabledForSite());

        // Structure parent
        if ($matchFieldType->maxLevels !== 1) {
            $matchFieldEntry->setParentId($this->getParentId());
        }

        return $matchFieldEntry;
    }

    /**
     * @inheritdoc
     */
    protected function uiLabel(): ?string
    {
        if (!isset($this->title) || trim($this->title) === '') {
            return Craft::t('app', 'Untitled {type}', [
                'type' => self::lowerDisplayName(),
            ]);
        }

        return null;
    }

    /**
     * Returns the match field's type.
     *
     * @throws InvalidConfigException
     */
    public function getMatchField(): MatchFieldModel
    {
        if (!isset($this->matchFieldId)) {
            throw new InvalidConfigException('Match field is missing its match field type ID');
        }

        $matchFieldType = Cockpit::$plugin->getMatchFields()->getMatchFieldById($this->matchFieldId);

        if (!$matchFieldType) {
            throw new InvalidConfigException('Invalid match field type ID: ' . $this->matchFieldId);
        }

        return $matchFieldType;
    }

    public function getName(): ?string
    {
        return $this->title;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function hasRevisions(): bool
    {
        return $this->getMatchField()->enableVersioning;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    protected function cpEditUrl(): ?string
    {
        $matchFieldType = $this->getMatchField();

        $path = sprintf('cockpit/match-field-entries/%s/%s', $matchFieldType->handle, $this->getCanonicalId());

        // Ignore homepage/temp slugs
        if ($this->slug && !str_starts_with($this->slug, '__')) {
            $path .= sprintf('-%s', str_replace('/', '-', $this->slug));
        }

        return UrlHelper::cpUrl($path);
    }

    /**
     * @inheritdoc
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('cockpit/match-field-entries');
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    protected function cpRevisionsUrl(): ?string
    {
        return sprintf('%s/revisions', $this->cpEditUrl());
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function getSupportedSites(): array
    {
        if (!isset($this->matchFieldId)) {
            throw new InvalidConfigException('Require `typeId` must be set on the product.');
        }

        $matchFieldType = $this->getMatchField();
        /** @var Site[] $allSites */
        $allSites = ArrayHelper::index(Craft::$app->getSites()->getAllSites(true), 'id');
        $sites = [];

        // If the match field type is leaving it up to match field entries to decide which sites to be propagated to,
        // figure out which sites the match field entry is currently saved in
        if (
            ($this->duplicateOf->id ?? $this->id) &&
            $matchFieldType->propagationMethod === PropagationMethod::Custom
        ) {
            if ($this->id) {
                $currentSites = self::find()
                    ->status(null)
                    ->id($this->id)
                    ->site('*')
                    ->select('elements_sites.siteId')
                    ->drafts(null)
                    ->provisionalDrafts(null)
                    ->revisions($this->getIsRevision())
                    ->column();
            } else {
                $currentSites = [];
            }

            // If this is being duplicated from another element (e.g. a draft), include any sites the source element is saved to as well
            if (!empty($this->duplicateOf->id)) {
                array_push($currentSites, ...self::find()
                    ->status(null)
                    ->id($this->duplicateOf->id)
                    ->site('*')
                    ->select('elements_sites.siteId')
                    ->drafts(null)
                    ->provisionalDrafts(null)
                    ->revisions($this->duplicateOf->getIsRevision())
                    ->column()
                );
            }

            $currentSites = array_flip($currentSites);
        }

        foreach ($matchFieldType->getSiteSettings() as $siteSettings) {
            switch ($matchFieldType->propagationMethod) {
                case PropagationMethod::None:
                    $include = $siteSettings->siteId == $this->siteId;
                    $propagate = true;
                    break;
                case PropagationMethod::SiteGroup:
                    $include = $allSites[$siteSettings->siteId]->groupId == $allSites[$this->siteId]->groupId;
                    $propagate = true;
                    break;
                case PropagationMethod::Language:
                    $include = $allSites[$siteSettings->siteId]->language == $allSites[$this->siteId]->language;
                    $propagate = true;
                    break;
                case PropagationMethod::Custom:
                    $include = true;
                    // Only actually propagate to this site if it's the current site, or the product has been assigned
                    // a status for this site, or the product already exists for this site
                    $propagate = (
                        $siteSettings->siteId == $this->siteId ||
                        $this->getEnabledForSite($siteSettings->siteId) !== null ||
                        isset($currentSites[$siteSettings->siteId])
                    );
                    break;
                default:
                    $include = $propagate = true;
                    break;
            }

            if ($include) {
                $sites[] = [
                    'siteId' => $siteSettings->siteId,
                    'propagate' => $propagate,
                    'enabledByDefault' => $siteSettings->enabledByDefault,
                ];
            }
        }

        return $sites;
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
                'url' => UrlHelper::cpUrl('cockpit/match-field-entries'),
            ],
        ]);
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $fields = [
            $this->slugFieldHtml($static),
        ];

        $matchFieldType = $this->getMatchField();

        if ($matchFieldType->maxLevels !== 1) {
            $fields[] = (function() use ($static, $matchFieldType) {
                if ($parentId = $this->getParentId()) {
                    $parent = Cockpit::$plugin->getMatchFieldEntries()->getMatchFieldEntryById($parentId, $this->siteId, [
                        'drafts' => null,
                        'draftOf' => false,
                    ]);
                } else {
                    // If the entry already has structure data, use it. Otherwise, use its canonical entry
                    /** @var self|null $parent */
                    $parent = self::find()
                        ->siteId($this->siteId)
                        ->ancestorOf($this->lft ? $this : ($this->getIsCanonical() ? $this->id : $this->getCanonical(true)))
                        ->ancestorDist(1)
                        ->drafts(null)
                        ->draftOf(false)
                        ->status(null)
                        ->one();
                }

                return Cp::elementSelectFieldHtml([
                    'label' => Craft::t('app', 'Parent'),
                    'id' => 'parentId',
                    'name' => 'parentId',
                    'elementType' => self::class,
                    'selectionLabel' => Craft::t('app', 'Choose'),
                    'sources' => ["matchFieldType:$matchFieldType->uid"],
                    'criteria' => $this->_parentOptionCriteria($matchFieldType),
                    'limit' => 1,
                    'elements' => $parent ? [$parent] : [],
                    'disabled' => $static,
                    'describedBy' => 'parentId-label',
                ]);
            })();
        }

        $fields[] = parent::metaFieldsHtml($static);

        return implode("\n", $fields);
    }

    private function _parentOptionCriteria(MatchFieldModel $matchFieldType): array
    {
        $parentOptionCriteria = [
            'siteId' => $this->siteId,
            'typeId' => $matchFieldType->id,
            'status' => null,
            'drafts' => null,
            'draftOf' => false,
        ];

        // Prevent the current entry, or any of its descendants, from being selected as a parent
        if ($this->id) {
            $excludeIds = self::find()
                ->descendantOf($this)
                ->drafts(null)
                ->draftOf(false)
                ->status(null)
                ->ids();
            $excludeIds[] = $this->getCanonicalId();
            $parentOptionCriteria['id'] = array_merge(['not'], $excludeIds);
        }

        if ($matchFieldType->maxLevels) {
            if ($this->id) {
                // Figure out how deep the ancestors go
                $maxDepth = self::find()
                    ->select('level')
                    ->descendantOf($this)
                    ->status(null)
                    ->leaves()
                    ->scalar();
                $depth = 1 + ($maxDepth ?: $this->level) - $this->level;
            } else {
                $depth = 1;
            }

            $parentOptionCriteria['level'] = sprintf('<=%s', $matchFieldType->maxLevels - $depth);
        }

        return $parentOptionCriteria;
    }

    /**
     * @inheritdoc
     */
    protected function inlineAttributeInputHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'slug':
                return Cp::textHtml([
                    'name' => 'slug',
                    'value' => $this->slug,
                ]);
            default:
                return parent::inlineAttributeInputHtml($attribute);
        }
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
     * @throws \yii\base\Exception
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
            $record->cockpitId = $this->cockpitId;
            $record->dateUpdated = $this->dateUpdated;
            $record->dateCreated = $this->dateCreated;

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
     * @inheritdoc
     * @throws \yii\db\Exception
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Update the match field record
        $data = [
            'deletedWithMatchField' => $this->deletedWithMatchField,
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
