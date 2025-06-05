<?php

namespace craftpulse\cockpit\services;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\records\Candidate;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * Candidate Service service
 */
class CandidateService extends Component
{
    public function getCandidateByUserId(int $userId): ?Candidate
    {
        return Candidate::find()->where(['userId' => $userId])->one();
    }

    public function upsertCandidate(int $userId, string $cockpitId): bool
    {
        $candidate = $this->getCandidateByUserId($userId);

        if (!$candidate) {
            $candidate = new Candidate();
        }

        $candidate->userId = $userId;
        $candidate->cockpitId = $cockpitId;


        return $candidate->save();
    }

    public function registerUser(string $email, Collection $data): ?User
    {
        $user = new User();
        $user->email = $email;
        $user->username = $email;
        $user->fullName = $data['name'] ?? null;
        $user->firstName = $data['firstName'] ?? null;
        $user->lastName = $data['lastName'] ?? null;

// @TODO: create a layer in between for mapping data between field layout and publication
        $mappings = collect([
            'phone' => $data['primaryMobilePhoneNumber']['number'] ?? null,
        ]);

        // loop through mappings to add the Cockpit data
        foreach($user->getFieldValues() as $field => $value) {
            $mapping = $mappings->get($field) ?? $value;
            $user->setFieldValue($field, $mapping);
        }

        if (!$user->id) {
            $user->pending = true;
        }

        if (Craft::$app->getElements()->saveElement($user)) {
            //@TODO: provide setting to assign user to group
            $userGroup = Craft::$app->userGroups->getGroupByHandle('applicants');

            if (!Craft::$app->getUsers()->assignUserToGroups($user->id, [$userGroup->id])) {
                throw new Exception('Could not assign user to the group');
            }

            if ($user->getStatus() == User::STATUS_PENDING) {
                Craft::$app->getUsers()->sendActivationEmail($user);
            }

            $this->upsertCandidate($user->id, $data['id']);

            return $user;
        }

        return null;
    }
}
