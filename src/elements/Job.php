<?php

namespace craftpulse\cockpit\elements;

use craft\elements\db\AddressQuery;
use craft\elements\ElementCollection;
use craft\elements\NestedElementManager;
use craft\enums\PropagationMethod;
use craft\helpers\StringHelper;
use DateTime;
use Craft;
use craft\base\Element;
use craft\elements\Address;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\web\CpScreenResponseBehavior;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\conditions\JobCondition;
use craftpulse\cockpit\elements\db\JobQuery;
use craftpulse\cockpit\records\JobRecord;
use yii\web\Response;

/**
 * Job element type
 */
class Job extends Element
{

    // Properties
    // =========================================================================

    public const STATUS_EXPIRED = 'expired';
    public const STATUS_PENDING = 'pending';

    /**
     * @var FieldLayout|null
     */
    private ?FieldLayout $fieldLayout = null;

    /**
     * @var string|null
     */
    public ?string $type = 'job';
    /**
     * @var int
     */
    public int $applicationCount = 1;
    /**
     * @var string
     */
    public string $cockpitCompanyId ='';
    /**
     * @var string
     */
    public string $cockpitId = '';
    /**
     * @var string
     */
    public string $cockpitJobRequestId = '';
    /**
     * @var string
     */
    public string $cockpitDepartmentId = '';
    /**
     * @var string
     */
    public string $companyName = '';
    /**
     * @var int
     */
    public int $openPositions = 1;

    /**
     * @var DateTime|null Post date
     */
    public ?DateTime $postDate = null;

    /**
     * @var DateTime|null Expiry date
     */
    public ?DateTime $expiryDate = null;

    /**
     * @var ElementCollection<Address> Address
     * @see getAddres()
     */
    private ElementCollection $_address;

    /**
     * @see getAddressManager()
     */
    private NestedElementManager $_addressManager;

