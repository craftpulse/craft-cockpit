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
use craftpulse\cockpit\elements\conditions\DepartmentCondition;
use craftpulse\cockpit\elements\db\DepartmentQuery;
use yii\web\Response;

/**
 * Department element type
 */
class Department extends Element
{

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
        return false;
    }

    public static function hasStatuses(): bool
    {
        return true;
    }

    public static function find(): ElementQueryInterface
    {
        return Craft::createObject(DepartmentQuery::class, [static::class]);
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
        return array_merge(parent::defineRules(), [
            // ...
        ]);
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
        // If departments should have URLs, define their URI format here
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

    protected function route(): array|string|null
    {
        // Define how departments should be routed when their URLs are requested
        return [
            'templates/render',
            [
                'template' => 'site/template/path',
                'variables' => ['department' => $this],
            ]
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
        return true;
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

    public function afterSave(bool $isNew): void
    {
        if (!$this->propagating) {
            if ($isNew) {
                $record = new DepartmentQuery();
                $record->id = $this->id;
            } else {
                $record = DepartmentQuery::findOne($this->id);
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

            // Save the record
            $record->save(false);
        }

        parent::afterSave($isNew);
    }
}
