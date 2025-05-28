<?php

namespace craftpulse\cockpit\jobs;

use Craft;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\models\PublicationBatch;
use craft\queue\BaseBatchedJob;
use craft\base\Batchable;

class BatchFetchPublicationsJob extends BaseBatchedJob
{
    private array $publications = [];

    public function init(): void
    {
        parent::init();
    }

    public function loadData(): Batchable
    {
        try {
            $results = Cockpit::$plugin->getApi()->getPublications()['results'] ?? collect([]);

            if ($results->isEmpty()) {
                Craft::error('No publications found');
                return new PublicationBatch([]); // empty batch to avoid failure
            }

            $publications = $results->map(fn($p) => [
                'id' => $p->get('id'),
                'name' => $p->get('name'),
            ])->values()->all();

            Craft::info('Loaded ' . count($publications) . ' publications.', __METHOD__);

            return new PublicationBatch($publications);

        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return new PublicationBatch([]);
        }
    }


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

