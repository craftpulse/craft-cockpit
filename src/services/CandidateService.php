<?php

namespace craftpulse\cockpit\services;

use Craft;
use craft\db\Query;
use craft\elements\User;
use craft\helpers\Console;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\records\Candidate;
use Illuminate\Support\Collection;
use yii\base\Component;
use Exception;

/**
 * Candidate Service service
 */
class CandidateService extends Component
{
    public function getCandidateByUserId(int $userId): ?Candidate
    {
        return Candidate::find()->where(['userId' => $userId])->one();
    }

    public function getCandidateByCockpitId(string $cockpitId): ?Candidate
    {
        return Candidate::find()->where(['cockpitId' => $cockpitId])->one();
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
        $user = User::find()->where(['email' => $email])->one() ?? new User();

        $user->email = $email;
        $user->username = $email;
        $user->fullName = $data['name'] ?? null;
        $user->firstName = $data['firstName'] ?? null;
        $user->lastName = $data['lastName'] ?? null;

// @TODO: create a layer in between for mapping data between field layout and publication
        $mappings = collect([
            'mobile' => $data['primaryMobilePhoneNumber']['number'] ?? null,
        ]);

        Console::stdout('Add field mappings to the user: '.PHP_EOL);
        // loop through mappings to add the Cockpit data
        foreach($user->getFieldValues() as $field => $value) {
            $mapping = $mappings->get($field) ?? $value;
            Console::stdout('   > Mapping for '.$field.' with value: '.$mapping.PHP_EOL);

            $user->setFieldValue($field, $mapping);
        }

        if (!$user->id) {
            $user->pending = true;
        }

        if (!$user->validate()) {
            throw new Exception('Could not validate user: '.print_r($user->getErrors(), true));
        }

        if (Craft::$app->getElements()->saveElement($user)) {
            if (Cockpit::$plugin->getSettings()->userGroup) {
                $userGroup = Craft::$app->userGroups->getGroupByHandle(Cockpit::$plugin->getSettings()->userGroup);

                if (!Craft::$app->getUsers()->assignUserToGroups($user->id, [$userGroup->id])) {
                    throw new Exception('Could not assign user to the group');
                }
            }

            if ($user->getStatus() == User::STATUS_PENDING) {
                Craft::$app->getUsers()->sendActivationEmail($user);
            }

            $this->upsertCandidate($user->id, $data['id']);

            return $user;
        } else {
            throw new Exception('Could not save user');
        }

        return null;
    }
}
