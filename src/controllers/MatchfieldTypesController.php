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
        // Ensure they have permission to edit the plugin settings
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('cockpit:settings-matchfields')) {
            throw new ForbiddenHttpException('You do not have permission to view the Matchfield settings.');
        }

        $general = Craft::$app->getConfig()->getGeneral();
        if (!$general->allowAdminChanges) {
            throw new ForbiddenHttpException('Unable to edit matchfield settings because admin changes are disabled in this environment.');
        }

        $matchfieldTypes = Cockpit::$plugin->getMatchfieldTypes()->getAllMatchfieldTypes();

        // View the matchfield settings
        $pluginName = 'Cockpit';
        $variables = [];
        $templateTitle = Craft::t('cockpit', 'Matchfields overview');
        $variables['title'] = $templateTitle;
        $variables['readOnly'] = $this->isReadOnlyScreen();
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['crumbs'] = [
            [
                'label' => $pluginName,
                'url' => UrlHelper::cpUrl('cockpit'),
            ],
            [
                'label' => $templateTitle,
                'url' => UrlHelper::cpUrl('cockpit/plugin'),
            ],
        ];
        $variables['matchfieldTypes'] = $matchfieldTypes;

        return $this->renderTemplate('cockpit/settings/matchfieldtypes/index', $variables);
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
