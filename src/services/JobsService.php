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
use craft\events\ConfigEvent;
use craft\fieldlayoutelements\TextField;
use craft\helpers\Console;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craftpulse\cockpit\elements\Job;
use craftpulse\cockpit\fieldlayoutelements\AddressField;
use Illuminate\Support\Collection;

/**
 * Class JobsService
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class JobsService extends Component
{
    public function createJob(Collection $publication): void
    {
        // @TODO: create a layer in between for mapping data between field layout and publication

        $job = Job::find()->cockpitId($publication->get('id'))->one();

        if (!$job) {
            $job = new Job();
        }

        $job->city = $publication->get('jobRequest')['data']['location']['city'] ?? null;
        $job->cockpitCompanyId = $publication->get('jobRequest')['data']['company']['id'] ?? null;
        $job->cockpitId = $publication->get('id');
        $job->cockpitJobRequestId = $publication->get('jobRequest')['id'] ?? null;
        $job->cockpitOfficeId = $publication->get('owner')['departmentId'] ?? null;
        $job->companyName = $publication->get('jobRequest')['data']['company']['name'] ?? null;
        $job->title = $publication->get('title');

        if (!$job->validate()) {
            Console::stderr('   > Error on save job: ' . print_r($job->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to save job', __METHOD__);
            return;
        }

        if (!Craft::$app->elements->saveElement($job)) {
            Craft::error('Unable to save job', __METHOD__);
        }
    }

    /**
     * This creates the native fields for the job section
     * @return array[]|null
     */
    public function createFields(): ?array
    {
        return [
            [
                'class' => TextField::class,
                'attribute' => 'title',
                'name' => 'title',
                'label' => Craft::t('cockpit', 'Vacancy'),
                'inputType' => 'text',
                'mandatory' => true,
                'required' => true,
                'width' => '100%',
            ],
            [
                'class' => AddressField::class,
                'attribute' => 'address',
                'name' => 'address',
                'mandatory' => true,
                'label' => Craft::t('cockpit', 'Address'),
                'width' => '100%',
            ],
        ];
    }

    /**
     * @param ConfigEvent $event
     * @return void
     * @throws \yii\base\Exception
     */
    public function handleChangedFieldLayout(ConfigEvent $event): void
    {
        $data = $event->newValue;

        ProjectConfig::ensureAllFieldsProcessed();
        $fieldsService = Craft::$app->getFields();

        if (empty($data) || empty(reset($data))) {
            // Delete the field layout
            $fieldsService->deleteLayoutsByType(Job::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Job::class)->id;
        $layout->type = Job::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);

        // Invalidate job caches
        Craft::$app->getElements()->invalidateCachesForElementType(Job::class);
    }

    /**
     * @param ConfigEvent $event
     * @return void
     */
    public function handleDeletedFieldLayout(ConfigEvent $event): void
    {
        $fieldsService = Craft::$app->getFields();
        $fieldsService->deleteLayoutsByType(Job::class);
    }
}
