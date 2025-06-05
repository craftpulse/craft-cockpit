<?php

namespace craftpulse\cockpit\jobs;

use Craft;
use craft\helpers\Console;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\models\FetchBatch;
use craft\queue\BaseBatchedJob;
use craft\base\Batchable;
use Throwable;
use function PHPUnit\Framework\isNan;

class BatchFetchPublicationsJob extends BaseBatchedJob
{
    public function batchSize(): int
    {
        return 50;
    }

    public function loadData(): Batchable
    {
        Console::stdout('Queue: Start publication fetch (loadData)' . PHP_EOL, Console::FG_BLUE);
        try {
            $allPublications = collect();
            $offset = 0;
            $limit = $this->batchSize();
            $loopings = 0;

            $pagination = $response = Cockpit::$plugin->getApi()->getPublications(['start' => 0, 'limit' => 1]);

            if ($pagination) {
                $loopings = (int) ceil($pagination['totalResults'] / $limit);
            }

            for($i = 0; $i < $loopings; $i++) {
                $response = Cockpit::$plugin->getApi()->getPublications([
                    'start' => $offset,
                    'limit' => $limit,
                ]);

                Console::stdout('    > Fetch results starting from ' . $offset . ' with a limit of ' . $limit . PHP_EOL, Console::FG_BLUE);

                $publications = collect($response['results'] ?? []);
                $resultCount = $response['resultCount'] ?? 0;
                $totalResults = $response['totalResults'] ?? 0;
                $skippedResults = $response['skippedResults'] ?? 0;

                Console::stdout('    > Current batch results ' . $resultCount . PHP_EOL, Console::FG_PURPLE);
                Console::stdout('    > Total results ' . $totalResults . PHP_EOL, Console::FG_PURPLE);
                Console::stdout('    > Skipped results ' . $skippedResults . PHP_EOL, Console::FG_PURPLE);

                if ($publications->isEmpty()) {
                    break;
                }

                $allPublications = $allPublications->merge($publications);

                // Stop if we've fetched all results
                if ($resultCount >= $totalResults) {
                    break;
                }

                Console::stdout('    > All publications count ' . $allPublications->count() . PHP_EOL, Console::FG_BLUE);
                Console::stdout('    > Skip ' . $offset . PHP_EOL, Console::FG_BLUE);
                Console::stdout('    > Count ' .  $allPublications->count() . PHP_EOL, Console::FG_BLUE);

                if ($allPublications->count() >= $totalResults) {
                    Craft::warning('Aborting pagination: reached max page limit.', __METHOD__);
                    break;
                }

                $offset = $skippedResults + $limit;

                Console::stdout('----------------------' . PHP_EOL, Console::FG_BLACK);
            }

            Craft::info('Loaded ' . $allPublications->count() . ' publications.', __METHOD__);

            Console::stdout('------- Total jobs to fetch: '.$allPublications->count().' ------- '. PHP_EOL, Console::FG_YELLOW);

            return new FetchBatch($allPublications->values()->all());

        } catch (Throwable $e) {
            Craft::error('Failed to fetch publications: ' . $e->getMessage(), __METHOD__);
            return new FetchBatch([]);
        }
    }

    public function processItem(mixed $item): void
    {
        $id = $item['id'] ?? null;

        if ($id) {
            try {
                Cockpit::$plugin->getJobs()->fetchPublicationById($id);
            } catch (Throwable $e) {
                Craft::error("Failed to process publication ID {$id}: " . $e->getMessage(), __METHOD__);
            }
        }
    }

    protected function defaultDescription(): string
    {
        return 'Fetching publications from Cockpit';
    }
}
