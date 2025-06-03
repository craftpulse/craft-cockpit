<?php

namespace craftpulse\cockpit\elements;

use Craft;
use craft\base\Element;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craft\models\FieldLayout;
use craft\web\CpScreenResponseBehavior;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\conditions\ContactCondition;
use craftpulse\cockpit\elements\db\ContactQuery;
use craftpulse\cockpit\records\ContactRecord;
use yii\web\Response;

/**
 * Contact element type
 */
class Contact extends Element
{

    /**
     * @var FieldLayout|null
     */
    private ?FieldLayout $fieldLayout = null;

    /**
     * @var string|null
     */
    public ?string $type = 'contact';
    /**
     * @var string
     */
    public string $cockpitId = '';


    public static function displayName(): string
    {
        return Craft::t('cockpit', 'Contact');
    }

    public static function lowerDisplayName(): string
    {
        return Craft::t('cockpit', 'contact');
    }

    public static function pluralDisplayName(): string
    {
        return Craft::t('cockpit', 'Contacts');
    }

    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('cockpit', 'contacts');
    }

    public static function refHandle(): ?string
    {
        return 'contact';
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

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        return Cockpit::$plugin->getSettings()->contactSiteSettings ?? [];
    }

    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(ContactQuery::class, [static::class]);
    }

    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(ContactCondition::class, [static::class]);
    }

    protected static function defineSources(string $context): array
    {
        return [
            [
                'key' => '*',
                'label' => Craft::t('cockpit', 'All contacts'),
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
                'cockpitId',
            ],
            'safe'
        ];

        return $rules;
    }

    public function getUriFormat(): ?string
    {
        $contactSettings = Cockpit::getInstance()->getSettings()->contactSiteSettings ?? [];

        if (!isset($contactSettings[$this->siteId])) {
            return null;
        }

        $hasUrls = $contactSettings[$this->siteId]['hasUrl'] ?? false;
        $uriFormat = $contactSettings[$this->siteId]['uriFormat'] ?? null;

        if (!$hasUrls) {
            return null;
        }

        if (!$uriFormat) {
            return null;
        }

        return $contactSettings[$this->siteId]['uriFormat'];
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

    protected function route(): array|string|null
    {
        // Make sure that the product is actually live
        if (!$this->previewing && $this->getStatus() != self::STATUS_ENABLED) {
            return null;
        }

        // Make sure the product type is set to have URLs for this site
        $siteId = Craft::$app->getSites()->currentSite->id;
        $settings = Cockpit::getInstance()->getSettings()->contactSiteSettings ?? [];

        if (!isset($settings[$this->siteId])) {
            return null;
        }

        return [
            'templates/render', [
                'template' => $settings[$siteId]['template'] ?? null,
                'variables' => [
                    'entry' => $this,
                    'contact' => $this,
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
        return $user->can('cockpit:view-element');
    }

    public function canSave(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:save-element');
    }

    public function canDuplicate(User $user): bool
    {
        if (parent::canDuplicate($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:duplicate-element');
    }

    public function canDelete(User $user): bool
    {
        if (parent::canSave($user)) {
            return true;
        }
        // todo: implement user permissions
        return $user->can('cockpit:delete-element');
    }

    public function canCreateDrafts(User $user): bool
    {
        return false;
    }

    protected function cpEditUrl(): ?string
    {
        return sprintf('cockpit/contacts/%s', $this->getCanonicalId());
    }

    public function getPostEditUrl(): ?string
    {
        return UrlHelper::cpUrl('cockpit/contacts');
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
                'url' => UrlHelper::cpUrl('contacts'),
            ],
        ]);
    }

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if ($isNew) {
                $record = new ContactRecord();
                $record->id = $this->id;
            } else {
                $record = ContactRecord::findOne($this->id);
            }

            $record->fieldLayoutId = $this->fieldLayout->id;

            // Job specific fields
            $record->cockpitId = $this->cockpitId;

            if (!$record->validate()) {
                $errors = $record->getErrors();
                Craft::error(
                    'Cockpit contact record validation failed: ' . json_encode($errors, JSON_PRETTY_PRINT),
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
}
