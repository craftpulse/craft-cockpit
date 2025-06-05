<?php

namespace craftpulse\cockpit\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\Queue;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\jobs\BatchFetchMatchFieldsJob;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\InvalidConfigException;
use yii\console\ExitCode;

/**
 * Departments controller
 */
class MatchFieldsController extends Controller
{
    public $defaultAction = 'match-fields-by-type';
    public string $matchFieldId;
    public string $matchFieldType;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'match-fields':
                $options[] = 'matchFieldType';
                break;
        }
        return $options;
    }

    public function optionAliases(): array
    {
        return [
            'match-field-type' => 'matchFieldType',
            'type' => 'matchFieldType',
        ];
    }

    /**
     * fetch all match fields by type from Cockpit
     * @throws InvalidConfigException
     * @throws GuzzleException
     */
    public function actionMatchFieldsByType(): int
    {
        $type = $this->matchFieldType;

        // @TODO - Add the type in the display information
        Console::stdout("Queueing batched match fields fetch for type {$type}".PHP_EOL, Console::FG_CYAN);

        try {
            // @TODO make ttr custom
            // @TODO make priority custom
            Queue::push(
                job: new BatchFetchMatchFieldsJob([
                    'matchFieldType' => $type,
                ]),
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
}
