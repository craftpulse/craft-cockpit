<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\services;

use craftpulse\cockpit\assetbundles\cockpit\CockpitCpAsset;
use nystudio107\pluginvite\services\VitePluginService;
use yii\base\InvalidConfigException;

/**
 * @author    craftpulse
 * @package   Cockpit
 * @since     5.0.0
 *
 * @property Api $api
 * @property CleanupService $cleanup
 * @property JobsService $jobs
 * @property DepartmentsService $departments
 * @property PostcodeService $postcodes
 * @property MatchField $matchFields
 * @property MatchFieldEntries $matchFieldEntries
 */
trait ServicesTrait
{
    public static function config(): array
    {
        return [
            'components' => [
                'api' => Api::class,
                'cleanup' => CleanupService::class,
                'contacts' => ContactsService::class,
                'departments' => DepartmentsService::class,
                'jobs' => JobsService::class,
                'map' => MapboxService::class,
                'matchFields' => MatchField::class,
                'matchFieldEntries' => MatchFieldEntries::class,
                'postcodes' => PostcodeService::class,
                'vite' => [
                    'assetClass' => CockpitCpAsset::class,
                    'checkDevServer' => true,
                    'useForAllRequests' => true,
                    'class' => VitePluginService::class,
                    'devServerInternal' => 'http://craft-cockpit-v5-buildchain-dev:3005',
                    'devServerPublic' => 'http://localhost:3005',
                    'errorEntry' => 'src/js/Cockpit.js',
                    'useDevServer' => true,
                ],
            ],
        ];
    }

    // Public Methods
    // =========================================================================

    /**
     * Returns the api service
     *
     * @return Api The api service
     * @throws InvalidConfigException
     */
    public function getApi(): Api
    {
        return $this->get('api');
    }

    /**
     * Returns the cleanup service
     *
     * @return CleanupService The cleanup service
     * @throws InvalidConfigException
     */
    public function getCleanup(): CleanupService
    {
        return $this->get('cleanup');
    }

    /**
     * Returns the contacts service
     *
     * @return CleanupService The cleanup service
     * @throws InvalidConfigException
     */
    public function getContacts(): ContactsService
    {
        return $this->get('contacts');
    }

    /**
     * Returns the jobs service
     *
     * @return JobsService The jobs service
     * @throws InvalidConfigException
     */
    public function getJobs(): JobsService
    {
        return $this->get('jobs');
    }

    /**
     * Returns the darpartments service
     *
     * @return DepartmentsService The departments service
     * @throws InvalidConfigException
     */
    public function getDepartments(): DepartmentsService
    {
        return $this->get('departments');
    }

    /**
     * Returns the matchField service
     *
     * @return MapboxService
     * @throws InvalidConfigException
     */
    public function getMap(): MapboxService
    {
        return $this->get('map');
    }

    /**
     * Returns the matchField service
     *
     * @return MatchField The matchField service
     * @throws InvalidConfigException
     */
    public function getMatchFields(): MatchField
    {
        return $this->get('matchFields');
    }

    /**
     * Returns the matchFieldEntries service
     *
     * @return MatchFieldEntries The matchFieldEntries service
     * @throws InvalidConfigException
     */
    public function getMatchFieldEntries(): MatchFieldEntries
    {
        return $this->get('matchFieldEntries');
    }

    /**
     * Returns the postcode service
     *
     * @return PostcodeService The postcode service
     * @throws InvalidConfigException
     */
    public function getPostcodes(): PostcodeService
    {
        return $this->get('postcodes');
    }
}
