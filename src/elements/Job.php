<?php

namespace craftpulse\cockpit\elements;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
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
    /**
     * @var FieldLayout|null
     */
    private ?FieldLayout $fieldLayout = null;

    public ?string $type = 'job';
    public ?string $applicationCount = null;
    public string $city = '';
    public string $cockpitCompanyId ='';
    public string $cockpitId = '';
    public string $cockpitJobRequestId = '';
    public string $cockpitOfficeId = '';
    public string $companyName = '';
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?int $openPositions = null;
    public ?string $postCode = null;
    public ?string $street = null;

    // Methods
    // =========================================================================
    public static function displayName(): string
    {
        return Craft::t('cockpit', 'Job');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('cockpit', 'job');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('cockpit', 'Jobs');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('cockpit', 'jobs');
    }

    public static function refHandle(): ?string
    {
        return 'job';
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
        return false;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(JobQuery::class, [static::class]);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(JobCondition::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('cockpit', 'All jobs'),
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
                'applicationCount',
                'city',
                'cockpitCompanyId',
                'cockpitId',
                'cockpitJobRequestId',
                'cockpitOfficeId',
                'companyName',
                'latitude',
                'longitude',
                'openPositions',
                'postCode',
                'street',
            ],
            'safe'
        ];

        if ($this->id !== null) {
            $rules[] = [[
                'city',
                'cockpitCompanyId',
                'cockpitId',
                'cockpitJobRequestId',
                'cockpitOfficeId',
                'companyName',
            ], 'required'];

            $rules[] = [['applicationCount', 'openPositions'], 'integer'];
            $rules[] = [['latitude'], 'number', 'min' => -90, 'max' => 90];
            $rules[] = [['longitude'], 'number', 'min' => -180, 'max' => 180];
        }

        return $rules;
    }

    public function getUriFormat(): ?string
    {
        // If jobs should have URLs, define their URI format here
        return Cockpit::getInstance()->getSettings()->jobUriFormat;
    }

    protected function previewTargets(): array
    {
        if ($uriFormat = $this->getUriFormat()) {
            return [[
                'urlFormat' => $uriFormat,
            ]];
        }

        return [];
    }

    protected function route(): array|string|null
    {
        if (!$this->previewing && $this->getStatus() != self::STATUS_ENABLED) {
            return null;
        }

        $settings = Cockpit::getInstance()->getSettings();

        if ($settings->jobUriFormat) {
            return [
                'templates/render', [
                    'template' => $settings->jobTemplate,
                    'variables' => [
                        'entry' => $this,
                    ],
                ],
            ];
        }

        return null;
    }

    public function canView(User $user): bool
    {
        if (parent::canView($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:jobs');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-jobs');
    }

    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-jobs');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:delete-jobs');
    }

    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('cockpit/jobs/%s', $this->getCanonicalId());
    }

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
     * @inheritdoc
     * @throws Exception
     */
    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if ($isNew) {
                $jobRecord = new JobRecord();
                $jobRecord->id = $this->id;
            } else {
                $jobRecord = JobRecord::findOne($this->id);
            }

            $jobRecord->fieldLayoutId = $this->fieldLayout->id;

            // Job specific fields
            $jobRecord->applicationCount = $this->applicationCount;
            $jobRecord->city = $this->city;
            $jobRecord->cockpitCompanyId = $this->cockpitCompanyId;
            $jobRecord->cockpitId = $this->cockpitId;
            $jobRecord->cockpitJobRequestId = $this->cockpitJobRequestId;
            $jobRecord->cockpitOfficeId = $this->cockpitOfficeId;
            $jobRecord->companyName = $this->companyName;
            $jobRecord->latitude = $this->latitude;
            $jobRecord->longitude = $this->longitude;
            $jobRecord->openPositions = $this->openPositions;
            $jobRecord->postCode = $this->postCode;
            $jobRecord->street = $this->street;

            if (!$jobRecord->validate()) {
                $errors = $jobRecord->getErrors();
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
            $jobRecord->save(false);
        }

        parent::afterSave($isNew);
    }

}
