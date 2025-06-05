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
use yii\base\Component;
use function PHPUnit\Framework\throwException;

/**
 * Application Service service
 */
class ApplicationService extends Component
{
    public function applyForJob($payload): ?Collection
    {
        $response = null;

        try {
            $cv = null;
            $email = $payload['email'];

            if (!$email) {
                return null;
                throwException(Craft::t('cockpit','Email is required'));
            }

            if ($payload['cv'] ?? null) {
                $cv = $this->_getCv($payload['cv']);
                Console::stdout('CV is attached and fetched: '.$cv['filename'].PHP_EOL);
            }

            $cockpitCandidateId = null;

            $user = User::find()->email($email)->one();

            if ($user) {
                $candidate = Cockpit::$plugin->getCandidates()->getCandidateByUserId($user->id);
                $cockpitCandidateId = $candidate->cockpitId ?? null;
                Console::stdout('Candidate is known in our system: '. $user['email'].PHP_EOL);
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
                    'primaryEmailAddress' => $email,
                    'primaryMobilePhoneNumber' => [
                        'number' => $payload['phoneNumber'] ?? null,
                        'countryCode' => 'BE' // @TODO: convert +32 to BE
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
                Console::stdout(print_r($data, true) . PHP_EOL);
                $response = Cockpit::$plugin->getApi()->postApplicationKnownCandidate($cockpitCandidateId, $data);
            } else {
                Console::stdout('Post application with payload:' . PHP_EOL);
                Console::stdout(print_r($data, true) . PHP_EOL);
                $response = Cockpit::$plugin->getApi()->postApplication($data);

                // register user
                if ($response) {
                    $cockpitUser = Cockpit::$plugin->getApi()->getCandidateById( $response['candidate']['id']);
                    Console::stdout('Register candidate on our system: '. $email.PHP_EOL);

                    Cockpit::$plugin->getCandidates()->registerUser($email, $cockpitUser);
                }
            }

        } catch (\Exception $e) {
            Craft::error($e->getMessage(), __METHOD__);
            Console::stdout('Error applying for job: '.$e->getMessage(), Console::FG_RED);
        }

        Console::stdout('Response from Cockpit:' . PHP_EOL);
        Console::stdout(print_r($response, true) . PHP_EOL);

        return $response;
    }

    public function applyForSpontaneousJob(): void
    {
        $cv = $this->_getCv('https://cdn.craft.cloud/c2e071f6-cc94-4bea-8d47-104743b4ff12/assets/documents/cv/lo%CC%80re%CC%81m-ipsu%CC%82m-2_2025-03-14-101545_wfzb.pdf');
        $email = 'stefanie.gevaert+dev18@pau.be';

        $data = [
            'applicationDate' => Carbon::now()->toIso8601String(),
            'owner' => [
                'departmentId' => 'departments-876-C',
            ],
            'allowEmailCommunication' => [
                'consentGiven' => true,
                'timestamp' => Carbon::now()->toIso8601String(),
            ],
            'curriculumVitae' => $cv ?? null,
            'campaignSource' => [
                'source' => 'go4jobs',
                'medium' => 'website',
                'campaign' => 'go4jobs',
                'term' => 'go4jobs',
                'content' => 'go4jobs'
            ],
            'candidate' => [
                'firstName' => 'Stefanie (DEV)',
                'lastName' => 'Gevaert',
                'primaryEmailAddress' => $email,
                'primaryMobilePhoneNumber' => [
                    'number' => '0498666688',
                    'countryCode' => 'BE'
                ],
                'softSkills' => null,
                'allowMediation' => [
                    'consentGiven' => true,
                    'timestamp' => Carbon::now()->toIso8601String(),
                ]
            ]
        ];

        $response = Cockpit::$plugin->getApi()->postSpontaneousApplication($data);

        if ($response) {
            $cockpitCandidateId = null;

            $user = User::find()->email($email)->one();

            if (!$user) {
                Cockpit::$plugin->getCandidates()->registerUser($email, $response);
            }
        }

        Craft::dd($response);
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
                throw new \Exception('Base64 content must not be empty.');
            }

            $fileData = [
                'filename' => $filename,
                'contentType' => $contentType,
                'contentBase64' => $contentBase64,
            ];

            return $fileData;

        } catch (\Exception $e) {
            throw new \Exception("Failed to download file from URL: $url. Error: " . $e->getMessage());
        }

        return null;
    }
}
