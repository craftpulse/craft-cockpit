<?php

namespace craftpulse\cockpit\jobs;

use Craft;
use craft\base\Batchable;
use craft\queue\BaseBatchedJob;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\models\FetchBatch;

/**
 * Batch Fetch Departments Job queue job
 */
class BatchFetchDepartmentsJob extends BaseBatchedJob
{
    private array $departments = [];

    public function init(): void
    {
        parent::init();
    }

    public function batchSize(): int
    {
        return 50;
    }

    public function loadData(): Batchable
    {
        try {
            $results = Cockpit::$plugin->getApi()->getDepartments()['results'] ?? collect([]);

            if ($results->isEmpty()) {
                Craft::error('No departments found');
                return new FetchBatch([]); // empty batch to avoid failure
            }

            $departments = $results->map(fn($p) => [
                'id' => $p->get('id'),
                'name' => $p->get('name'),
            ])->values()->all();

            Craft::info('Loaded ' . count($departments) . ' departments.', __METHOD__);

            return new FetchBatch($departments);

        } catch (\Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return new FetchBatch([]);
        }
    }

    public function processItem(mixed $item): void
    {
        $id = $item['id'];
        $name = $item['name'];

        if ($id) {
            Cockpit::$plugin->getDepartments()->fetchDepartmentByCockpitId($id);
        }
    }

    protected function defaultDescription(): string
    {
        return 'Fetching departments from Cockpit';
    }
}
