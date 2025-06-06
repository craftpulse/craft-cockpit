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
use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;
use craftpulse\cockpit\elements\Contact;
use craftpulse\cockpit\elements\Department;
use craftpulse\cockpit\elements\Job;

/**
 * Class SettingsModel
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 */
class SettingsModel extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var string|null the API Key of the Cockpit Environment
     */
    public ?string $apiKey = null;

    /**
     * @var string|null the API URL of the Cockpit Environment
     */
    public ?string $apiUrl = null;

    /**
     * @var bool if we want to enable Mapbox
     */
    public bool $enableMapbox = false;

    /**
     * @var string the Mapbox API Key
     */
    public ?string $mapboxApiKey = null;

    /**
     * @var array the job site settings
     */
    public ?array $jobSiteSettings = null;

    /**
     * @var array the department site settings
     */
    public ?array $departmentSiteSettings = null;

    /**
     * @var array the contact site settings
     */
    public ?array $contactSiteSettings = null;

    /**
     * @var bool if we want to register users
     */
    public bool $registerUsers = true;

    /**
     * @var string register to user group
     */
    public string $userGroup = 'applicants';

    /**
     * @var string the website publication match field id
     */
    public string $websitePublicationMatchFieldId = 'MatchFields-5904-C';

    // Private Properties
    // =========================================================================

    /**
     * @var mixed
     */
    private mixed $_jobFieldLayout;

    /**
     * @var mixed
     */
    private mixed $_departmentFieldLayout;

    /**
     * @var mixed
     */
    private mixed $_contactFieldLayout;

    public function __construct($config = [])
    {
        parent::__construct($config);

        if ($this->contactSiteSettings === null) {
            foreach (Craft::$app->sites->getAllSites() as $site) {
                $this->contactSiteSettings[$site->id] = [
                    'siteId' => $site->id,
                    'enabled' => true,
                    'template' => null,
                    'uriFormat' => null,
                    'enabledByDefault' => true,
                ];
            }
        }

        if ($this->departmentSiteSettings === null) {
            foreach (Craft::$app->sites->getAllSites() as $site) {
                $this->departmentSiteSettings[$site->id] = [
                    'siteId' => $site->id,
                    'enabled' => true,
                    'template' => null,
                    'uriFormat' => null,
                    'enabledByDefault' => true,
                ];
            }
        }

        if ($this->jobSiteSettings === null) {
            foreach (Craft::$app->sites->getAllSites() as $site) {
                $this->jobSiteSettings[$site->id] = [
                    'siteId' => $site->id,
                    'enabled' => true,
                    'template' => null,
                    'uriFormat' => null,
                    'enabledByDefault' => true,
                ];
            }
        }
    }

    /**
     * @return array[]
     */
    protected function defineBehaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'apiKey',
                    'apiUrl',
                    'enableMapbox',
                    'mapboxApiKey',
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['apiKey', 'apiUrl'], 'required']
            // @TODO if mapbox enabled, require mapboxApiKey,
        ];
    }

    /**
     * @return \craft\models\FieldLayout|mixed
     */
    public function getJobFieldLayout()
    {
        if (!isset($this->_jobFieldLayout)) {
            $this->_jobFieldLayout = Craft::$app->getFields()->getLayoutByType(Job::class);
        }

        return $this->_jobFieldLayout;
    }

    /**
     * @param mixed $fieldLayout
     * @return void
     */
    public function setJobFieldLayout(mixed $fieldLayout): void
    {
        $this->_jobFieldLayout = $fieldLayout;
    }

    /**
     * @return \craft\models\FieldLayout|mixed
     */
    public function getDepartmentFieldLayout()
    {
        if (!isset($this->_departmentFieldLayout)) {
            $this->_departmentFieldLayout = Craft::$app->getFields()->getLayoutByType(Department::class);
        }

        return $this->_departmentFieldLayout;
    }

    /**
     * @param mixed $fieldLayout
     * @return void
     */
    public function setDepartmentFieldLayout(mixed $fieldLayout): void
    {
        $this->_departmentFieldLayout = $fieldLayout;
    }

    /**
     * @return \craft\models\FieldLayout|mixed
     */
    public function getContactFieldLayout()
    {
        if (!isset($this->_departmentFieldLayout)) {
            $this->_contactFieldLayout = Craft::$app->getFields()->getLayoutByType(Contact::class);
        }

        return $this->_contactFieldLayout;
    }

    /**
     * @param mixed $fieldLayout
     * @return void
     */
    public function setContactFieldLayout(mixed $fieldLayout): void
    {
        $this->_contactFieldLayout = $fieldLayout;
    }
}
