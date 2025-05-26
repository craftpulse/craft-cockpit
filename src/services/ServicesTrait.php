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

use yii\base\InvalidConfigException;

/**
 * @author    craftpulse
 * @package   Cockpit
 * @since     5.0.0
 *
 * @property CleanupService $cleanup
 * @property JobService $jobs
 * @property OfficeService $offices
 * @property
 */
trait ServicesTrait
{
    public static function config(): array
    {
        return [
            'components' => [
                'cleanup' => CleanupService::class,
                'jobs' => JobService::class,
                'offices' => OfficeService::class,
            ],
        ];
    }

    // Public Methods
    // =========================================================================

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
     * Returns the jobs service
     *
     * @return JobService The jobs service
     * @throws InvalidConfigException
     */
    public function getJobs(): JobsService
    {
        return $this->get('jobs');
    }

    /**
     * Returns the offices service
     *
     * @return OfficeService The offices service
     * @throws InvalidConfigException
     */
    public function getOffices(): OfficeService
    {
        return $this->get('offices');
    }
}
