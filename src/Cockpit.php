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
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\DefineHtmlEvent;
use craft\models\FieldLayout;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use Throwable;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\User;
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
use craft\web\UrlManager;
use craftpulse\cockpit\elements\Contact;
use craftpulse\cockpit\elements\Job;
use craftpulse\cockpit\elements\MatchFieldEntry;
use craftpulse\cockpit\elements\Office;
use craftpulse\cockpit\models\SettingsModel;
use craftpulse\cockpit\services\MatchField;
use craftpulse\cockpit\services\ServicesTrait;
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

    use ServicesTrait;

    // Const Properties
    // =========================================================================
    public const CONFIG_JOBFIELD_LAYOUT_KEY = 'cockpit.jobFieldLayout';

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
            $this->_registerFieldLayouts();
            $this->_registerSidebarPanels();
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

        if ($currentUser->can('cockpit:view-contacts')) {
            $subNavs['contacts'] = [
                'label' => Craft::t('cockpit', 'Contacts'),
                'url' => 'cockpit/contacts',
            ];
        }

        if ($currentUser->can('cockpit:view-jobs')) {
            $subNavs['jobs'] = [
                'label' => Craft::t('cockpit', 'Jobs'),
                'url' => 'cockpit/jobs',
            ];
        }

        if ($currentUser->can('cockpit:view-offices')) {
            $subNavs['offices'] = [
                'label' => Craft::t('cockpit', 'Offices'),
                'url' => 'cockpit/offices',
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

        $this->_registerUserPermissions();
        $this->_registerUtilities();
        $this->_registerProjectConfigEventListeners();
    }

    // Private Methods
    // =========================================================================

    /**
     * Register Commerce’s project config event listeners
     */
    private function _registerProjectConfigEventListeners(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        $jobsService = $this->getJobs();

        $projectConfigService->onAdd(self::CONFIG_JOBFIELD_LAYOUT_KEY, [$jobsService, 'handleChangedFieldLayout'])
            ->onUpdate(self::CONFIG_JOBFIELD_LAYOUT_KEY, [$jobsService, 'handleChangedFieldLayout'])
            ->onRemove(self::CONFIG_JOBFIELD_LAYOUT_KEY, [$jobsService, 'handleDeletedFieldLayout']);

        $matchFieldsService = $this->getMatchFields();
        $projectConfigService->onAdd(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleChangedMatchField'])
            ->onUpdate(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleChangedMatchField'])
            ->onRemove(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleDeletedMatchField']);
    }

    /**
     * Registers CP URL rules event
     */
    private function _registerCpUrlRules(): void
    {
        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                // General Settings
                $event->rules['cockpit'] = 'cockpit/settings/edit';
                $event->rules['cockpit/settings'] = 'cockpit/settings/edit';
                $event->rules['cockpit/settings/general'] = 'cockpit/settings/edit';
                $event->rules['cockpit/plugins/cockpit'] = 'cockpit/settings/edit';

                // Match Field Types
                $event->rules['cockpit/settings/matchfields'] = 'cockpit/matchfields/match-field-index';
                $event->rules['cockpit/settings/matchfields/<matchFieldId:\d+>'] = 'cockpit/matchfields/edit-match-field';
                $event->rules['cockpit/settings/matchfields/new'] = 'cockpit/matchfields/edit-match-field';

                // Contact Elements
                $event->rules['cockpit/contacts'] = ['template' => 'cockpit/contacts/_index.twig'];
                $event->rules['cockpit/contacts/<elementId:\\d+>'] = 'elements/edit';

                // Job Elements
                $event->rules['cockpit/settings/jobs'] = 'cockpit/jobs/edit-settings';
                $event->rules['cockpit/jobs'] = ['template' => 'cockpit/jobs/_index.twig'];
                $event->rules['cockpit/jobs/<elementId:\\d+>'] = 'elements/edit';

                // Office Elements
                $event->rules['cockpit/offices'] = ['template' => 'cockpit/offices/_index.twig'];
                $event->rules['cockpit/offices/<elementId:\\d+>'] = 'elements/edit';

                /*$event->rules['match-field-entries'] = ['template' => 'cockpit/match-field-entries/_index.twig'];
                $event->rules['match-field-entries/<elementId:\\d+>'] = 'elements/edit';*/
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
                $event->types[] = Office::class;
                $event->types[] = MatchFieldEntry::class;
            }
        );
    }

    private function _registerFieldLayouts(): void
    {
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_NATIVE_FIELDS,
            function (DefineFieldLayoutFieldsEvent $event) {
                /** @var FieldLayout $fieldLayout */
                $fieldLayout = $event->sender;

                if ($fieldLayout->type === Job::class) {
                    foreach ($this->getJobs()->createFields() as $field) {
                        $event->fields[] = $field;
                    }
                }
            }
        );
    }

    private function _registerSidebarPanels(): void
    {
        Event::on(
            Job::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
                /** @var Element $element */
                $element = $event->sender;

                $html = Craft::$app->getView()->renderTemplate('cockpit/_components/_job-sidebar', [
                    'variable' => true,
                    'element' => $element,
                ]);

                $event->html .= $html;
            },
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
                        'cockpit:contacts' => [
                            'label' => Craft::t('cockpit', 'Contacts.'),
                        ],
                        'cockpit:jobs' => [
                            'label' => Craft::t('cockpit', 'View Jobs.'),
                        ],
                        'cockpit:save-jobs' => [
                            'label' => Craft::t('cockpit', 'Save Jobs.'),
                        ],
                        'cockpit:delete-jobs' => [
                            'label' => Craft::t('cockpit', 'Delete Jobs.'),
                        ],
                        'cockpit:offices' => [
                            'label' => Craft::t('cockpit', 'View Offices.'),
                        ],
                        'cockpit:settings' => [
                            'label' => Craft::t('cockpit', 'Manage plugin settings.'),
                        ],
                        'cockpit:settings-matchfields' => [
                            'label' => Craft::t('cockpit', 'Manage matchfields.'),
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
