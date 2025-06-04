<?php

namespace craftpulse\cockpit\console\controllers;

use Craft;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Contacts controller
 */
class ContactsController extends Controller
{
    public $defaultAction = 'index';

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        switch ($actionID) {
            case 'index':
                // $options[] = '...';
                break;
        }
        return $options;
    }

    /**
     * cockpit/contacts command
     */
    public function actionIndex(): int
    {
        // ...
        return ExitCode::OK;
    }
}
