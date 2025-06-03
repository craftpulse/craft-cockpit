<?php

namespace craftpulse\cockpit\elements;

use Craft;
use craft\base\Element;
use craft\elements\Address;
use craft\elements\db\AddressQuery;
use craft\elements\ElementCollection;
use craft\elements\NestedElementManager;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\enums\PropagationMethod;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\models\Site;
use craft\web\CpScreenResponseBehavior;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\conditions\DepartmentCondition;
use craftpulse\cockpit\elements\db\DepartmentQuery;
use craftpulse\cockpit\records\DepartmentRecord;
use yii\base\InvalidConfigException;
use yii\web\Response;

/**
 * Department element type
 */
class Department extends Element
{
    /**
     * @var string
     */
    public string $cockpitId = '';

    /**
     * @var string
     */
    public ?string $email = null;

    /**
     * @var string
     */
    public ?string $phone = null;

    /**
     * @var string
     */
    public ?string $reference = null;

    /**
     * @var string|null
     */
    public ?string $type = 'department';

    /**
     * @var ElementCollection<Address> Address
     * @see getAddres()
     */
    private ElementCollection $_address;

    /**
     * @see getAddressManager()
     */
    private NestedElementManager $_addressManager;

    /**
     * @var FieldLayout|null
     */
    private ?FieldLayout $fieldLayout = null;

    public static function displayName(): string
    {
        return Craft::t('cockpit', 'Department');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('cockpit', 'department');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('cockpit', 'Departments');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('cockpit', 'departments');
    }

    public static function refHandle(): ?string
    {
        return 'department';
    }

    public static function trackChanges(): bool
    {
        return true;
    }

    public static function hasTitles(): bool
    {
        return true;
    }

    public static function hasUris(): bool
    {
        return true;
    }

    public static function isLocalized(): bool
    {
        return true;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(DepartmentQuery::class, [static::class]);
    }

    /**
     * Gets the address.
     *
     * @return ElementCollection
     */
    public function getAddress(): ElementCollection
    {
        if (!isset($this->_address)) {
            if (!$this->id) {
                /** @var ElementCollection<Address> */
                return ElementCollection::make();
            }

            $this->_address = $this->createAddressQuery()
                ->andWhere(['fieldId' => null])
                ->collect();
        }

        return $this->_address;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        return Cockpit::$plugin->getSettings()->departmentSiteSettings ?? [];
    }

    /**
     * Returns a nested element manager for the user’s address.
     *
     * @return NestedElementManager
     * @since 5.0.0
     */
    public function getAddressManager(): NestedElementManager
    {
        if (!isset($this->_addressManager)) {
            $this->_addressManager = new NestedElementManager(
                Address::class,
                fn() => $this->createAddressQuery(),
                [
                    'attribute' => 'address',
                    'propagationMethod' => PropagationMethod::None,
                ],
            );
        }

        return $this->_addressManager;
    }

    /**
     * @inheritdoc
     */
    public function afterRestore(): void
    {
        $this->getAddressManager()->restoreNestedElements($this);
        parent::afterRestore();
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();
        $names[] = 'address';
        return $names;
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(DepartmentCondition::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('cockpit', 'All departments'),
            ],
        ];
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
            'link',
            'dateCreated',
            // ...
        ];
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            [
                'phone',
                'reference',
            ],
            'safe'
        ];

        if ($this->id !== null) {
            $rules[] = [[
                'cockpitId',
            ], 'required'];

            $rules[] = [['email'], 'email'];
        }

        return $rules;
    }

    /**
     * @inheritdoc
     * @return FieldLayout|null
     */
    public function getFieldLayout(): ?FieldLayout
    {
        if ($this->fieldLayout !== null) {
            return $this->fieldLayout;
        }

        $this->fieldLayout = Craft::$app->getFields()->getLayoutByType(self::class);

        return $this->fieldLayout;
    }

    public function getUriFormat(): ?string
    {
        $settings = Cockpit::getInstance()->getSettings()->departmentSiteSettings ?? [];

        $hasUrls = $settings[$this->siteId]['hasUrl'] ?? false;
        $uriFormat = $settings[$this->siteId]['uriFormat'] ?? null;

        if (!$hasUrls) {
            return null;
        }

        if (!$uriFormat) {
            return null;
        }

        return $settings[$this->siteId]['uriFormat'];
    }

    protected function route(): array|string|null
    {
        // Make sure that the product is actually live
        if (!$this->previewing && $this->getStatus() != self::STATUS_ENABLED) {
            return null;
        }

        // Make sure the product type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $settings = Cockpit::getInstance()->getSettings()->departmentSiteSettings ?? [];

        if (!isset($settings[$this->siteId])) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $settings[$siteId]['template'] ?? null,
                'variables' => [
                    'entry' => $this,
                    'department' => $this,
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
        return $user->can('cockpit:departments');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-departments');
    }

    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-departments');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:delete-departments');
    }

    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('cockpit/departments/%s', $this->getCanonicalId());
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('cockpit/departments');
    }

    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('cockpit/departments'),
            ],
        ]);
    }
    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        $this->getAddressManager()->deleteNestedElements($this, $this->hardDelete);

        return true;
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if ($isNew) {
                $record = new DepartmentRecord();
                $record->id = $this->id;
            } else {
                $record = DepartmentRecord::findOne($this->id);
            }

            if (!$record->validate()) {
                $errors = $record->getErrors();
                Craft::error(
                    'Cockpit department record validation failed: ' . json_encode($errors, JSON_PRETTY_PRINT),
                    __METHOD__
                );

                // Add errors to the element
                foreach ($errors as $attribute => $attributeErrors) {
                    foreach ($attributeErrors as $error) {
                        $this->addError($attribute, $error);
                    }
                }
                return;
            }

            // fields
            $record->cockpitId = $this->cockpitId;
            $record->email = $this->email;
            $record->phone = $this->phone;
            $record->reference = $this->reference;
            $record->title = $this->title;

            // Save the record
            $record->save(false);
        }

        parent::afterSave($isNew);
    }

    private function createAddressQuery(): AddressQuery
    {
        return Address::find()
            ->owner($this)
            ->orderBy(['id' => SORT_ASC]);
    }
}
