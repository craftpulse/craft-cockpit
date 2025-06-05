<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craftpulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Address;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\DefineHtmlEvent;
use craft\events\PluginEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\Json;
use craft\log\MonologTarget;
use craft\services\Elements;
use craft\services\Plugins;
use craft\services\UserPermissions;
use craft\services\Utilities;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;

use craftpulse\cockpit\base\PluginTrait;
use craftpulse\cockpit\behaviors\CandidateBehaviour;
use craftpulse\cockpit\elements\Contact;
use craftpulse\cockpit\elements\Department;
use craftpulse\cockpit\elements\Job;
use craftpulse\cockpit\elements\MatchFieldEntry;
use craftpulse\cockpit\models\SettingsModel;
use craftpulse\cockpit\services\ServicesTrait;
use craftpulse\cockpit\variables\CockpitVariable;

use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use Throwable;
use yii\base\Event;
use yii\base\InvalidRouteException;
use yii\log\Dispatcher;
use yii\log\Logger;

/**
 * Class Cockpit
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 * @method SettingsModel getSettings()
 * @property-read SettingsModel $settings
 */
class Cockpit extends Plugin
{
    // Traits
    // =========================================================================

    use PluginTrait;
    use ServicesTrait;

    // Const Properties
    // =========================================================================
    public const CONFIG_CONTACT_FIELD_LAYOUT_KEY = 'cockpit.dcontactFieldLayout';
    public const CONFIG_DEPARTMENT_FIELD_LAYOUT_KEY = 'cockpit.departmentFieldLayout';
    public const CONFIG_JOB_FIELD_LAYOUT_KEY = 'cockpit.jobFieldLayout';

    // Static Properties
    // =========================================================================
    /**
     * @var ?Cockpit
     */
    public static ?Cockpit $plugin = null;


    // Public Properties
    // =========================================================================
    /**
     * @var null|SettingsModel
     */
    public static ?SettingsModel $settings = null;

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var mixed|object|null
     */
    public mixed $queue = null;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Register custom log target
        $this->registerLogTarget();

