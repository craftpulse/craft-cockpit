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
use craft\db\Query;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\models\PostcodeMappingModel;
use Illuminate\Support\Collection;

class PostcodeService extends Component
{
    public function mapPostcode(string|int|null $postcode = null): ?string
    {
        if (is_null($postcode)) {
            return null;
        }

        $postcode = (string) $postcode;

        $collection = Collection::make($this->getPostcodeMapping());

        $city = $collection->filter(function ($item) use ($postcode) {
            return $item['postcode'] === $postcode;
        })->first();

        return is_null($city) ? null : (string) $city['cityName'] ?? null;
    }

    /**
     * @throws Exception
     */
    public function saveMapping(array $postcodeMapConfig): bool
    {
        // Validate the model
        $postcodeMap = new PostcodeMappingModel($postcodeMapConfig);

        // Make sure our array is JSON encoded.
        $postcodeMapConfig = $postcodeMap->getAttributes();

        $db = Craft::$app->getDb();
        // Check if we already have values
        $existingMap = (new Query())
            ->from(Table::POSTCODE_MAPPINGS)
            ->one();

        if(!empty($existingMap)) {
            // Update, and get the id
            $postcodeMapConfig['id'] = $existingMap['id'];
        }

        $isNew = (int)$postcodeMapConfig['id'] === 0;

        if (!$isNew) {
            // Update the existing record
            $db->createCommand()->update(
                Table::POSTCODE_MAPPINGS,
                $postcodeMapConfig,
                [
                    'id' => $postcodeMapConfig['id']
                ]
            )->execute();
        } else {
            unset($postcodeMapConfig['id']);
            // Create new record
            try {
                $db->createCommand()->insert(
                    Table::POSTCODE_MAPPINGS,
                    $postcodeMapConfig,
                )->execute();
            } catch(Exception $exception) {
                Craft::error($exception->getMessage(), __METHOD__);
                return false;
            }
        }

        return true;
    }

    public function getPostcodeMapping(): ?array
    {
        $query = (new Query())
            ->from(Table::POSTCODE_MAPPINGS)
            ->one();

        if(!empty($query)) {
            return json_decode($query['postcodeMapping'], true);
        }

        return null;
    }
}
