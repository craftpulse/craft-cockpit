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
        Console::stdout('Start publications fetch '. PHP_EOL, Console::FG_CYAN);

        try {
            $publications = Cockpit::$plugin->getApi()->getPublications()['results'] ?? collect([]);

            if ($publications->isEmpty()) {
                Craft::error('No publications found');
                Console::stderr('No publications found' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            } else {
                $count = $publications->count();
                Console::stdout("   > ${count} Publications found". PHP_EOL);

                foreach($publications as $publication) {
                    $title = $publication->get('name');
                    $id = $publication->get('id');
                    Console::stdout("       > ${title} (${id})". PHP_EOL);
                }
            }

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
                Console::stderr('Error on fetching publication: Publication ID is required' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            }

            Console::stdout('Start publication fetch ' . $id . PHP_EOL, Console::FG_CYAN);

            if (!Cockpit::$plugin->getJobs()->fetchPublicationById($id)) {
                return ExitCode::DATAERR;
            }

            return ExitCode::OK;

        } catch (\Exception $e) {
            Console::stderr('Error on fetching publication: '.$e->getMessage() . PHP_EOL);
            Craft::error($e->getMessage());
        }

        return ExitCode::DATAERR;
    }
}
