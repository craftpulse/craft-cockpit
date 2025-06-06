<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\services;

use Craft;
use craft\base\Component;
use craft\helpers\App;
use craft\helpers\Console;
use craft\helpers\Json;

use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\models\SettingsModel;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use yii\base\ExitException;
use yii\log\Logger;
use yii\web\Response;
use function PHPUnit\Framework\throwException;

/**
 * Class Api
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 * @property-read SettingsModel $settings
 */
class Api extends Component
{
    // Private Properties
    // =========================================================================

    /**
     * @var SettingsModel
     */
    private SettingsModel $settings;

    /**
     * @var Client|null
     */
    private ?Client $_client = null;

    // Public Constants
    // =========================================================================

    // Public Methods
    // =========================================================================
    /**
     * @inheritdoc
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();
        $this->settings = Cockpit::$plugin->settings;

        Collection::macro('recursive', function () {
            return $this->map(function ($value) {
                if (is_array($value) || is_object($value)) {
                    return Collection::make($value)->recursive();
                }

                return $value;
            });
        });
    }

    /**
     * Returns or sets up a Rest API client.
     */
    public function getClient(): ?Client
    {
        if ($this->_client === null) {
            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Api-Key' => App::parseEnv($this->settings->apiKey),
            ];
            $this->_client = Craft::createGuzzleClient(
                [
                'base_uri' => App::parseEnv($this->settings->apiUrl),
                'headers' => $headers,
                ]
            );
        }

