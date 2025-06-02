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
    public ?array $jobSiteSettings = [];

    /**
     * @var array the department site settings
     */
    public ?array $departmentSiteSettings = [];

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
}
