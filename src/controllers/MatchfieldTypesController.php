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
use craftpulse\cockpit\models\MatchfieldType;

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

    /**
     * @param int|null $matchfieldTypeId
     * @param MatchfieldType|null $matchfieldType
     * @throws HttpException
     */
    public function actionEditMatchfieldType(int $matchfieldTypeId = null, MatchfieldType $matchfielType = null): Response
    {
        $variables['brandNewMatchfieldType'] = false;
        $variables['fullPageForm'] = true;

        if (empty($variables['matchfieldType'])) {
            if (!empty($variables['matchfieldTypeId'])) {
                $matchfieldTypeId = $variables['matchfieldTypeId'];
                $variables['matchfieldType'] = Cockpit::$plugin->getMatchfieldTypes()->getMatchfieldTypeById($matchfieldTypeId);

                if (!$variables['matchfieldType']) {
                    throw new HttpException(404);
                }
            } else {
                $variables['matchfieldType'] = new MatchfieldType();
                $variables['brandNewMatchfieldType'] = true;
            }
        }

        if (!empty($variables['matchfieldTypeId'])) {
            $variables['title'] = $variables['matchfieldType']->name;
        } else {
            $variables['title'] = Craft::t('cockpit', 'Create a Matchfield Type');
        }

        $tabs = [
            'matchfieldTypeSettings' => [
                'label' => Craft::t('cockpit', 'Settings'),
                'url' => '#matchfield-type-settings',
            ],
        ];

        $variables['tabs'] = $tabs;
        $variables['selectedTab'] = 'matchfieldTypeSettings';

        $variables['readOnly'] = $this->isReadOnlyScreen();

        return $this->renderTemplate('cockpit/settings/matchfieldtypes/_edit', $variables);
    }

    /**
     * @throws HttpException
     * @throws Throwable
     * @throws BadRequestHttpException
     */
    public function actionSaveMatchfieldType(): void
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser->can('cockpit:settings-matchfieldtypes')) {
            throw new HttpException(403, Craft::t('cockpit', 'This action is not allowed for the current user.'));
        }

        $this->requirePostRequest();
        $matchfieldTypeId = $this->request->getBodyParam('matchfieldTypeId');

        if ($matchfieldTypeId) {
            $matchfieldType = Cockpit::$plugin->getMatchfieldTypes()->getMatchfieldTypeById($matchfieldTypeId);

            if (!$matchfieldType) {
                throw new BadRequestHttpException("Invalid section ID: $matchfieldTypeId");
            }
        } else {
            $matchfieldType = new MatchfieldType();
        }

        // Shared attributes
        $matchfieldType->id = $this->request->getBodyParam('matchfieldTypeId');
        $matchfieldType->name = $this->request->getBodyParam('name');
        $matchfieldType->handle = $this->request->getBodyParam('handle');
        $matchfieldType->cockpitId = $this->request->getBodyParam('cockpitId');

        // Save it
        if (Cockpit::$plugin->getMatchfieldTypes()->saveMatchfieldType($matchfieldType)) {
            $this->setSuccessFlash(Craft::t('cockpit', 'Matchfield type saved.'));
            $this->redirectToPostedUrl($matchfieldType);
        } else {
            $this->setFailFlash(Craft::t('cockpit', 'Couldn’t save matchfield type.'));
        }

        // Send the matchfieldType back to the template
        Craft::$app->getUrlManager()->setRouteParams([
            'matchfieldtype' => $matchfieldType,
        ]);
    }

    /**
     * @throws Throwable
     * @throws BadRequestHttpException
     */
    public function actionDeleteMatchfieldType(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $matchfieldTypeId = $this->request->getRequiredBodyParam('id');

        Cockpit::$plugin->getMatchfieldTypes()->deleteMatchfieldTypeById($matchfieldTypeId);
        return $this->asSuccess();
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
