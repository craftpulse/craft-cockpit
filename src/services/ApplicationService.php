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
        // https://cdn.craft.cloud/c2e071f6-cc94-4bea-8d47-104743b4ff12/assets/documents/cv/lo%CC%80re%CC%81m-ipsu%CC%82m-2_2025-03-14-101545_wfzb.pdf

        $cv = $this->_getCv('https://cdn.craft.cloud/c2e071f6-cc94-4bea-8d47-104743b4ff12/assets/documents/cv/lo%CC%80re%CC%81m-ipsu%CC%82m-2_2025-03-14-101545_wfzb.pdf');
        $data = [
            'publication' => [
                'id' => 'publications-5-B'
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
                'primaryEmailAddress' => 'stefanie.gevaert+1@pau.be',
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

/*
 * {
  "publication": {
    "id": "string"
  },
  "motivationFile": {
    "filename": "string",
    "contentType": "string",
    "contentBase64": "string"
  },
  "curriculumVitae": {
    "filename": "string",
    "contentType": "string",
    "contentBase64": "string"
  },
  "motivation": "string",
  "applicationDate": "2025-06-04T08:34:15.468Z",
  "allowEmailCommunication": {
    "consentGiven": true,
    "timestamp": "2025-06-04T08:34:15.468Z"
  },
  "fields": [
    {
      "key": "string",
      "label": "string",
      "value": "string"
    }
  ],
  "owner": {
    "userId": "string",
    "departmentId": "string"
  },
  "remoteId": "string",
  "applicationOrigin": {
    "id": "string"
  },
  "campaignSource": {
    "source": "string",
    "medium": "string",
    "campaign": "string",
    "term": "string",
    "content": "string"
  },
  "candidate": {
    "firstName": "string",
    "middleName": "string",
    "lastName": "string",
    "initials": "string",
    "remoteId": "string",
    "gender": "Unknown",
    "dateOfBirth": "2025-06-04T08:34:15.468Z",
    "address": {
      "street": "string",
      "housenumber": "string",
      "housenumberSuffix": "string",
      "zipcode": "string",
      "city": "string",
      "countryCode": "st",
      "region": "string"
    },
    "primaryEmailAddress": "user@example.com",
    "primaryPhoneNumber": {
      "number": "string",
      "countryCode": "st"
    },
    "primaryMobilePhoneNumber": {
      "number": "string",
      "countryCode": "st"
    },
    "language": {
      "id": "string"
    },
    "communicationChannel": "Mail",
    "curriculumVitae": {
      "filename": "string",
      "contentType": "string",
      "contentBase64": "string"
    },
    "photo": {
      "filename": "string",
      "contentType": "string",
      "contentBase64": "string"
    },
    "preference": {
      "sectors": [
        {
          "id": "string"
        }
      ],
      "professions": [
        {
          "id": "string"
        }
      ],
      "contractTypes": [
        {
          "id": "string"
        }
      ],
      "workingDays": [
        {
          "id": "string"
        }
      ],
      "employmentTypes": [
        {
          "id": "string"
        }
      ],
      "shiftServices": [
        {
          "id": "string"
        }
      ],
      "workLocationPreferences": [
        {
          "id": "string"
        }
      ],
      "noticePeriod": {
        "id": "string"
      },
      "availableFrom": "2025-06-04T08:34:15.468Z",
      "isAvailable": true,
      "travelDistance": 180,
      "hoursPerWeek": {
        "min": 80,
        "max": 80
      },
      "salaryPeriod": "Hour",
      "salaryAmount": 0,
      "salaryDescriptionInternal": "string",
      "freelancerInfo": {
        "minimumRate": 0,
        "maximumRate": 0,
        "ratePeriod": "Hour",
        "additionalCosts": 0,
        "additionalCostPeriod": "Hour",
        "employmentPossessions": [
          {
            "id": "string"
          }
        ],
        "rentedOutBy": {
          "company": {
            "id": "string"
          },
          "contactPersons": [
            {
              "id": "string"
            },
            {
              "id": "string"
            },
            {
              "id": "string"
            }
          ]
        }
      }
    },
    "experience": {
      "driversLicenses": [
        {
          "id": "string"
        }
      ],
      "meansOfTransport": [
        {
          "id": "string"
        }
      ],
      "experienceLevel": {
        "id": "string"
      },
      "educationLevel": {
        "id": "string"
      },
      "intellectualAbilityLevels": [
        {
          "id": "string"
        }
      ],
      "professionalLevels": [
        {
          "id": "string"
        }
      ],
      "employments": [
        {
          "function": "string",
          "company": "string",
          "location": "string",
          "start": "2025-06-04T08:34:15.468Z",
          "end": "2025-06-04T08:34:15.468Z",
          "current": true,
          "description": "string",
          "includeOnCv": true
        }
      ]
    },
    "softSkills": [
      "string"
    ],
    "hardSkills": [
      {
        "name": "string",
        "rating": 100
      }
    ],
    "certificates": [
      "string"
    ],
    "educations": [
      {
        "educationLevel": {
          "id": "string"
        },
        "title": "string",
        "institution": "string",
        "location": "string",
        "fromYear": 2026,
        "toYear": 0,
        "hasGraduated": true,
        "description": "string"
      }
    ],
    "tags": [
      "string"
    ],
    "allowMediation": {
      "consentGiven": true,
      "timestamp": "2025-06-04T08:34:15.468Z"
    },
    "allowWhatsAppCommunication": {
      "consentGiven": true,
      "timestamp": "2025-06-04T08:34:15.468Z"
    },
    "allowBulkEmailCommunication": {
      "consentGiven": true,
      "timestamp": "2025-06-04T08:34:15.468Z"
    },
    "metaInfo": {
      "origin": {
        "id": "string"
      },
      "internalRemarks": "string"
    },
    "nationality": {
      "id": "string"
    }
  }
}
 */
