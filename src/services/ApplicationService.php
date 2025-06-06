<?php

namespace craftpulse\cockpit\services;

use Carbon\Carbon;
use Craft;
use craft\elements\Asset;
use craft\elements\User;
use craft\helpers\Console;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\records\Candidate;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use libphonenumber\PhoneNumberUtil;
use yii\base\Component;
use yii\log\Logger;
use Exception;
use Throwable;

/**
 * Application Service service
 */
class ApplicationService extends Component
{
    public function applyForJob(array $payload): ?Collection
    {
        $response = null;

        try {
            $cv = null;

            if (is_null($payload['candidateId'] ?? null) && is_null($payload['email'] ?? null)) {
                Console::stdout('Either the Candidate ID or email is required to apply for a job');
                Cockpit::$plugin->log('Candidate ID or email is required', $payload, Logger::LEVEL_ERROR, 'cockpit-application');
                throw new Exception(Craft::t('cockpit','Candidate ID or email is required'));
                return null;
            }

            if ($payload['cv'] ?? null) {
                $cv = $this->_getCv($payload['cv']);
                Console::stdout('CV is attached and fetched: '.$cv['filename'].PHP_EOL);
            }

            $cockpitCandidateId = null;

            // if candidateId is provided from formie, use that one. if not, check if we know the candidateId stored in a user
            if ($payload['candidateId'] ?? null) {
                $cockpitCandidateId = $payload['candidateId'];
            } elseif (Cockpit::$plugin->getSettings()->registerUsers) {
                $email = $payload['email'] ?? null;

                if (!$email) {
                    Console::stdout('Can\'t find a candidate to send either by candidateId nor email');
                    Cockpit::$plugin->log('Can\'t find a candidate to send either by candidateId nor email', $payload, Logger::LEVEL_ERROR, 'cockpit-application');
                    throw new Exception(Craft::t('cockpit','Candidate ID or email is required'));
                    return null;
                }

                $user = User::find()->email($email)->one();

                if ($user) {
                    $candidate = Cockpit::$plugin->getCandidates()->getCandidateByUserId($user->id);
                    $cockpitCandidateId = $candidate->cockpitId ?? null;
                    Console::stdout('Candidate is known in our system: '. $user['email'] . ' - candidate ID: '.$cockpitCandidateId.PHP_EOL);
                }
            }

            $data = [
                'publication' => [
                    'id' => $payload['publicationId'] ?? null,
                ],
                'applicationDate' => Carbon::now()->toIso8601String(),
                'owner' => [
                    'departmentId' => $payload['departmentId'] ?? null,
                ],
                'allowEmailCommunication' => [
                    'consentGiven' => ($payload['emailCommunicationConsent'] ?? null) ? true : false,
                    'timestamp' => Carbon::now()->toIso8601String(),
                ],
                'curriculumVitae' => $cv ?? null,
                'campaignSource' => [
                    'source' => $playload['utmSource'] ?? null,
                    'medium' => $playload['utmMedium'] ?? null,
                    'campaign' => $playload['utmCampaign'] ?? null,
                    'term' => $playload['utmTerm'] ?? null,
                    'content' => $playload['utmContent'] ?? null
                ],
            ];

            if (!$cockpitCandidateId) {
                $data['candidate'] = [
                    'firstName' => $payload['firstName'] ?? null,
                    'lastName' => $payload['lastName'] ?? null,
                    'primaryEmailAddress' => $payload['email'] ?? null,
                    'primaryMobilePhoneNumber' => [
                        'number' => $payload['phoneNumber'] ?? null,
                        'countryCode' => $payload['phoneCountry'] ?? null,
                    ],
                    'softSkills' => null,
                    'allowMediation' => [
                        'consentGiven' => ($payload['allowMediation'] ?? null) ? true : false,
                        'timestamp' => Carbon::now()->toIso8601String(),
                    ]
                ];
            }

            if ($cockpitCandidateId) {
                Console::stdout('Post application known candidate with payload:' . PHP_EOL);
                Console::stdout(json_encode($data) . PHP_EOL);
                $response = Cockpit::$plugin->getApi()->postApplicationKnownCandidate($cockpitCandidateId, $data);
            } else {
                Console::stdout('Post application with payload:' . PHP_EOL);
                Console::stdout(json_encode($data) . PHP_EOL);
                $response = Cockpit::$plugin->getApi()->postApplication($data);

                // register user
                if ($response && Cockpit::$plugin->getSettings()->registerUsers) {
                    $cockpitUser = Cockpit::$plugin->getApi()->getCandidateById($response['candidate']['id']);
                    Console::stdout('Register/update candidate on our system: '. var_dump($cockpitUser) .PHP_EOL);

                    Cockpit::$plugin->getCandidates()->registerUser($email, $cockpitUser);
                }
            }

        } catch (Exception $e) {
            Cockpit::$plugin->log($e->getMessage(), [], Logger::LEVEL_ERROR, 'cockpit-application');
            Console::stdout('Error applying for job: '.$e->getMessage().PHP_EOL, Console::FG_RED);
            return null;
        }

        Console::stdout('Response from Cockpit' . PHP_EOL);

        return $response;
    }

