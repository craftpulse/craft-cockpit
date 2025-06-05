<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\models;

use Craft;
use craft\base\Chippable;
use craft\base\CpEditable;
use craft\base\FieldLayoutProviderInterface;
use craft\base\Iconic;
use craft\base\Model;
use craft\behaviors\FieldLayoutBehavior;
use craft\db\Query;
use craft\db\Table as CraftTable;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use craft\models\FieldLayout;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\elements\MatchFieldEntry;
use craftpulse\cockpit\records\MatchField as MatchFieldRecord;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use DateTime;
use Exception;
use yii\base\ExitException;
use yii\base\InvalidConfigException;

/**
 * MatchField model class.
 *
 * @property MatchField_SiteSettings[] $siteSettings Site-specific settings
 * @property bool $hasMultiSiteEntries Whether MatchFieldEntries in this match field support multiple sites
 * @mixin FieldLayoutBehavior
 */
class MatchField extends Model implements
    Chippable,
    CpEditable,
    FieldLayoutProviderInterface,
    Iconic
{
    public const PROPAGATION_METHOD_NONE = 'none';
    public const PROPAGATION_METHOD_SITE_GROUP = 'siteGroup';
    public const PROPAGATION_METHOD_LANGUAGE = 'language';
    public const PROPAGATION_METHOD_ALL = 'all';
    public const PROPAGATION_METHOD_CUSTOM = 'custom';
    public const DEFAULT_PLACEMENT_BEGINNING = 'beginning';
    public const DEFAULT_PLACEMENT_END = 'end';

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public static function get(int|string $id): ?static
    {
        /** @phpstan-ignore-next-line */
        return Cockpit::$plugin->getMatchFields()->getMatchFieldById($id);
    }

    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int|null Structure ID
     */
    public ?int $structureId = null;

    /**
     * @var int|null Field layout ID
     */
    public ?int $fieldLayoutId = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var string|null Type
     */
    public ?string $type = null;

    /**
     * @var int|null Max levels
     */
    public ?int $maxLevels = null;

    /**
     * @var bool Enable versioning
     */
    public bool $enableVersioning = true;

    /**
     * @var bool trigger API data fetch
     */
    public bool $syncMatchFields = true;

    /**
     * @var PropagationMethod Propagation method
     *
     * This will be set to one of the following:
     *
     *  - [[PropagationMethod::None]] – Only save entries in the site they were created in
     *  - [[PropagationMethod::SiteGroup]] – Save  entries to other sites in the same site group
     *  - [[PropagationMethod::Language]] – Save entries to other sites with the same language
     *  - [[PropagationMethod::Custom]] – Let each entry choose which sites it should be saved to
     *  - [[PropagationMethod::All]] – Save entries to all sites supported by the owner element
     */
    public PropagationMethod $propagationMethod = PropagationMethod::All;

    /**
     * @var string Default placement
     * @phpstan-var self::DEFAULT_PLACEMENT_*
     */
    public string $defaultPlacement = self::DEFAULT_PLACEMENT_END;

    /**
     * @var array|null Preview targets
     */
    public ?array $previewTargets = null;

    /**
     * @var string|null Match fields' UID
     */
    public ?string $uid = null;

    /**
     * @var DateTime|null The date that the match field was trashed
     */
    public ?DateTime $dateDeleted = null;

    /**
     * @var MatchField_SiteSettings[]|null
     */
    private ?array $_siteSettings = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        if (!isset($this->previewTargets)) {
            $this->previewTargets = [
                [
                    'label' => Craft::t('cockpit', 'Primary {type} page', [
                        'type' => Entry::lowerDisplayName(),
                    ]),
                    'urlFormat' => '{url}',
                ],
            ];
        }

        parent::init();
    }

    /**
     * @inheritdoc
     */
    protected function defineBehaviors(): array
    {
        return [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => MatchFieldEntry::class,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getUiLabel(): string
    {
        return Craft::t('cockpit', $this->name);
    }

    /**
     * @inheritdoc
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return [
            'handle' => Craft::t('app', 'Handle'),
            'name' => Craft::t('app', 'Name'),
            'type' => Craft::t('app', 'MatchField Type'),
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['id', 'structureId', 'fieldLayoutId', 'maxLevels'], 'number', 'integerOnly' => true];
        $rules[] = [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']];
        $rules[] = [['handle'], UniqueValidator::class, 'targetClass' => MatchFieldRecord::class];
        $rules[] = [['name', 'handle', 'type', 'propagationMethod', 'siteSettings'], 'required'];
        $rules[] = [['name', 'handle'], 'string', 'max' => 255];
        $rules[] = [['defaultPlacement'], 'in', 'range' => [self::DEFAULT_PLACEMENT_BEGINNING, self::DEFAULT_PLACEMENT_END]];
        $rules[] = [['fieldLayout'], 'validateFieldLayout'];
        $rules[] = [['previewTargets'], 'validatePreviewTargets'];
        $rules[] = [['siteSettings'], 'validateSiteSettings'];
        return $rules;
    }

    /**
     * Validates the field layout.
     */
    public function validateFieldLayout(): void
    {
        $fieldLayout = $this->getFieldLayout();
        $fieldLayout->reservedFieldHandles = [
            'matchField',
            'cockpitId',
            'matchFieldId',
        ];

        if (!$fieldLayout->validate()) {
            $this->addModelErrors($fieldLayout, 'fieldLayout');
        }
    }

    /**
     * Validates the site settings.
     * @throws InvalidConfigException
     * @throws ExitException
     */
    public function validateSiteSettings(): void
    {
        // If this is an existing match field, make sure they aren't moving it to a
        // completely different set of sites in one fell swoop
        if ($this->id) {
            $currentSiteIds = (new Query())
                ->select(['siteId'])
                ->from([Table::MATCHFIELDS_SITES])
                ->where(['matchFieldId' => $this->id])
                ->column();

            if (empty(array_intersect($currentSiteIds, array_keys($this->getSiteSettings())))) {
                $this->addError('siteSettings', Craft::t('app', 'At least one currently-enabled site must remain enabled.'));
            }
        }

        foreach ($this->getSiteSettings() as $i => $siteSettings) {
            if (!$siteSettings->validate()) {
                $this->addModelErrors($siteSettings, "siteSettings[$i]");
            }
        }
    }

    /**
     * Validates the preview targets.
     */
    public function validatePreviewTargets(): void
    {
        $hasErrors = false;

        foreach ($this->previewTargets as &$target) {
            $target['label'] = trim($target['label']);
            $target['urlFormat'] = trim($target['urlFormat']);

            if ($target['label'] === '') {
                $target['label'] = ['value' => $target['label'], 'hasErrors' => true];
                $hasErrors = true;
            }
        }
        unset($target);

        if ($hasErrors) {
            $this->addError('previewTargets', Craft::t('app', 'All targets must have a label.'));
        }
    }

    /**
     * Use the translated match field name as the string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return Craft::t('cockpit', $this->name) ?: static::class;
    }

    /**
     * @inheritdoc
     */
    public function getHandle(): ?string
    {
        return $this->handle;
    }

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function getFieldLayout(): FieldLayout
    {
        /** @var FieldLayoutBehavior $behavior */
        $behavior = $this->getBehavior('fieldLayout');
        return $behavior->getFieldLayout();
    }

    /**
     * Returns the match fields' site-specific settings, indexed by site ID.
     *
     * @return MatchField_SiteSettings[]
     * @throws InvalidConfigException
     */
    public function getSiteSettings(): array
    {
        if (isset($this->_siteSettings)) {
            return $this->_siteSettings;
        }

        if (!$this->id) {
            return [];
        }

        // Set them with setSiteSettings() so they get indexed by site ID and setMatchField() gets called on them
        $this->setSiteSettings(Cockpit::$plugin->getMatchFields()->getMatchFieldSiteSettings($this->id));

        return $this->_siteSettings;
    }

    /**
     * Sets the match fields' site-specific settings.
     *
     * @param MatchField_SiteSettings[] $siteSettings Array of MatchField_SiteSettings objects.
     */
    public function setSiteSettings(array $siteSettings): void
    {
        $this->_siteSettings = ArrayHelper::index(
            $siteSettings,
            fn(MatchField_SiteSettings $siteSettings) => $siteSettings->siteId,
        );

        foreach ($this->_siteSettings as $settings) {
            $settings->setMatchField($this);
        }
    }

    /**
     * Returns the site IDs that are enabled for the match field.
     *
     * @return int[]
     * @throws InvalidConfigException
     * @throws ExitException
     */
    public function getSiteIds(): array
    {
        return array_keys($this->getSiteSettings());
    }

    /**
     * Adds site-specific errors to the model.
     *
     * @param array $errors
     * @param int $siteId
     */
    public function addSiteSettingsErrors(array $errors, int $siteId): void
    {
        foreach ($errors as $attribute => $siteErrors) {
            $key = $attribute . '-' . $siteId;
            foreach ($siteErrors as $error) {
                $this->addError($key, $error);
            }
        }
    }

    /**
     * Returns whether entries in this match field support multiple sites.
     *
     * @return bool
     * @throws InvalidConfigException
     */
    public function getHasMultiSiteEntries(): bool
    {
        return (
            Craft::$app->getIsMultiSite() &&
            count($this->getSiteSettings()) > 1 &&
            $this->propagationMethod !== PropagationMethod::None
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        if (!$this->id || !Craft::$app->getUser()->getIsAdmin()) {
            return null;
        }
        return UrlHelper::cpUrl("cockpit/settings/matchfields/$this->id");
    }

    /**
     * @inheritdoc
     */
    public function getIcon(): ?string
    {
        // @TODO: find a fitting icon
        return 'newspaper';
    }

    /**
     * Returns the match fields’ config.
     * @throws Exception
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
            'type' => $this->type,
            'enableVersioning' => $this->enableVersioning,
            'propagationMethod' => $this->propagationMethod->value,
            'siteSettings' => [],
            'defaultPlacement' => $this->defaultPlacement ?? self::DEFAULT_PLACEMENT_END,
        ];

        if (!empty($this->previewTargets)) {
            $config['previewTargets'] = ProjectConfigHelper::packAssociativeArray(array_values($this->previewTargets));
        }

        $config['structure'] = [
            'uid' => $this->structureId ? Db::uidById(CraftTable::STRUCTURES, $this->structureId) : StringHelper::UUID(),
            'maxLevels' => (int)$this->maxLevels ?: null,
        ];

        $fieldLayout = $this->getFieldLayout();

        if ($fieldLayoutConfig = $fieldLayout->getConfig()) {
            $config['fieldLayouts'] = [
                $fieldLayout->uid => $fieldLayoutConfig,
            ];
        }

        foreach ($this->getSiteSettings() as $siteId => $siteSettings) {
            $siteUid = Db::uidById(CraftTable::SITES, $siteId);
            $config['siteSettings'][$siteUid] = [
                'enabledByDefault' => (bool)$siteSettings['enabledByDefault'],
                'hasUrls' => (bool)$siteSettings['hasUrls'],
                'uriFormat' => $siteSettings['uriFormat'] ?: null,
                'template' => $siteSettings['template'] ?: null,
            ];
        }

        return $config;
    }
}