    // Methods
    // =========================================================================
    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('cockpit', 'Job');
    }

    /**
     * @return string
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('cockpit', 'job');
    }

    /**
     * @return string
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('cockpit', 'Jobs');
    }

    /**
     * @return string
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('cockpit', 'jobs');
    }

    /**
     * @return string|null
     */
    public static function refHandle(): ?string
    {
        return 'job';
    }

    /**
     * @return bool
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function hasUris(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @return bool
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
            parent::STATUS_ENABLED => Craft::t('app', 'Enabled'),
            self::STATUS_PENDING => Craft::t('app', 'Pending'),
            self::STATUS_EXPIRED => ['label' => Craft::t('app', 'Expired'), 'color' => 'red'],
            parent::STATUS_DISABLED => Craft::t('app', 'Disabled'),
        ];
    }

    /**
     * @return ElementQueryInterface
     * @throws \yii\base\InvalidConfigException
     */
    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(JobQuery::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        return Cockpit::$plugin->getSettings()->jobSiteSettings ?? [];
    }

    // Public Methods
    // =========================================================================
    public function getDepartment(): ?Department
    {
        if ($this->id) {
            return Department::find()->cockpitId($this->cockpitDepartmentId)->one() ?? null;
        }

        return null;
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

    /**
     * @return ElementConditionInterface
     * @throws \yii\base\InvalidConfigException
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(JobCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        return array_merge(parent::attributeLabels(), [
            'postDate' => Craft::t('app', 'Post Date'),
            'expiryDate' => Craft::t('app', 'Expiry Date'),
        ]);
    }

    /**
     * @param string $context
     * @return array[]
     */
    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('cockpit', 'All jobs'),
            ],
        ];
    }

    /**
     * @param string $source
     * @return array
     */
    protected static function defineActions(string $source): array
    {
        // List any bulk element actions here
        return [];
    }

    /**
     * @return bool
     */
    protected static function includeSetStatusAction(): bool
    {
        return true;
    }

    /**
     * @return array
     */
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
                'label' => Craft::t('app', 'Expiry Date'),
                'orderBy' => 'expiryDate',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'ID'),
                'orderBy' => 'elements.id',
                'attribute' => 'id',
            ],
            [
                'label' => Craft::t('app', 'Department'),
                'orderBy' => 'department',
                'attribute' => 'department',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('app', 'Address'),
                'orderBy' => 'address',
                'attribute' => 'address',
                'defaultDir' => 'desc',
            ],
            // ...
        ];
    }

    /**
     * @return array[]
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'address' => ['label' => Craft::t('app', 'Address')],
            'cockpitId' => ['label' => Craft::t('app', 'Cockpit ID')],
            'companyName' => ['label' => Craft::t('app', 'Company')],
            'dateCreated' => ['label' => Craft::t('app', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('app', 'Date Updated')],
            'department' => ['label' => Craft::t('app', 'Department')],
            'expiryDate' => ['label' => Craft::t('app', 'Expiry Date')],
            'id' => ['label' => Craft::t('app', 'ID')],
            'link' => ['label' => Craft::t('app', 'Link'), 'icon' => 'world'],
            'postDate' => ['label' => Craft::t('app', 'Post Date')],
            'slug' => ['label' => Craft::t('app', 'Slug')],
            'uid' => ['label' => Craft::t('app', 'UID')],
            'uri' => ['label' => Craft::t('app', 'URI')],
            // ...
        ];
    }

    public function tableAttributeHtml(string $attribute): string
    {
        if ($attribute === 'address') {
            $address = $this->getAddress()->one();

            if (is_array($address)) {
                return implode(', ', $address);
            }

            if (is_object($address)) {
                if (method_exists($address, '__toString')) {
                    return (string)$address;
                }
                // maybe it's a field value object
                return json_encode($address);
            }

            return $address ?? '';
        }

        if ($attribute === 'department') {
            return $this->getDepartment()?->title ?? '';
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @param string $source
     * @return array|string[]
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        $attributes = [];

        $attributes[] = 'status';
        $attributes[] = 'postDate';
        $attributes[] = 'expiryDate';
        $attributes[] = 'link';

        return $attributes;
    }

    /**
     * @return array
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            [
                'applicationCount',
                'cockpitCompanyId',
                'cockpitId',
                'cockpitJobRequestId',
                'cockpitDepartmentId',
                'companyName',
                'expiryDate',
                'openPositions',
                'postDate',
            ],
            'safe'
        ];

        if ($this->id !== null) {
            $rules[] = [[
                'cockpitCompanyId',
                'cockpitId',
                'cockpitJobRequestId',
                'cockpitDepartmentId',
                'companyName',
            ], 'required'];

            $rules[] = [['applicationCount', 'openPositions'], 'integer'];
        }

        return $rules;
    }

    /**
     * @return string|null
     */
    public function getUriFormat(): ?string
    {
        $departmentSettings = Cockpit::getInstance()->getSettings()->jobSiteSettings ?? [];

        if (!isset($departmentSettings[$this->siteId])) {
            return null;
        }

        return $departmentSettings[$this->siteId]['uriFormat'];
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        $status = parent::getStatus();

        if ($status == self::STATUS_ENABLED && $this->postDate) {
            $currentTime = DateTimeHelper::currentTimeStamp();
            $postDate = $this->postDate->getTimestamp();
            $expiryDate = $this->expiryDate?->getTimestamp();

            if ($postDate <= $currentTime && ($expiryDate === null || $expiryDate > $currentTime)) {
                return parent::STATUS_ENABLED;
            }

            if ($postDate > $currentTime) {
                return self::STATUS_PENDING;
            }

            return self::STATUS_EXPIRED;
        }

        return $status;
    }

    /**
     * @return array|string|null
     */
    protected function route(): array|string|null
    {
        // Make sure that the product is actually live
        if (!$this->previewing && $this->getStatus() != self::STATUS_ENABLED) {
            return null;
        }

        // Make sure the product type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $settings = Cockpit::getInstance()->getSettings()->jobSiteSettings ?? [];

        if (!isset($settings[$this->siteId])) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $settings[$siteId]['template'],
                'variables' => [
                    'entry' => $this,
                    'job' => $this,
                ],
            ],
        ];
    }

    /**
     * @param User $user
     * @return bool
     */
    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:jobs');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-jobs');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-jobs');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:delete-jobs');
    }

    /**
     * @param User $user
     * @return bool
     */
    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    /**
     * @return string|null
     */
    protected function cpEditUrl(): ?string
    {
        return sprintf('cockpit/jobs/%s', $this->getCanonicalId());
    }

    /**
     * @return string|null
     */
    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('cockpit/jobs');
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

    /**
     * @param Response $response
     * @param string $containerId
     * @return void
     */
    public function prepareEditScreen(Response $response, string $containerId): void
    {
        /** @var Response|CpScreenResponseBehavior $response */
        $response->crumbs([
            [
                'label' => self::pluralDisplayName(),
                'url' => UrlHelper::cpUrl('cockpit/jobs'),
            ],
        ]);
    }

    /**
     * @param bool $isNew
     * @return bool
     */
    public function beforeSave(bool $isNew): bool
    {
        return parent::beforeSave($isNew); // TODO: Change the autogenerated stub
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

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
//        Craft::dd($this->dateCreated);
        if (!$this->propagating) {
            if ($isNew) {
                $record = new JobRecord();
                $record->id = $this->id;
            } else {
                $record = JobRecord::findOne($this->id);
            }

            if (!$this->postDate) {
                $this->postDate = $this->dateCreated;
            }

            $record->fieldLayoutId = $this->fieldLayout->id;

            // Job specific fields
            $record->postDate = $this->postDate;
            $record->expiryDate = $this->expiryDate;

            $record->applicationCount = $this->applicationCount;
            $record->cockpitCompanyId = $this->cockpitCompanyId;
            $record->cockpitDepartmentId = $this->cockpitDepartmentId;
            $record->cockpitId = $this->cockpitId;
            $record->cockpitJobRequestId = $this->cockpitJobRequestId;
            $record->companyName = $this->companyName;
            $record->openPositions = $this->openPositions;
            $record->title = $this->title;

            if (!$record->validate()) {
                $errors = $record->getErrors();
                Craft::error(
                    'Cockpit job record validation failed: ' . json_encode($errors, JSON_PRETTY_PRINT),
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


            // Save the record
            $record->save(false);
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    protected function metaFieldsHtml(bool $static): string
    {
        $fields = [];
        $view = Craft::$app->getView();
        // Slug
        $fields[] = $this->slugFieldHtml($static);

        $isDeltaRegistrationActive = $view->getIsDeltaRegistrationActive();
        $view->registerDeltaName('postDate');
        $view->registerDeltaName('expiryDate');
        $view->setIsDeltaRegistrationActive($isDeltaRegistrationActive);

        // Post Date
        $fields[] = Cp::dateTimeFieldHtml([
            'status' => $this->getAttributeStatus('postDate'),
            'label' => Craft::t('app', 'Post Date'),
            'id' => 'postDate',
            'name' => 'postDate',
            'value' => $this->postDate,
            'errors' => $this->getErrors('postDate'),
            'disabled' => $static,
        ]);

        // Expiry Date
        $fields[] = Cp::dateTimeFieldHtml([
            'status' => $this->getAttributeStatus('expiryDate'),
            'label' => Craft::t('app', 'Expiry Date'),
            'id' => 'expiryDate',
            'name' => 'expiryDate',
            'value' => $this->expiryDate,
            'errors' => $this->getErrors('expiryDate'),
            'disabled' => $static,
        ]);

        $fields[] = parent::metaFieldsHtml($static);

        return implode("\n", $fields);
    }

    private function createAddressQuery(): AddressQuery
    {
        return Address::find()
            ->owner($this)
            ->orderBy(['id' => SORT_ASC]);
    }
}