    public function applyForSpontaneousJob(array $payload): ?Collection
    {
        try {
            $user = null;

            if (is_null($payload['candidateId'] ?? null) && is_null($payload['email'] ?? null)) {
                Console::stdout('Either the Candidate ID or email is required to apply for a job');
                Cockpit::$plugin->log('Candidate ID or email is required', $payload, Logger::LEVEL_ERROR, 'cockpit-application');
                throw new Exception(Craft::t('cockpit','Candidate ID or email is required'));
                return null;
            }

            if ($payload['cv'] ?? null) {
                $cv = $this->_getCv($payload['cv']);
                Console::stdout('CV is attached and fetched: '.$cv['filename'].PHP_EOL);
            }

            $data = [
                'applicationDate' => Carbon::now()->toIso8601String(),
                'owner' => [
                    'departmentId' => $payload['departmentId'] ?? null,
                ],
                'allowEmailCommunication' => [
                    'consentGiven' => ($payload['emailCommunicationConsent'] ?? null) ? true : false,
                    'timestamp' => Carbon::now()->toIso8601String(),
                ],
                'curriculumVitae' => $cv ?? null,
                'campaignSource' => [
                    'source' => $playload['utmSource'] ?? null,
                    'medium' => $playload['utmMedium'] ?? null,
                    'campaign' => $playload['utmCampaign'] ?? null,
                    'term' => $playload['utmTerm'] ?? null,
                    'content' => $playload['utmContent'] ?? null
                ],
            ];

            // if candidateId is provided, fetch the information of the user
            if ($payload['candidateId'] ?? null) {
                $cockpitCandidateId = $payload['candidateId'];
                $candidate = Cockpit::$plugin->getCandidates()->getCandidateByCockpitId($cockpitCandidateId);

                if (!$candidate) {
                    Console::stdout('Candidate is not known in our system: '. $cockpitCandidateId.PHP_EOL);
                    throw new Exception(Craft::t('cockpit', 'Candidate is not known in our system'));
                    return null;
                }

                $user = User::find()->id($candidate->userId)->one();

                if (!$user) {
                    Console::stdout('Candidate is not known in our system: '. $cockpitCandidateId.PHP_EOL);
                    throw new Exception(Craft::t('cockpit', 'Candidate is not known in our system'));
                    return null;
                }

            }

// @TODO: create a layer in between for mapping data between field layout and user
            $mappings = collect([
                'firstName' => $user['firstName'] ?? $payload['firstName'] ??  null,
                'lastName' => $user['lastName'] ??  $payload['lastName'] ?? null,
                'email' => $user['email'] ??  $payload['email'] ?? null,
                'phone' => $user['mobile'] ?? $payload['phoneNumber'] ?? null,
                'phoneCountryCode' => $this->_getPhoneRegion($user['mobile'] ?? null) ?? ($payload['phoneCountry'] ?? 'BE'),
                'softSkills' => null,
            ]);

            $data['candidate'] = [
                'firstName' => $mappings['firstName'] ?? null,
                'lastName' => $mappings['lastName'] ?? null,
                'primaryEmailAddress' => $mappings['email'] ?? null,
                'primaryMobilePhoneNumber' => [
                    'number' => $mappings['phone'] ?? null,
                    'countryCode' =>  $mappings['phoneCountryCode'] ?? null,
                ],
                'softSkills' => null,
                'allowMediation' => [
                    'consentGiven' => ($payload['allowMediation'] ?? null) ? true : false,
                    'timestamp' => Carbon::now()->toIso8601String(),
                ]
            ];

            $response = Cockpit::$plugin->getApi()->postSpontaneousApplication($data);

            // register user
            if ($response && Cockpit::$plugin->getSettings()->registerUsers) {
                $cockpitUser = Cockpit::$plugin->getApi()->getCandidateById( $response['candidate']['id']);
                Console::stdout('Register/update candidate on our system: '. $mappings['email'].PHP_EOL);

                Cockpit::$plugin->getCandidates()->registerUser($mappings['email'], $cockpitUser);
            }

        } catch (Exception $e) {
            Console::stdout('Error applying for spontaneous job: '.$e->getMessage(), Console::FG_RED);
            Cockpit::$plugin->log($e->getMessage(), $payload, Logger::LEVEL_ERROR, 'cockpit-application');
            return null;
        }

        Console::stdout('Response from Cockpit' . PHP_EOL);

        return $response;
    }

    private function _getCv(int $id): ?array
    {
        $asset = Asset::find()
            ->id($id)
            ->one();

        if (!$asset) {
            return null;
        }

        $filename = $asset->getFilename();
        $contentType = $asset->getMimeType();
        $contentBase64 = base64_encode($asset->getContents());


        $fileData = [
            'filename' => $filename,
            'contentType' => $contentType,
            'contentBase64' => $contentBase64,
        ];

        return $fileData;
    }

    private function _getCvOnline(string $url): ?array
    {
        $client = new Client();

        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'Accept' => '*/*',
                ],
                'http_errors' => true,
                'stream' => false,
            ]);

            $content = $response->getBody()->getContents();

            $filename = basename(parse_url($url, PHP_URL_PATH));
            $contentType = $response->getHeaderLine('Content-Type') ?: 'application/octet-stream';
            $contentBase64 = base64_encode($content);

            // Validate
            if (strlen($contentBase64) < 1) {
                throw new Exception('Base64 content must not be empty.');
            }

            $fileData = [
                'filename' => $filename,
                'contentType' => $contentType,
                'contentBase64' => $contentBase64,
            ];

            return $fileData;

        } catch (Exception $e) {
            throw new Exception("Failed to download file from URL: $url. Error: " . $e->getMessage());
        }

        return null;
    }

    private function _getPhoneRegion(?string $phone): ?string
    {
        try {
            return PhoneNumberUtil::getInstance()->getRegionCodeForNumber(
                PhoneNumberUtil::getInstance()->parse($phone),
                'ZZ'
            );
        } catch (Throwable $e) {
            // Log error if you want here
            return null;
        }

        return null;
    }
}
