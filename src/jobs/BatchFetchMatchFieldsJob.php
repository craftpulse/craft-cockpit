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
use craft\base\Batchable;
use craft\queue\BaseBatchedJob;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\errors\MatchFieldNotFoundException;
use craftpulse\cockpit\models\FetchBatch;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use Throwable;
use yii\base\InvalidConfigException;

/**
 * Class BatchFetchMatchFieldsJob
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 */
class BatchFetchMatchFieldsJob extends BaseBatchedJob
{
    public ?string $type = null;
    public ?string $matchFieldId = null;

    public function batchSize(): int
    {
        return 50;
    }

    public function loadData(): Batchable
    {
        try {
            $results = Cockpit::$plugin->getApi()->getMatchFieldsByType($this->type)['results'] ?? collect([]);

            if ($results->isEmpty()) {
                Craft::error('No match fields found');
                return new FetchBatch([]); // empty batch to avoid failure
            }

            $matchFields = $results->map(fn($matchField) => [
                'id' => $matchField->get('id'),
                'name' => $matchField->get('name'),
                'matchFieldId' => $this->matchFieldId,
            ])->values()->all();

            Craft::info('Loaded ' . count($matchFields) . ' match fields.', __METHOD__);

            return new FetchBatch($matchFields);

        } catch (Throwable $e) {
            Craft::error($e->getMessage(), __METHOD__);
            return new FetchBatch([]);
        }
    }

    /**
     * @throws MatchFieldNotFoundException
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function processItem(mixed $item): void
    {
        $matchField = Collection::make($item);

        if ($matchField->get('id') && $matchField->get('name') && $matchField->get('matchFieldId')) {
            Cockpit::$plugin->getMatchFieldEntries()->saveMatchFieldEntry($matchField);
        }
    }

    protected function defaultDescription(): string
    {
        $type = Str::headline($this->type);
        return "Saving match fields from type {$type}";
    }
}
