<?php

namespace craftpulse\cockpit\elements;

use Craft;
use craft\base\Colorable;
use craft\base\Element;
use craft\base\ExpirableElementInterface;
use craft\base\Iconic;
use craft\base\NestedElementInterface;
use craft\base\NestedElementTrait;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\enums\Color;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\services\ElementSources;
use craft\web\CpScreenResponseBehavior;

use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\conditions\MatchFieldEntryCondition;
use craftpulse\cockpit\elements\db\MatchFieldEntryQuery;
use craftpulse\cockpit\models\MatchField as MatchFieldModel;

use DateTime;
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

    public ?DateTime $expiryDate = null;

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
        } else {
            $matchFields = Cockpit::$plugin->getMatchFields()->getAllMatchFields();
        }

        foreach ($matchFields as $matchField) {
            $sources[] = [
                'key' => 'matchfield:' . $matchField->uid,
                'label' => Craft::t('site', $matchField->name),
                'data' => ['handle' => $matchField->handle],
                'criteria' => ['groupId' => $matchField->id],
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

    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            // ...
        ]);
    }

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

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:view-match-field-entries');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-match-field-entries');
    }

    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:duplicate-match-field-entries');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:delete-match-field-entries');
    }

    public function canCreateDrafts(User $user): bool
    {
        return true;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('match-field-entries/%s', $this->getCanonicalId());
    }

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

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            // todo: update the `matchfieldentries` table
        }

        parent::afterSave($isNew);
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
     * Returns the match field.
     *
     * @return MatchFieldModel
     * @throws InvalidConfigException if [[groupId]] is missing or invalid
     */
    public function getMatchField(): MatchFieldModel
    {
        if (!isset($this->matchFieldId)) {
            throw new InvalidConfigException('Match field is missing its match field ID');
        }

        $matchFields = Cockpit::$plugin->getMatchFields()->getMatchFieldById($this->matchFieldId);

        if (!$matchFields) {
            throw new InvalidConfigException('Invalid match field ID: ' . $this->matchFieldId);
        }

        return $matchFields;
    }
}
