<?php

namespace craftpulse\cockpit\services;

use Craft;
use craft\db\Query;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\records\Candidate;
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
}