        return $this->_client;
    }

    /**
     * Returns the match field types
     *
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getMatchFieldTypes(): ?Collection
    {
        return $this->_generateMatchFieldTypes($this->get('MatchFields/types'));
    }

    /**
     * Returns the match fields by Type
     *
     * @param string $type
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getMatchFieldsByType(string $type): ?Collection
    {
        $query = [
            'type' => $type,
        ];

        return $this->get('MatchFields', $query);
    }

    /**
     * Returns the match field by ID
     *
     * @param string $id
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getMatchFieldById(string $id): ?Collection
    {
        $query = [
            'id' => $id,
        ];

        return $this->get('MatchFields/types', $query);
    }

    /**
     * Returns the publications
     *
     * @param array{
     *     limit?:  int,
     *     start?:  int,
     *     filter?: string
     * } $params
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getPublications(array $params = []): ?Collection
    {
        return $this->get('Publications', $params);
    }

    /**
     * Returns the publications by ID
     *
     * @param string $id
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getPublicationById(string $id): ?Collection
    {
        return $this->get("Publications/$id");
    }

    /**
     * Returns the job requests
     *
     * @param array{
     *     limit?:  int,
     *     start?:  int,
     *     filter?: string
     * } $params
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getJobRequests(array $params = []): ?Collection
    {
        return $this->get('JobRequests', $params);
    }

    /**
     * Returns the job requests by ID
     *
     * @param string $id
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getJobRequestById(string $id): ?Collection
    {
        return $this->get("JobRequests/$id");
    }

    /**
     * Returns the departments
     *
     * @param array{
     *     limit?:  int,
     *     start?:  int,
     *     filter?: string
     * } $params
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getDepartments(array $params = []): ?Collection
    {
        return $this->get('Departments', $params);
    }

    /**
     * Returns the departments by ID
     *
     * @param string $id
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getDepartmentById(string $id): ?Collection
    {
        return $this->get("Departments/$id");
    }

    /**
     * Returns the departments by ID
     *
     * @param string $id
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getUserById(string $id): ?Collection
    {
        return $this->get("Users/$id");
    }

    /**
     * Returns the departments by ID
     *
     * @param string $id
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getCandidateById(string $id): ?Collection
    {
        return $this->get("Candidates/$id");
    }

    /**
     * Returns the registered webhooks
     *
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getRegisteredWebhooks(): ?Collection
    {
        return $this->get('WebHookRegistrations');
    }

    /**
     * Returns the available webhooks
     *
     * @return Collection|null
     * @throws GuzzleException
     */
    public function getAvailableWebhooks(): ?Collection
    {
        return $this->get('WebHookFilters');
    }

    /**
     * Posts an application
     *
     * @param array $data
     * @return Collection|null
     * @throws GuzzleException
     */
    public function postApplication(array $data): ?Collection
    {
        return $this->post("Applications", $data);
    }

    /**
     * Posts an application
     *
     * @param array $data
     * @return Collection|null
     * @throws GuzzleException
     */
    public function postApplicationKnownCandidate(string $id, array $data): ?Collection
    {
        return $this->post("Applications/candidate/$id", $data);
    }

    /**
     * Posts an application
     *
     * @param array $data
     * @return Collection|null
     * @throws GuzzleException
     */
    public function postSpontaneousApplication(array $data): ?Collection
    {
        return $this->post("CandidateEnrollments", $data);
    }

    /**
     * @param string $endpoint
     * @param array|null $query
     * @return Response|null
     * @throws GuzzleException
     */
    public function get(string $endpoint, ?array $query = null): ?Collection
    {
        try {
            $response = $this->getClient()->request('GET', $endpoint, $query ? ['query' => $query] : []);
            return Collection::make($this->_getContent($response))->recursive();
        } catch (GuzzleException $e) {
            // You can log full response for debugging
            $statusCode = $e->getResponse()?->getStatusCode();
            $body = $e->getResponse()?->getBody()?->getContents();

            Cockpit::$plugin->log("HTTP $statusCode when getting to $endpoint", [], Logger::LEVEL_ERROR, 'cockpit');

            if ($query) {
                Cockpit::$plugin->log('', $query, Logger::LEVEL_INFO, 'cockpit');
            }
        }

        return null;
    }

    /**
     * @param string $endpoint
     * @param array $data
     * @return Response|null
     * @throws GuzzleException
     */
    public function post(string $endpoint, array $data): ?Collection
    {
        try {
            $response = $this->getClient()->request('POST', $endpoint, ['json' => $data]);
            $statusCode = $response->getStatusCode();

            $message = "HTTP $statusCode when posting to $endpoint";

            switch ($statusCode) {
                case 200:
                    $message .= " - OK";
                    break;
                case 201:
                    $message .= " - Created";
                    break;
                case 202:
                    $message .= " - Accepted";
                    break;
                case 204:
                    $message .= " - No Content";
                    break;
                default:
                    $message .= "";
            }

            Console::stdout($message . PHP_EOL, Console::FG_GREEN);
            return Collection::make($this->_getContent($response))->recursive();
        } catch (GuzzleException $e) {
            // You can log full response for debugging
            $statusCode = $e->getResponse()?->getStatusCode();
            $body = $e->getResponse()?->getBody()?->getContents();

            Console::stdout("HTTP $statusCode when posting to $endpoint: $body".PHP_EOL, Console::FG_RED);
            Cockpit::$plugin->log("HTTP $statusCode when posting to $endpoint: $body", [], Logger::LEVEL_ERROR, 'cockpit-application');
            Cockpit::$plugin->log('', $data, Logger::LEVEL_INFO, 'cockpit-application');
        }

        return null;
    }

    // Private Methods
    // =========================================================================
    /**
     * @param ResponseInterface|null $response
     * @return array|null
     */
    private function _getContent(?ResponseInterface $response): ?array
    {
        if (!$response) {
            return null;
        }

        return Json::decodeIfJson($response->getBody()->getContents());
    }

    /**
     * @param Collection|null $matchFields
     * @return Collection|null
     */
    private function _generateMatchFieldTypes(?Collection $matchFields): ?Collection
    {
        if (!$matchFields) {
            return null;
        }

        $options = $matchFields->mapWithKeys(fn ($label) => [
            $label => Craft::t('cockpit', Str::headline($label)),
        ])->all();

        return Collection::make(Json::decodeIfJson($options));
    }

}
