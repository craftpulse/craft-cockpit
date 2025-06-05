<?php

namespace craftpulse\cockpit\controllers;

use Craft;
use craft\commerce\controllers\BaseFrontEndController;
use craft\controllers\EditUserTrait;
use craft\helpers\Cp;
use craft\helpers\Html;
use craft\web\CpScreenResponseBehavior;
use craftpulse\cockpit\Cockpit;
use yii\web\Response;

/**
 * User controller
 */
class CandidateController extends BaseFrontEndController
{
    use EditUserTrait;

    public const SCREEN_COCKPIT = 'cockpit';

    /**
     * cockpit/user action
     */
    public function actionIndex(?int $userId = null): Response
    {
        $user = $this->editedUser($userId);

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, self::SCREEN_COCKPIT);

        // Set the form action to your custom save route
        $response->action('cockpit/candidate/save');

        $candidate = Cockpit::$plugin->getCandidates()->getCandidateByUserId($userId);

        $input = Cp::textHtml([
            'title' => Craft::t('cockpit', 'Cockpit Candidate ID'),
            'label' => Craft::t('cockpit', 'Cockpit Candidate ID'),
            'name' => 'cockpitId',
            'id' => 'cockpitId',
            'type' => 'text',
            'value' => $candidate->cockpitId ?? null,
        ]);

        $content = Cp::fieldHtml($input, [
            'label' => Craft::t('cockpit', 'Cockpit Candidate ID'),
            'instructions' => Craft::t('cockpit', 'Enter the candidate ID associated with this user.'),
            'id' => 'cockpitCandidateId',
        ]);

        $content .= '<input type="hidden" name="userId" value="'.$userId.'"/>';

        return $response->contentHtml($content);
    }

    /**
     * cockpit/user action
     */
    public function actionCurrent(): Response
    {
        $user = Craft::$app->getUser()->getIdentity();
        $userId = $user->id;

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, self::SCREEN_COCKPIT);

        // Set the form action to your custom save route
        $response->action('cockpit/candidate/save');

        $candidate = Cockpit::$plugin->getCandidates()->getCandidateByUserId($userId);

        $input = Cp::textHtml([
            'title' => Craft::t('cockpit', 'Cockpit Candidate ID'),
            'label' => Craft::t('cockpit', 'Cockpit Candidate ID'),
            'name' => 'cockpitId',
            'id' => 'cockpitId',
            'type' => 'text',
            'value' => $candidate->cockpitId ?? null,
        ]);

        $content = Cp::fieldHtml($input, [
            'label' => Craft::t('cockpit', 'Cockpit Candidate ID'),
            'instructions' => Craft::t('cockpit', 'Enter the candidate ID associated with this user.'),
            'id' => 'cockpitCandidateId',
        ]);

        $content .= '<input type="hidden" name="userId" value="'.$userId.'"/>';

        return $response->contentHtml($content);
    }

    public function actionSave(): Response
    {
        $request = Craft::$app->getRequest();

        $userId = $request->getBodyParam('userId');
        $cockpitId = $request->getBodyParam('cockpitId');

        $success = Cockpit::$plugin->getCandidates()->upsertCandidate($userId, $cockpitId);

        if ($success) {
            Craft::$app->getSession()->setNotice(Craft::t('cockpit', 'Candidate ID saved successfully.'));
        } else {
            Craft::$app->getSession()->setError(Craft::t('cockpit', 'Failed to save candidate ID.'));
        }

        return $this->redirectToPostedUrl();
    }

}
