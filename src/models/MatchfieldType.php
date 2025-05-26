<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\MatchfieldType;

use Craft;
use craft\base\Field;
use craft\base\FieldLayoutProviderInterface;
use craft\behaviors\FieldLayoutBehavior;
use craft\commerce\records\MatchfieldType as MatchfieldTypeRecord;
use craft\enums\PropagationMethod;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;
use yii\base\InvalidConfigException;

/**
 * Matchfield type model.
 * @method null setFieldLayout(FieldLayout $fieldLayout)
 *
 * @property string $cpEditUrl
 * @property string $cpEditVariantUrl
 * @property FieldLayout $fieldLayout
 */
class ProductType extends Model implements FieldLayoutProviderInterface
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var bool Whether versioning should be enabled for this matchfield type.
     */
    public bool $enableVersioning = false;

    /**
     * @var string Title format
     */
    public string $titleFormat = '{matchfieldType.title}';

    /**
     * @var string Variant title translation method
     * @phpstan-var Field::TRANSLATION_METHOD_NONE|Field::TRANSLATION_METHOD_SITE|Field::TRANSLATION_METHOD_SITE_GROUP|Field::TRANSLATION_METHOD_LANGUAGE|Field::TRANSLATION_METHOD_CUSTOM
     */
    public string $titleTranslationMethod = Field::TRANSLATION_METHOD_SITE;

    /**
     * @var string|null Variant title translation key format
     */
    public ?string $titleTranslationKeyFormat = null;

    /**
     * @var string|null Template
     */
    public ?string $template = null;

    /**
     * @var int|null Field layout ID
     */
    public ?int $fieldLayoutId = null;

    /**
     * @var string Cockpit ID
     */
    public ?string $cockpitId = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    /**
     * @var PropagationMethod Propagation method
     *
     * This will be set to one of the following:
     *
     *  - [[PropagationMethod::None]] – Only save matchfields in the site they were created in
     *  - [[PropagationMethod::SiteGroup]] – Save  matchfields to other sites in the same site group
     *  - [[PropagationMethod::Language]] – Save matchfields to other sites with the same language
     *  - [[PropagationMethod::Custom]] – Save matchfields to other sites based on a custom [[$propagationKeyFormat|propagation key format]]
     *  - [[PropagationMethod::All]] – Save matchfields to all sites supported by the owner element
     *
     * @since 5.1.0
     */
    public PropagationMethod $propagationMethod = PropagationMethod::All;

    /**
     * @return null|string
     */
    public function __toString()
    {
        return (string)$this->handle;
    }

    /**
     * @inerhitdoc
     */
    public function getHandle(): ?string
    {
        return $this->handle;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['id', 'fieldLayoutId'], 'number', 'integerOnly' => true],
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['handle'], UniqueValidator::class, 'targetClass' => MatchfieldTypeRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'],
            [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
            ['fieldLayout', 'validateFieldLayout'],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('cockpit/settings/matchfieldtypes/' . $this->id);
    }

    /**
     * Validate the field layout to make sure no fields with reserved words are used.
     */
    public function validateFieldLayout(): void
    {
        $fieldLayout = $this->getFieldLayout();

        $fieldLayout->reservedFieldHandles = [
            'cockpitId',
        ];

        if (!$fieldLayout->validate()) {
            $this->addModelErrors($fieldLayout, 'fieldLayout');
        }
    }
}
