<?php

namespace craftpulse\cockpit\services;

use Carbon\Carbon;
use Craft;
use craft\helpers\Console;
use craftpulse\cockpit\Cockpit;
use GuzzleHttp\Client;
use yii\base\Component;

/**
 * Application Service service
 */
class ApplicationService extends Component
{
    public function applyForJob(): void
    {
        $cv = $this->_getCv('https://cdn.craft.cloud/c2e071f6-cc94-4bea-8d47-104743b4ff12/assets/documents/cv/lo%CC%80re%CC%81m-ipsu%CC%82m-2_2025-03-14-101545_wfzb.pdf');
        $email = 'stefanie.gevaert+1@pau.be';

        $data = [
            'publication' => [
                'id' => 'publications-4-A'
            ],
            'applicationDate' => Carbon::now()->toIso8601String(),
            'owner' => [
                'departmentId' => 'departments-875-A',
            ],
            'allowEmailCommunication' => [
                'consentGiven' => true,
                'timestamp' => Carbon::now()->toIso8601String(),
            ],
            'candidate' => [
                'firstName' => 'Go4',
                'lastName' => 'Jobs',
                'primaryEmailAddress' => $email,
                'softSkills' => null,
                'allowMediation' => [
                    'consentGiven' => true,
                    'timestamp' => Carbon::now()->toIso8601String(),
                ]
            ],
            'curriculumVitae' => $cv ?? null,
            'campaignSource' => [
                'source' => 'go4jobs',
                'medium' => 'website',
                'campaign' => 'go4jobs',
                'term' => 'go4jobs',
                'content' => 'go4jobs'
            ],
        ];

        $response = Cockpit::$plugin->getApi()->postApplication($data);

        Craft::dd($response);
    }

    public function applyForJobKnownCandidate(string $cockpitCandidateId): void
    {

        $data = [
            'publication' => [
                'id' => 'publications-4-A'
            ],
            'applicationDate' => Carbon::now()->toIso8601String(),
            'owner' => [
                'departmentId' => 'departments-875-A',
            ],
            'allowEmailCommunication' => [
                'consentGiven' => true,
                'timestamp' => Carbon::now()->toIso8601String(),
            ],
            'curriculumVitae' => null,
            'campaignSource' => [
                'source' => 'go4jobs',
                'medium' => 'website',
                'campaign' => 'go4jobs',
                'term' => 'go4jobs',
                'content' => 'go4jobs'
            ],
        ];

        $response = Cockpit::$plugin->getApi()->postApplicationKnownCandidate($cockpitCandidateId, $data);

        Craft::dd($response);
    }

    private function _getCv(string $url): ?array
    {
        $client = new Client();

        try {
            $response = $client->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'CraftCMS File Fetcher',
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
