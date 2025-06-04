<?php

namespace craftpulse\cockpit\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\Queue;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\jobs\BatchFetchPublicationsJob;
use craftpulse\cockpit\jobs\FetchPublicationFromCockpitJob;
use yii\console\ExitCode;

/**
 * Jobs controller
 */
class JobsController extends Controller
{
    public $defaultAction = 'publications';

    public $publicationId;
    public $jobRequestId;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        switch ($actionID) {
            case 'publication':
                 $options[] = 'publicationId';
                break;
            case 'job-request':
                $options[] = 'jobRequestId';
                break;
            case 'delete-publication':
                $options[] = 'publicationId';
                break;
        }

        return $options;
    }

    public function optionAliases(): array
    {
        return [
            'job-request-id' => 'jobRequestId',
            'publication-id' => 'publicationId',
        ];
    }

    /**
     * fetch all publications from Cockpit
     */
    public function actionPublications(): int
    {
        Console::stdout('Queueing batched publication fetch...'.PHP_EOL, Console::FG_CYAN);

        try {
            Queue::push(
                job: new BatchFetchPublicationsJob(),
                priority: 2,
                ttr: 1000,
                queue: Cockpit::$plugin->queue
            );
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            Console::stderr("Error: {$e->getMessage()}".PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * fetch publication by id from Cockpit
     */
    public function actionPublication(): int
    {
        try {
            // Get the ID from command line options
            $id = $this->publicationId;

            if (!$id) {
                Craft::error('Publication ID (as --id=x) is required');
                Console::stderr('   > Error on fetching publication: Publication ID is required' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            }

            if (!Cockpit::$plugin->getJobs()->fetchPublicationById($id)) {
                return ExitCode::DATAERR;
            }

            return ExitCode::OK;

        } catch (\Exception $e) {
            Console::stderr('   > Error on fetching publication: '.$e->getMessage() . PHP_EOL);
            Craft::error($e->getMessage());
        }

        return ExitCode::DATAERR;
    }

    public function actionJobRequest(): int
    {
        try {
            // Get the ID from command line options
            $id = $this->jobRequestId;

            Console::stdout('Start job request fetch ' . $id . PHP_EOL, Console::FG_CYAN);

            if (!$id) {
                Craft::error('Job request ID (as --id=x) is required');
                Console::stderr('   > Error on fetching job request: Job request ID is required' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            }

            if (!Cockpit::$plugin->getJobs()->fetchJobRequestByid($id)) {
                return ExitCode::DATAERR;
            }

            return ExitCode::OK;

        } catch (\Exception $e) {
            Console::stderr('   > Error on fetching publication: '.$e->getMessage() . PHP_EOL);
            Craft::error($e->getMessage());
        }

        return ExitCode::DATAERR;
    }

    public function actionDeletePublication(): int
    {
        try {
            // Get the ID from command line options
            $id = $this->publicationId;

            Console::stdout('Start publication deletion ' . $id . PHP_EOL, Console::FG_CYAN);

            if (!$id) {
                Craft::error('Publication ID (as --id=x) is required');
                Console::stderr('   > Error on deleting publication: Publication ID is required' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            }

            if (!Cockpit::$plugin->getJobs()->deleteJobByCockpitId($id)) {
                return ExitCode::DATAERR;
            }

            return ExitCode::OK;

        } catch (\Exception $e) {
            Console::stderr('   > Error on deleting publication: '.$e->getMessage() . PHP_EOL);
            Craft::error($e->getMessage());
        }

        return ExitCode::DATAERR;
    }
}
