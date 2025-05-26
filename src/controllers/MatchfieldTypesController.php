<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\controllers;

use Craft;
use craft\errors\MissingComponentException;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\UrlManager;

use craftpulse\cockpit\Cockpit;

use Throwable;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class MatchfieldTypesController extends Controller
{
    // Public Methods
    // =========================================================================
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $this->requireAdmin();

        return parent::beforeAction($action);
    }

    public function actionMatchfieldTypeIndex(): Response
    {
        $matchfieldTypes = Cockpit::$plugin->getMatchfieldTypes()->getAllMatchfieldTypes();
        return $this->renderTemplate('cockpit/settings/matchfieldtypes/index',[
            'matchfieldTypes' => $matchfieldTypes,
            'readOnly' => $this->isReadOnlyScreen(),
        ]);
    }

    // Protected Methods
    // =========================================================================
    /**
     * @return bool
     */
    protected function isReadOnlyScreen(): bool
    {
        return !Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
    }
}
