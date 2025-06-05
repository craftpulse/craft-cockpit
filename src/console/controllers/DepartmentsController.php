<?php

namespace craftpulse\cockpit\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\Queue;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\jobs\BatchFetchDepartmentsJob;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

/**
 * Departments controller
 */
class DepartmentsController extends Controller
{
    public $defaultAction = 'departments';
    public $departmentId;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'delete-department':
            case 'department':
                $options[] = 'departmentId';
                break;
        }
        return $options;
    }

    public function optionAliases(): array
    {
        return [
            'department-id' => 'departmentId',
        ];
    }

    /**
     * fetch all publications from Cockpit
     */
    public function actionDepartments(): int
    {
        Console::stdout('Queueing batched departments fetch...'.PHP_EOL, Console::FG_CYAN);

        try {
            Queue::push(
                job: new BatchFetchDepartmentsJob(),
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
    public function actionDepartment(): int
    {
        try {
            // Get the ID from command line options
            $id = $this->departmentId;

            Console::stdout('Start department fetch ' . $id . PHP_EOL, BaseConsole::FG_CYAN);

            if (!$id) {
                Craft::error('Department ID (as --id=x) is required');
                Console::stderr('   > Error on fetching department: Department ID is required' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            }

            if (!Cockpit::$plugin->getDepartments()->fetchDepartmentByCockpitId($id)) {
                return ExitCode::DATAERR;
            }

            return ExitCode::OK;

        } catch (\Exception $e) {
            Console::stderr('   > Error on fetching department: '.$e->getMessage() . PHP_EOL);
            Craft::error($e->getMessage());
        }

        return ExitCode::DATAERR;
    }

    public function actionDeleteDepartment(): int
    {
        try {
            // Get the ID from command line options
            $id = $this->departmentId;

            Console::stdout('Start department deletion ' . $id . PHP_EOL, Console::FG_CYAN);

            if (!$id) {
                Craft::error('Department ID (as --id=x) is required');
                Console::stderr('   > Error on deleting department: Department ID is required' . PHP_EOL, Console::FG_RED);
                return ExitCode::DATAERR;
            }

            if (!Cockpit::$plugin->getDepartments()->deleteDepartmentByCockpitId($id)) {
                return ExitCode::DATAERR;
            }

            return ExitCode::OK;

        } catch (\Exception $e) {
            Console::stderr('   > Error on deleting departments: '.$e->getMessage() . PHP_EOL);
            Craft::error($e->getMessage());
        }

        return ExitCode::DATAERR;
    }
}
