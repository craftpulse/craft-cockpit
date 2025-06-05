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
use craft\enums\PropagationMethod;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\MatchFieldEntry;
use craftpulse\cockpit\errors\MatchFieldNotFoundException;
use craftpulse\cockpit\jobs\BatchFetchMatchFieldsJob;
use craftpulse\cockpit\models\MatchField as MatchFieldModel;
use craftpulse\cockpit\models\MatchField_SiteSettings as MatchField_SiteSettingsModel;
use craftpulse\cockpit\models\SettingsModel;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Throwable;
use yii\base\ExitException;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class MatchFieldsController extends Controller
{
    private bool $readOnly;

    /**
     * @var SettingsModel
     */
    private SettingsModel $settings;

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();
        $this->settings = Cockpit::$plugin->settings;
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $viewActions = ['index', 'edit-matchfield', 'table-data'];
        if (in_array($action->id, $viewActions)) {
            // Some actions require admin but not allowAdminChanges
            $this->requireAdmin(false);
        } else {
            // All other actions require an admin & allowAdminChanges
            $this->requireAdmin();
        }

        $this->readOnly = !Craft::$app->getConfig()->getGeneral()->allowAdminChanges;

        return true;
    }

    /**
     * Matchfield index.
     *
     * @param array $variables
     * @return Response The rendering result
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function actionMatchFieldIndex(): Response
    {
        // Ensure they have permission to edit the plugin settings
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('cockpit:settings-matchfields')) {
            throw new ForbiddenHttpException('You do not have permission to view the Match field settings.');
        }

        $general = Craft::$app->getConfig()->getGeneral();
        if (!$general->allowAdminChanges) {
            throw new ForbiddenHttpException('Unable to edit match field settings because admin changes are disabled in this environment.');
        }

        $matchFields = Cockpit::$plugin->getMatchFields()->getAllMatchFields();

        // View the match field settings
        $pluginName = 'Cockpit';
        $variables = [];
        $templateTitle = Craft::t('cockpit', 'Match fields overview');
        $variables['title'] = $templateTitle;
        $variables['readOnly'] = $this->readOnly;
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['fullPageForm'] = true;
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
        $variables['matchFields'] = $matchFields;

        return $this->renderTemplate('cockpit/settings/matchfields/index', $variables);
    }

    /**
     * Edit a match field.
     *
     * @param int|null $matchFieldId The match fields’ ID, if any.
     * @param MatchFieldModel|null $matchField
     * @return Response
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException if the requested match field cannot be found
     * @throws InvalidConfigException|ExitException
     * @throws GuzzleException
     */
    public function actionEditMatchField(?int $matchFieldId = null, ?MatchfieldModel $matchField = null): Response
    {
        if ($matchFieldId === null && $this->readOnly) {
            throw new ForbiddenHttpException('Administrative changes are disallowed in this environment.');
        }

        $matchFieldService = Cockpit::$plugin->getMatchfields();

        $variables = [
            'matchFieldId' => $matchFieldId,
            'brandNewMatchField' => false,
        ];

        if ($matchFieldId !== null) {
            if ($matchField === null) {
                $matchField = $matchFieldService->getMatchfieldById($matchFieldId);

                if (!$matchField) {
                    throw new NotFoundHttpException('match field not found');
                }
            }

            $variables['title'] = trim($matchField->name) ?: Craft::t('cockpit', 'Edit match field');
        } else {
            if ($matchField === null) {
                $matchField = new MatchfieldModel();
                $variables['brandNewMatchField'] = true;
            }

            $variables['title'] = Craft::t('cockpit', 'Create a new match field');
        }

        $typeOptions = null;

        // This needs to fetch data from the API - need to safeguard this!
        if ($this->settings->apiKey && $this->settings->apiUrl) {
            $typeOptions = Cockpit::$plugin->getApi()->getMatchFieldTypes();

            if (!$matchField->type) {
                $matchField->type = $typeOptions->keys()->first();
            }
        }

        $variables['matchField'] = $matchField;
        $variables['typeOptions'] = $typeOptions;
        $variables['readOnly'] = $this->readOnly;

        return $this->renderTemplate('cockpit/settings/matchfields/_edit.twig', $variables);
    }

    /**
     * Saves a match field.
     *
     * @return Response|null
     * @throws BadRequestHttpException if any invalid site IDs are specified in the request
     * @throws InvalidConfigException
     * @throws Throwable
     * @throws MatchFieldNotFoundException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSaveMatchField(): ?Response
    {
        $this->requirePostRequest();

        $matchFieldService = Cockpit::$plugin->getMatchfields();
        $matchFieldId = $this->request->getBodyParam('matchFieldId');
        if ($matchFieldId) {
            $matchField = $matchFieldService->getMatchFieldById($matchFieldId);
            if (!$matchField) {
                throw new BadRequestHttpException("Invalid match field ID: $matchFieldId");
            }
        } else {
            $matchField = new MatchfieldModel();
        }

        // Main match field settings
        $matchField->name = $this->request->getBodyParam('name');
        $matchField->handle = $this->request->getBodyParam('handle');
        $matchField->type = $this->request->getBodyParam('type');
        $matchField->enableVersioning = $this->request->getBodyParam('enableVersioning', true);
        $matchField->propagationMethod = PropagationMethod::tryFrom($this->request->getBodyParam('propagationMethod') ?? '')
            ?? PropagationMethod::All;
        $matchField->previewTargets = $this->request->getBodyParam('previewTargets') ?: [];
        $matchField->maxLevels = $this->request->getBodyParam('maxLevels') ?: null;
        $matchField->defaultPlacement = $this->request->getBodyParam('defaultPlacement') ?? $matchField->defaultPlacement;

        // Site-specific settings
        $allSiteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $postedSettings = $this->request->getBodyParam('sites.' . $site->handle);

            // Skip disabled sites if this is a multi-site install
            if (Craft::$app->getIsMultiSite() && empty($postedSettings['enabled'])) {
                continue;
            }

            $siteSettings = new MatchField_SiteSettingsModel();
            $siteSettings->siteId = $site->id;
            $siteSettings->uriFormat = $postedSettings['uriFormat'] ?? null;
            $siteSettings->enabledByDefault = (bool)$postedSettings['enabledByDefault'];

            if ($siteSettings->hasUrls = (bool)$siteSettings->uriFormat) {
                $siteSettings->template = $postedSettings['template'] ?? null;
            }

            $allSiteSettings[$site->id] = $siteSettings;
        }

        $matchField->setSiteSettings($allSiteSettings);

        $fieldLayout = Craft::$app->getFields()->assembleLayoutFromPost();
        $fieldLayout->type = MatchFieldEntry::class;
        $matchField->setFieldLayout($fieldLayout);

        // Save it
        if (!$matchFieldService->saveMatchField($matchField)) {
            $this->setFailFlash(Craft::t('cockpit', 'Couldn’t save match field.'));

            // Send the match field back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'matchField' => $matchField,
            ]);

            return null;
        }

        // Sync it if the sync is enabled
        if ($matchField->syncMatchFields) {
            $this->actionMatchFieldsByType($matchField->type, $matchField->handle);
        }

        $this->setSuccessFlash(Craft::t('app', 'Match field saved.'));
        return $this->redirectToPostedUrl($matchField);
    }

    /**
     * Deletes a Match field.
     *
     * @return Response
     * @throws MethodNotAllowedHttpException
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionDeleteMatchField(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $matchFieldId = $this->request->getRequiredBodyParam('id');

        Cockpit::$plugin->getMatchFields()->deleteMatchfieldById($matchFieldId);

        return $this->asSuccess();
    }

    /**
     * Returns data formatted for AdminTable vue component
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws InvalidConfigException
     */
    public function actionTableData(): Response
    {
        $this->requireAcceptsJson();

        $matchFieldService = Cockpit::$plugin->getMatchFields();

        $page = (int)$this->request->getParam('page', 1);
        $limit = (int)$this->request->getParam('per_page', 100);
        $searchTerm = $this->request->getParam('search');
        $orderBy = match ($this->request->getParam('sort.0.field')) {
            '__slot:handle' => 'handle',
            'type' => 'type',
            default => 'name',
        };
        $sortDir = match ($this->request->getParam('sort.0.direction')) {
            'desc' => SORT_DESC,
            default => SORT_ASC,
        };

        [$pagination, $tableData] = $matchFieldService->getMatchFieldTableData($page, $limit, $searchTerm, $orderBy, $sortDir);

        return $this->asSuccess(data: [
            'pagination' => $pagination,
            'data' => $tableData,
        ]);
    }

    /**
     * fetch all match fields by type from Cockpit
     * @throws InvalidConfigException
     */
    public function actionMatchFieldsByType(string $matchFieldType, string $handle): Response
    {
        $matchField = Cockpit::$plugin->getMatchFields()->getMatchFieldByHandle($handle);

        try {
            // @TODO make ttr custom
            // @TODO make priority custom
            Queue::push(
                job: new BatchFetchMatchFieldsJob([
                    'type' => $matchFieldType,
                    'matchFieldId' => $matchField->id,
                ]),
                priority: 2,
                ttr: 1000,
                queue: Cockpit::$plugin->queue
            );

            return $this->asSuccess();
        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);

            return $this->asFailure();
        }
    }
}
