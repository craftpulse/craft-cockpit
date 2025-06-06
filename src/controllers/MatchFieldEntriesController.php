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
use craft\base\Element;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\MatchFieldEntry;

use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\InvalidRouteException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Class MatchFieldEntriesController
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class MatchFieldEntriesController extends Controller
{
    /**
     * @param string|null $matchFieldTypeHandle
     * @return Response
     */
    public function actionMatchFieldEntryIndex(?string $matchFieldTypeHandle = null): Response
    {
        return $this->renderTemplate('cockpit/match-field-entries/_index', [
            'matchFieldTypeHandle' => $matchFieldTypeHandle,
        ]);
    }

    /**
     * @throws Throwable
     * @throws InvalidRouteException
     * @throws InvalidConfigException
     * @throws Exception
     * @throws BadRequestHttpException
     */
    public function actionCreate(?string $matchFieldType = null): ?Response
    {
        if ($matchFieldType) {
            $matchFieldTypeHandle = $matchFieldType;
        } else {
            $matchFieldTypeHandle = $this->request->getRequiredBodyParam('matchFieldType');
        }

        $matchFieldType = Cockpit::$plugin->getMatchFields()->getMatchFieldByHandle($matchFieldTypeHandle);
        if (!$matchFieldType) {
            throw new BadRequestHttpException("Invalid match field type handle: $matchFieldTypeHandle");
        }

        $sitesService = Craft::$app->getSites();
        $siteId = $this->request->getBodyParam('siteId');

        if ($siteId) {
            $site = $sitesService->getSiteById($siteId);
            if (!$site) {
                throw new BadRequestHttpException("Invalid site ID: $siteId");
            }
        } else {
            $site = Cp::requestedSite();
            if (!$site) {
                throw new ForbiddenHttpException('User not authorized to edit content in any sites.');
            }
        }

        $editableSiteIds = $sitesService->getEditableSiteIds();
        if (!in_array($site->id, $editableSiteIds)) {
            // Go with the first one
            $site = $sitesService->getSiteById($editableSiteIds[0]);
        }

        $user = static::currentUser();

        // Create & populate the draft
        $matchFieldEntry = Craft::createObject(MatchFieldEntry::class);
        $matchFieldEntry->siteId = $site->id;
        $matchFieldEntry->matchFieldId = $matchFieldType->id;
        $matchFieldEntry->enabled = true;

        // Structure parent
        if ((int)$matchFieldType->maxLevels !== 1) {
            // Set the initially selected parent
            $matchFieldEntry->setParentId($this->request->getParam('parentId'));
        }

        // Make sure the user is allowed to create this entry
        if (!Craft::$app->getElements()->canSave($matchFieldEntry, $user)) {
            throw new ForbiddenHttpException('User not authorized to create this product.');
        }

        // Title & slug
        $matchFieldEntry->title = $this->request->getParam('title');
        $matchFieldEntry->slug = $this->request->getParam('slug');
        if ($matchFieldEntry->title && !$matchFieldEntry->slug) {
            $matchFieldEntry->slug = ElementHelper::generateSlug($matchFieldEntry->title, null, $site->language);
        }
        if (!$matchFieldEntry->slug) {
            $matchFieldEntry->slug = ElementHelper::tempSlug();
        }

        // Pause time so postDate will definitely be equal to dateCreated, if not explicitly defined
        DateTimeHelper::pause();

        // Post & expiry dates
        if (($postDate = $this->request->getParam('postDate')) !== null) {
            $matchFieldEntry->postDate = DateTimeHelper::toDateTime($postDate);
        } else {
            $matchFieldEntry->postDate = DateTimeHelper::now();
        }

        if (($expiryDate = $this->request->getParam('expiryDate')) !== null) {
            $matchFieldEntry->expiryDate = DateTimeHelper::toDateTime($expiryDate);
        }

        // Custom fields
        foreach ($matchFieldEntry->getFieldLayout()->getCustomFields() as $field) {
            if (($value = $this->request->getParam($field->handle)) !== null) {
                $matchFieldEntry->setFieldValue($field->handle, $value);
            }
        }

        // Save it
        $matchFieldEntry->setScenario(Element::SCENARIO_ESSENTIALS);
        $success = Craft::$app->getDrafts()->saveElementAsDraft($matchFieldEntry, $user->id, markAsSaved: false);

        // Resume time
        DateTimeHelper::resume();

        if (!$success) {
            return $this->asModelFailure($matchFieldEntry, Craft::t('app', 'Couldn’t create {type}.', [
                'type' => MatchFieldEntry::lowerDisplayName(),
            ]), 'matchFieldEntry');
        }

        // Set its position in the structure if a before/after param was passed

        if ($nextId = $this->request->getParam('before')) {
            $nextEntry = Cockpit::$plugin->getMatchFieldEntries()->getMatchFieldEntryById($nextId, $site->id, [
                'structureId' => $matchFieldType->structureId,
            ]);
            Craft::$app->getStructures()->moveBefore($matchFieldType->structureId, $matchFieldEntry, $nextEntry);
        } elseif ($prevId = $this->request->getParam('after')) {
            $prevEntry = Cockpit::$plugin->getMatchFieldEntries()->getMatchFieldEntryById($prevId, $site->id, [
                'structureId' => $matchFieldType->structureId,
            ]);
            Craft::$app->getStructures()->moveAfter($matchFieldType->structureId, $matchFieldEntry, $prevEntry);
        }

        $editUrl = $matchFieldEntry->getCpEditUrl();

        $response = $this->asModelSuccess($matchFieldEntry, Craft::t('app', '{type} created.', [
            'type' => MatchFieldEntry::displayName(),
        ]), 'matchFieldEntry', array_filter([
            'cpEditUrl' => $this->request->getIsCpRequest() ? $editUrl : null,
        ]));

        if (!$this->request->getAcceptsJson()) {
            $response->redirect(UrlHelper::urlWithParams($editUrl, [
                'fresh' => 1,
            ]));
        }

        return $response;
    }
}
