<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\jobs;

use Craft;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\models\FetchBatch;
use craft\queue\BaseBatchedJob;
use craft\base\Batchable;
use GuzzleHttp\Exception\GuzzleException;
use yii\base\InvalidConfigException;

/**
 * Class BatchFetchPublicationsJob
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 */
class BatchFetchPublicationsJob extends BaseBatchedJob
{

    public function batchSize(): int
    {
        return 50;
    }

    public function loadData(): Batchable
    {
        try {
            $results = Cockpit::$plugin->getApi()->getMatchFieldsByType();

            if ($results->isEmpty()) {
                Craft::error('No publications found');
                return new FetchBatch([]); // empty batch to avoid failure
            }

            $publications = [];
            $publications = $results->map(fn($p) => [
                'id' => $p->get('id'),
                'name' => $p->get('name'),
            ])->values()->all();

            Craft::info('Loaded ' . count($publications) . ' publications.', __METHOD__);

            return new FetchBatch($publications);

        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return new FetchBatch([]);
        }
    }

    /**
     * @throws GuzzleException
     * @throws InvalidConfigException
     */
    public function processItem(mixed $item): void
    {
        $id = $item['id'];
        $name = $item['name'];

        if ($id) {
            Cockpit::$plugin->getJobs()->fetchPublicationById($id);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Fetching publications from Cockpit';
    }
}

