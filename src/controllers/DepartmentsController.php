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
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\queue\jobs\ResaveElements;
use craft\web\Controller;
use craft\web\UrlManager;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\Department;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Departments Settings controller
 */
class DepartmentsController extends Controller
{
    /**
     * @var string
     */
    public $defaultAction = 'edit';

    /**
     * @var array<int|string>|bool|int
     */
    protected array|int|bool $allowAnonymous = parent::ALLOW_ANONYMOUS_NEVER;

    /**
     * @return Response|null
     */
    public function actionEditSettings(): ?Response
    {
        // Ensure they have permission to edit the plugin settings
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('cockpit:settings')) {
            throw new ForbiddenHttpException('You do not have permission to edit the Cockpit settings.');
        }
        $general = Craft::$app->getConfig()->getGeneral();
        if (!$general->allowAdminChanges) {
            throw new ForbiddenHttpException('Unable to edit Cockpit plugin settings because admin changes are disabled in this environment.');
        }

        // Edit the plugin settings
        $variables = [];
        $pluginName = 'Cockpit';
        $templateTitle = Craft::t('cockpit', 'Department settings');

        $variables['fullPageForm'] = true;
        $variables['pluginName'] = $pluginName;
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
        $variables['settings'] = Cockpit::$plugin->settings;

        return $this->renderTemplate('cockpit/settings/departments/_edit', $variables);
    }

    /**
     * Saves the plugin settings
     * @return Response|null
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     * @throws NotFoundHttpException
     * @throws Throwable
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSaveSettings(): ?Response
    {
        // Ensure they have permission to edit the plugin settings
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('cockpit:settings')) {
            throw new ForbiddenHttpException('You do not have permission to edit the Cockpit settings.');
        }
        $general = Craft::$app->getConfig()->getGeneral();
        if (!$general->allowAdminChanges) {
            throw new ForbiddenHttpException('Unable to edit Cockpit plugin settings because admin changes are disabled in this environment.');
        }

        // Save the plugin settings
        $this->requirePostRequest();
        $pluginHandle = Craft::$app->getRequest()->getRequiredBodyParam('pluginHandle');
        $plugin = Craft::$app->getPlugins()->getPlugin($pluginHandle);
        $settings = Craft::$app->getRequest()->getBodyParam('settings', []);

        if ($plugin === null) {
            throw new NotFoundHttpException('Plugin not found');
        }

        // provide settings
        $pluginSettings = $plugin->getSettings();

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $this->request->getBodyParam('sites.' . $site->handle);

            // Skip disabled sites if this is a multi-site install
            if (Craft::$app->getIsMultiSite() && empty($postedSettings['enabled'])) {
                continue;
            }

            $siteSettings = [];
            $siteSettings['siteId'] = $site->id;
            $siteSettings['uriFormat'] = $postedSettings['uriFormat'] ?? null;
            $siteSettings['enabledByDefault'] = (bool)$postedSettings['enabledByDefault'];

            if ($siteSettings['hasUrls'] = (bool)$siteSettings['uriFormat']) {
                $siteSettings['template'] = $postedSettings['template'] ?? null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $pluginSettings['departmentSiteSettings'] = $allSiteSettings;

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            Craft::$app->getSession()->setError(Craft::t('app', "Couldn't save plugin settings."));

            // Send the redirect back to the template
            /** @var UrlManager $urlManager */
            $urlManager = Craft::$app->getUrlManager();
            $urlManager->setRouteParams([
                'plugin' => $plugin,
            ]);

            return null;
        }

        // Resave all products if the URI format changed
        Craft::$app->getQueue()->push(new ResaveElements([
            'elementType' => Department::class,
            'criteria' => [
                'siteId' => '*',
                'unique' => true,
                'status' => null,
            ],
        ]));

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = Department::class;

        $projectConfig = Craft::$app->getProjectConfig();
        $uid = StringHelper::UUID();
        $fieldLayoutConfig = $fieldLayout->getConfig();
        $projectConfig->set(Cockpit::CONFIG_DEPARTMENT_FIELD_LAYOUT_KEY, [$uid => $fieldLayoutConfig], 'Save the department field layout');
        $pluginSettings->setDepartmentFieldLayout($fieldLayout);

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Plugin settings saved.'));

        return $this->redirectToPostedUrl();
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