        $request = Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            $this->controllerNamespace = 'craftpulse\cockpit\console\controllers';
        }

        // Install our global event handlers
        $this->installEventHandlers();

        // Register control panel events
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->_registerCpUrlRules();
            $this->_registerElements();
            $this->_registerSidebarPanels();
            $this->_registerUserFields();
        }

        // Log that the plugin has loaded
        Craft::info(
            Craft::t(
                'cockpit',
                '{name} plugin loaded',
                ['name' => $this->name]
            )
        );
    }

    // Public Methods
    // =========================================================================
    /**
     * Logs a message
     * @throws Throwable
     */
    public function log(string $message, array $params = [], int $type = Logger::LEVEL_INFO): void
    {
        /** @var User|null $user */
        $user = Craft::$app->getUser()->getIdentity();

        if ($user !== null) {
            $params['username'] = $user->username;
        }

        $encoded_params = str_replace('\\', '', Json::encode($params));

        $message = Craft::t('cockpit', $message . ' ' . $encoded_params, $params);

        Craft::getLogger()->log($message, $type, 'cockpit');
    }

    /**
     * @inheritdoc
     * @throws InvalidRouteException
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect('cockpit/settings');
    }

    /**
     * @inheritdoc
     * @throws Throwable
     */
    public function getCpNavItem(): ?array
    {
        $subNavs = [];
        $navItem = parent::getCpNavItem();
        $currentUser = Craft::$app->getUser()->getIdentity();

        $editableSettings = true;
        $general = Craft::$app->getConfig()->getGeneral();

        if (!$general->allowAdminChanges) {
            $editableSettings = false;
        }

        if ($currentUser->can('cockpit:view-jobs')) {
            $subNavs['jobs'] = [
                'label' => Craft::t('cockpit', 'Jobs'),
                'url' => 'cockpit/jobs',
            ];
        }

        if ($currentUser->can('cockpit:view-departments')) {
            $subNavs['departments'] = [
                'label' => Craft::t('cockpit', 'Departments'),
                'url' => 'cockpit/departments',
            ];
        }

        if ($currentUser->can('cockpit:postcode-mapping')) {
            $subNavs['postcodes'] = [
                'label' => 'Postcode Mapping',
                'url' => 'cockpit/postcodes',
            ];
        }

        if ($currentUser->can('cockpit:view-contacts')) {
            $subNavs['contacts'] = [
                'label' => Craft::t('cockpit', 'Contacts'),
                'url' => 'cockpit/contacts',
            ];
        }

        if ($currentUser->can('cockpit:view-match-field-entries')) {
            $subNavs['match-field-entries'] = [
                'label' => Craft::t('cockpit', 'Match field entries'),
                'url' => 'cockpit/match-field-entries',
            ];
        }

        if ($currentUser->can('cockpit:settings') && $editableSettings) {
            $subNavs['settings'] = [
                'label' => 'Settings',
                'url' => 'cockpit/settings',
            ];
        }

        if (empty($subNavs)) {
            return null;
        }

        // A single sub nav item is redundant
        if (count($subNavs) === 1) {
            $subNavs = [];
        }

        return array_merge($navItem, [
            'subnav' => $subNavs,
        ]);
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'cockpit/settings/general/_edit',
            ['settings' => $this->getSettings()]
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new SettingsModel();
    }

    /**
     * @return void
     */
    protected function installEventHandlers(): void
    {
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS,
            function(PluginEvent $event) {
                if ($event->plugin === $this) {
                    Craft::debug(
                        'Plugins::EVENT_AFTER_SAVE_PLUGIN_SETTINGS',
                        __METHOD__
                    );
                }
            }
        );

        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('cockpit', [
                    'class' => CockpitVariable::class,
                    'viteService' => $this->vite,
                ]);
            }
        );

        $this->_registerFieldLayoutListener();
        $this->_registerUserPermissions();
        $this->_registerUtilities();
        $this->_registerProjectConfigEventListeners();
    }

    // Private Methods
    // =========================================================================

    /**
     * Registers CP URL rules event
     */
    private function _registerCpUrlRules(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // General Settings
                $event->rules['cockpit/settings'] = 'cockpit/settings/edit';
                $event->rules['cockpit/settings/general'] = 'cockpit/settings/edit';
                $event->rules['cockpit/plugins/cockpit'] = 'cockpit/settings/edit';

                // Match Field Types
                $event->rules['cockpit/settings/matchfields'] = 'cockpit/match-fields/match-field-index';
                $event->rules['cockpit/settings/matchfields/<matchFieldId:\d+>'] = 'cockpit/match-fields/edit-match-field';
                $event->rules['cockpit/settings/matchfields/new'] = 'cockpit/match-fields/edit-match-field';
                $event->rules['cockpit/match-field-entries'] = 'cockpit/match-field-entries/match-field-entry-index';
                $event->rules['cockpit/match-field-entries/<matchFieldTypeHandle:{handle}>'] = 'cockpit/match-field-entries/match-field-entry-index';
                $event->rules['cockpit/match-field-entries/<matchFieldType:{handle}>/new'] = 'cockpit/match-field-entries/create';
                $event->rules['cockpit/match-field-entries/<matchFieldTypeHandle:{handle}>/<elementId:\d+><slug:(?:-[^\/]*)?>'] = 'elements/edit';

                // Contact Elements
                $event->rules['cockpit/settings/contacts'] = 'cockpit/contacts/edit-settings';
                $event->rules['cockpit/contacts'] = ['template' => 'cockpit/contacts/_index.twig'];
                $event->rules['cockpit/contacts/<elementId:\\d+>'] = 'elements/edit';

                // Job Elements
                $event->rules['cockpit'] = ['template' => 'cockpit/jobs/_index.twig'];
                $event->rules['cockpit/settings/jobs'] = 'cockpit/jobs/edit-settings';
                $event->rules['cockpit/jobs'] = ['template' => 'cockpit/jobs/_index.twig'];
                $event->rules['cockpit/jobs/<elementId:\\d+>'] = 'elements/edit';

                // Department Elements
                $event->rules['cockpit/settings/departments'] = 'cockpit/departments/edit-settings';
                $event->rules['cockpit/departments'] = ['template' => 'cockpit/departments/_index.twig'];
                $event->rules['cockpit/departments/<elementId:\\d+>'] = 'elements/edit';

                // Office Elements
                $event->rules['cockpit/offices'] = ['template' => 'cockpit/offices/_index.twig'];
                $event->rules['cockpit/offices/<elementId:\\d+>'] = 'elements/edit';

                // Postcodes
                $event->rules['cockpit/postcodes'] = 'cockpit/postcodes/postcode-mapping';

                // User
                $event->rules['users/<userId:\d+>/cockpit'] = 'cockpit/candidate/index';
                $event->rules['myaccount/cockpit'] = 'cockpit/candidate/current';
            }
        );
    }

    /**
     * Registers elements
     */
    private function _registerElements(): void
    {
        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Contact::class;
                $event->types[] = Job::class;
                $event->types[] = Department::class;
                $event->types[] = MatchFieldEntry::class;
            }
        );
    }


    /**
     * Registers user permissions
     */
    private function _registerUserPermissions(): void
    {
        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'Cockpit',
                    'permissions' => [
                        'cockpit:view-contacts' => [
                            'label' => Craft::t('cockpit', 'View contacts'),
                        ],
                        'cockpit:create-contacts' => [
                            'label' => Craft::t('cockpit', 'Create contacts'),
                        ],
                        'cockpit:save-contacts' => [
                            'label' => Craft::t('cockpit', 'Save contacts'),
                        ],
                        'cockpit:edit-contacts' => [
                            'label' => Craft::t('cockpit', 'Edit contacts'),
                        ],
                        'cockpit:duplicate-contacts' => [
                            'label' => Craft::t('cockpit', 'Duplicate contacts'),
                        ],
                        'cockpit:delete-contacts' => [
                            'label' => Craft::t('cockpit', 'Delete contacts'),
                        ],
                        'cockpit:view-jobs' => [
                            'label' => Craft::t('cockpit', 'View jobs'),
                        ],
                        'cockpit:create-jobs' => [
                            'label' => Craft::t('cockpit', 'Create jobs'),
                        ],
                        'cockpit:save-jobs' => [
                            'label' => Craft::t('cockpit', 'Save jobs'),
                        ],
                        'cockpit:edit-jobs' => [
                            'label' => Craft::t('cockpit', 'Edit jobs'),
                        ],
                        'cockpit:duplicate-jobs' => [
                            'label' => Craft::t('cockpit', 'Duplicate jobs'),
                        ],
                        'cockpit:delete-jobs' => [
                            'label' => Craft::t('cockpit', 'Delete jobs'),
                        ],
                        'cockpit:view-offices' => [
                            'label' => Craft::t('cockpit', 'View offices'),
                        ],
                        'cockpit:create-offices' => [
                            'label' => Craft::t('cockpit', 'Create offices'),
                        ],
                        'cockpit:save-offices' => [
                            'label' => Craft::t('cockpit', 'Save offices'),
                        ],
                        'cockpit:edit-offices' => [
                            'label' => Craft::t('cockpit', 'Edit offices'),
                        ],
                        'cockpit:duplicate-offices' => [
                            'label' => Craft::t('cockpit', 'Duplicate offices'),
                        ],
                        'cockpit:delete-offices' => [
                            'label' => Craft::t('cockpit', 'Delete offices'),
                        ],
                        'cockpit:view-departments' => [
                            'label' => Craft::t('cockpit', 'View departments'),
                        ],
                        'cockpit:create-departments' => [
                            'label' => Craft::t('cockpit', 'Create departments'),
                        ],
                        'cockpit:save-departments' => [
                            'label' => Craft::t('cockpit', 'Save departments'),
                        ],
                        'cockpit:edit-departments' => [
                            'label' => Craft::t('cockpit', 'Edit departments'),
                        ],
                        'cockpit:duplicate-departments' => [
                            'label' => Craft::t('cockpit', 'Duplicate departments'),
                        ],
                        'cockpit:delete-departments' => [
                            'label' => Craft::t('cockpit', 'Delete departments'),
                        ],
                        'cockpit:view-match-field-entries' => [
                            'label' => Craft::t('cockpit', 'View match fields'),
                        ],
                        'cockpit:create-match-field-entries' => [
                            'label' => Craft::t('cockpit', 'Create match fields'),
                        ],
                        'cockpit:save-match-field-entries' => [
                            'label' => Craft::t('cockpit', 'Save match fields'),
                        ],
                        'cockpit:edit-match-field-entries' => [
                            'label' => Craft::t('cockpit', 'Edit match fields'),
                        ],
                        'cockpit:duplicate-match-field-entries' => [
                            'label' => Craft::t('cockpit', 'Duplicate match fields'),
                        ],
                        'cockpit:delete-match-field-entries' => [
                            'label' => Craft::t('cockpit', 'Delete match fields'),
                        ],
                        // @TODO: add all the setting permissions
                        'cockpit:settings' => [
                            'label' => Craft::t('cockpit', 'Manage plugin settings'),
                        ],
                        'cockpit:settings-matchfields' => [
                            'label' => Craft::t('cockpit', 'Manage match fields.'),
                        ],
                        'cockpit:postcode-mapping' => [
                            'label' => Craft::t('cockpit', 'Manage the Postcode code mapping.'),
                        ],
                    ],
                ];
            }
        );
    }

    /**
     * Registers utilities
     */
    private function _registerUtilities(): void
    {
        Event::on(Utilities::class, Utilities::EVENT_REGISTER_UTILITIES,
            function(RegisterComponentTypesEvent $event) {
                //$event->types[] = ???::class;
            }
        );
    }

    /**
     * Registers a custom log target
     *
     * @see LineFormatter::SIMPLE_FORMAT
     */
    private function registerLogTarget(): void
    {
        if (Craft::getLogger()->dispatcher instanceof Dispatcher) {
            Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
                'name' => 'cockpit',
                'categories' => ['cockpit'],
                'level' => LogLevel::INFO,
                'logContext' => false,
                'allowLineBreaks' => true,
                'formatter' => new LineFormatter(
                    format: "%datetime% [%channel%.%level_name%] %message% %context%\n",
                    dateFormat: 'Y-m-d H:i:s',
                ),
            ]);
        }
    }
}
