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
use craft\helpers\UrlHelper;
use craft\web\Controller;

use craftpulse\cockpit\Cockpit;
use JsonException;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class PostcodesController extends Controller
{
    /**
     * @var string
     */
    public $defaultAction = 'postcodeMapping';

    /**
     * @var array<int|string>|bool|int
     */
    protected array|int|bool $allowAnonymous = parent::ALLOW_ANONYMOUS_NEVER;

    // Public Methods
    // =========================================================================

    /**
     * @throws Throwable
     * @throws ForbiddenHttpException
     */
    public function actionPostcodeMapping(): Response
    {
        // Ensure they have permission to edit the plugin settings
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('cockpit:postcode-mapping')) {
            throw new ForbiddenHttpException('You do not have permission to edit the Cockpit postcode mappings.');
        }

        $variables = [];

        $pluginName = 'Cockpit';
        $templateTitle = Craft::t('cockpit', 'Postcode Mapping');

//        $variables['controllerHandle'] = 'postcodes';
        $variables['fullPageForm'] = true;
        $variables['pluginName'] = $pluginName;
        $variables['title'] = $templateTitle;
        $variables['crumbs'] = [
            [
                'label' => $pluginName,
                'url' => UrlHelper::cpUrl('cockpit'),
            ],
            [
                'label' => $templateTitle,
                'url' => UrlHelper::cpUrl('cockpit/postcodes'),
            ],
        ];
        $variables['docTitle'] = "{$pluginName} - {$templateTitle}";
        $variables['selectedSubnavItem'] = 'postcodes';
        $variables['postcodeMapping'] = Cockpit::$plugin->getPostcodes()->getPostcodeMapping();

        return $this->renderTemplate('cockpit/postcodes/_index', $variables);
    }

    /**
     * Save the mappings
     *
     * @return null|Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws MethodNotAllowedHttpException
     * @throws JsonException
     * @throws Throwable
     */
    public function actionSaveMapping(): ?Response
    {
        // Ensure they have permission to edit the plugin settings
        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('cockpit:postcode-mapping')) {
            throw new ForbiddenHttpException('You do not have permission to edit the Cockpit postcode mappings.');
        }

        $this->requirePostRequest();
        $postcodeMapping['postcodeMapping'] = Craft::$app->getRequest()->getRequiredParam('postcodeMapping');

        Cockpit::$plugin->getPostcodes()->saveMapping($postcodeMapping);

        return $this->redirectToPostedUrl();
    }
}
