<?php

namespace craftpulse\cockpit\services;

use Craft;
use craft\helpers\App;
use craft\helpers\Json;

use craftpulse\cockpit\Cockpit;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use yii\base\Component;

/**
 * Mapbox Service service
 */
class MapboxService extends Component
{

    /**
     * @const the mapbox forward geocoding endpoint
     */
    const MAPBOX_API_FORWARD_ENDPOINT = 'search/geocode/v6/forward';

    /**
     * @const the mapbox base url
     */
    const MAPBOX_API_BASE_URL='https://api.mapbox.com';

    /**
     * @const the language that needs to be fetched
     */
    const LANGUAGE = 'nl-BE';

    /**
     * @param $query
     * @return array|null
     * @throws GuzzleException
     */
    public function getFullAddress($query): ?array
    {
        if (!$this->getApiKey()) {
            return null;
        }

        $client = Craft::createGuzzleClient([
            'base_uri' => self::MAPBOX_API_BASE_URL,
        ]);

        $endpoint = self::MAPBOX_API_FORWARD_ENDPOINT;

        $queryParams = [
            'query' => [
                'q' => $query,
                'access_token' => $this->getApiKey(),
                'language' => self::LANGUAGE,
            ]
        ];

        if ($client === null) {
            return null;
        }

        $response = $client->request('GET', $endpoint, $queryParams);
        $response = Json::decodeIfJson($response->getBody()->getContents());

        return $response['features'][0];
    }

    /**
     * @param $query
     * @return array|null
     * @throws GuzzleException
     */
    public function getGeoPoints($query): ?array {
        $location = $this->getFullAddress($query);

        if (!$location) {
            return null;
        }

        return $location['geometry']['coordinates'];
    }

    /**
     * @return string
     */
    private function getApiKey(): string
    {
        return App::parseEnv(Cockpit::$plugin->getSettings()->mapboxApiKey);
    }
}
