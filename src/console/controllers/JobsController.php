<?php

namespace craftpulse\cockpit\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craftpulse\cockpit\Cockpit;
use yii\console\ExitCode;

/**
 * Jobs controller
 */
class JobsController extends Controller
{
    public $defaultAction = 'index';

    public $id;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'publication':
                 $options[] = 'id';
                break;
        }
        return $options;
    }
    /**
     * cockpit/jobs command
     */
    public function actionIndex(): int
    {
        return ExitCode::OK;
    }

    /**
     * fetch all publications from Cockpit
     */
    public function actionPublications(): int
    {
        try {
            $publications = Cockpit::$plugin->getApi()->getPublications()['results'] ?? collect([]);

            return ExitCode::OK;

        } catch (\Exception $e) {
            Craft::error($e->getMessage());
        }

        return ExitCode::DATAERR;
    }

    /**
     * fetch publication by id from Cockpit
     */
    public function actionPublication(): int
    {
        try {
            // Get the ID from command line options
            $id = $this->id;

            if (!$id) {
                Craft::error('Publication ID (as --id=x) is required');
                Console::stderr('Error on fetching publication: Publication ID is required' . PHP_EOL);
                return ExitCode::DATAERR;
            }

            Console::stdout('Start publication fetch ' . $id . PHP_EOL, Console::FG_CYAN);

            // Get publication by ID
            $publication = Cockpit::$plugin->getApi()->getPublicationById($id);

            if (!$publication) {
                Craft::error('Publication not found');
                Console::stderr('   > Error on fetching publication: Publication not found' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            }

            Console::stdout('   > Publication ' . $publication->get('title') . ' found ' . PHP_EOL, Console::FG_GREEN);

            $jobRequestId = $publication->get('jobRequest')['id'] ?? null;

            if (!$jobRequestId) {
                Craft::error('Job request ID not found');
                Console::stderr('   > Error on fetching publication: Job request ID not found' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            }

            Console::stdout('   > Job request for ' . $publication['title'] . ' found ' . PHP_EOL, Console::FG_GREEN);

            $jobRequest = Cockpit::$plugin->getApi()->getJobRequestById($jobRequestId);
            $publication->get('jobRequest')['data'] = $jobRequest;

            Cockpit::$plugin->getJobs()->createJob($publication);

            return ExitCode::OK;

        } catch (\Exception $e) {
            Console::stderr('Error on fetching publication: '.$e->getMessage() . PHP_EOL);
            Craft::error($e->getMessage());
        }

        return ExitCode::DATAERR;
    }
}
