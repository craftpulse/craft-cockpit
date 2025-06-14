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

use Carbon\Carbon;
use Craft;
use craft\base\Component;
use craft\elements\Address;
use craft\events\ConfigEvent;
use craft\helpers\Console;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\Job;
use yii\base\Exception;
use DateTime;
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
    /**
     * @param string $id
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \yii\base\InvalidConfigException
     */
    public function fetchPublicationById(string $id): bool
    {
        Console::stdout('Start publication fetch ' . $id . PHP_EOL, Console::FG_CYAN);
        if (!$id) {
            Craft::error('Publication ID is required');
            Console::stdout('Error on fetching publication: Publication ID is required' . PHP_EOL, Console::FG_RED);
            return false;
        }

        // Get publication by ID
        $publication = Cockpit::$plugin->getApi()->getPublicationById($id);

        if (!$publication) {
            Craft::error('Publication not found');
            Console::stdout('   > Error on fetching publication: Publication not found' . PHP_EOL, Console::FG_RED);
            return false;
        }

        Console::stdout('   > Publication found: ' . $publication->get('title') . PHP_EOL);

        $jobRequestId = $publication->get('jobRequest')['id'] ?? null;

        if (!$jobRequestId) {
            Craft::error('Job request ID not found');
            Console::stdout('   > Error on fetching publication: Job request ID not found' . PHP_EOL, Console::FG_RED);
            return false;
        }

        Console::stdout('   > Job request found ' . PHP_EOL);

        $jobRequest = Cockpit::$plugin->getApi()->getJobRequestById($jobRequestId);
        $publication->get('jobRequest')['data'] = $jobRequest;

        $success = $this->upsertJob($publication);

        if ($success) {
            Console::stdout('   > Job ' . $publication->get('title'). ' added / updated in our system ' . PHP_EOL, Console::FG_GREEN);
        } else {
            Console::stdout('   > Couldn\'t add job in our system ' . PHP_EOL, Console::FG_RED);
        }

        return $success;
    }

    /**
     * @param string $jobRequestId
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function fetchJobRequestById(string $jobRequestId): bool
    {
        Console::stdout('Start job request fetch ' . $jobRequestId . PHP_EOL, Console::FG_CYAN);
        if (!$jobRequestId) {
            Craft::error('Job request ID is required');
            Console::stdout('Error on fetching job request: Job request ID is required' . PHP_EOL, Console::FG_RED);
            return false;
        }

        // Get job request by ID
        $jobRequest = Cockpit::$plugin->getApi()->getJobRequestById($jobRequestId);

        if (!$jobRequest) {
            Craft::error('Job request not found');
            Console::stdout('   > Error on fetching job request: Job request not found' . PHP_EOL, Console::FG_RED);
            return false;
        }

        $job = Job::find()->cockpitJobRequestId($jobRequestId)->one();

        if (!$job) {
            Craft::error('Job not found');
            Console::stdout('   > Error on fetching the publication (job) attached to the job request: Publication not found' . PHP_EOL, Console::FG_RED);
            return false;
        }

        // save native fields
        $job->cockpitCompanyId = $jobRequest['company']['id'] ?? null;
        $job->companyName = $jobRequest['company']['name'] ?? null;

        // save field layout fields
// @TODO: create a layer in between for mapping data between field layout and publication
        $mappings = collect([]);

        // loop through mappings to add the Cockpit data
        foreach($job->getFieldValues() as $field => $value) {
            $mapping = $mappings->get($field) ?? $value;
            $job->setFieldValue($field, $mapping);
        }

        // validate if the data matches our model
        if (!$job->validate()) {
            Console::stdout('   > Validation errors: ' . print_r($job->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Job element invalid', __METHOD__);
            return false;
        }

        // save the job
        if (!Craft::$app->elements->saveElement($job)) {
            Console::stdout('   > Error unable to save job: ' . print_r($job->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to save job', __METHOD__);
            return false;
        }

        Console::stdout('   > Job request saved for ' . $job->title . ' [' . $job->cockpitId . ']' . PHP_EOL);

        return true;
    }

    /**
     * @param Collection $publication
     * @return bool
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function upsertJob(Collection $publication): bool
    {
        // Temprorary check if the publication is from the website
        $publicationChannels = collect($publication->get('applicationInfo')['publicationChannels'] ?? []);
        if (!$publicationChannels->contains('id', Cockpit::$plugin->getSettings()->websitePublicationMatchFieldId)) {
            Console::stdout('   > Publication is not a website publication' . PHP_EOL, Console::FG_PURPLE);
            return false;
        }

        // set / create job
        $job = Job::find()->cockpitId($publication->get('id'))->one();
        if (!$job) {
            $job = new Job();
        }

        // set dates
        $startDate = ($publication->get('publicationDate')['start'] ?? null) ? Carbon::parse($publication->get('publicationDate')['start']) : Carbon::now();
        $calculationDate = $startDate->copy();
        $endDate = ($publication->get('publicationDate')['end'] ?? null) ? Carbon::parse($publication->get('publicationDate')['end']) : $calculationDate->addMonths(3);

        // if job end date is passed -> don't add
        if ($endDate && $endDate->isPast()) {
            return true;
        }

        // save native fields
        $job->cockpitCompanyId = $publication->get('jobRequest')['data']['company']['id'] ?? null;
        $job->cockpitId = $publication->get('id');
        $job->cockpitJobRequestId = $publication->get('jobRequest')['id'] ?? null;
        $job->cockpitDepartmentId = $publication->get('owner')['departmentId'] ?? null;
        $job->cockpitContactId = $publication->get('owner')['userId'] ?? null;
        $job->companyName = $publication->get('jobRequest')['data']['company']['name'] ?? null;
        $job->title = $publication->get('title');
        // $job->slug = StringHelper::slugify($publication->get('title') . '-' . $publication->get('id'));
        $job->postDate = $startDate;
        $job->expiryDate = $endDate;

        // upsert department
        if ($job->cockpitDepartmentId) {
            Cockpit::$plugin->getDepartments()->fetchDepartmentByCockpitId($job->cockpitDepartmentId);
        }

        // upsert contact
        if ($job->cockpitContactId) {
            Cockpit::$plugin->getContacts()->fetchContactByCockpitId($job->cockpitContactId);
        }

        // save field layout fields
// @TODO: create a layer in between for mapping data between field layout and publication
        $mappings = collect([
            'contractTypes' => null, // @TODO: match fields-> $publication->get('preferences')['contactTypes']
            'description' => $publication->get('descriptions')['summary'] ?? null,
            'educationLevels' => null, // @TODO: match fields -> $publication->get('preferences')['educationLevels']
            'employmentTypes' => null, // @TODO: match fields -> $publication->get('preferences')['employmentTypes']
            'experienceLevels' => null, // @TODO: match fields -> $publication->get('preferences')['experienceLevels']
            'extra' => $publication->get('descriptions')['extra'] ?? null,
            'fulltimeHours' => $publication->get('preferences')['hoursPerWeek']['max'] ?? null,
            'offer' => $publication->get('descriptions')['clientDescription'] ?? null,
            'parttimeHours' => $publication->get('preferences')['hoursPerWeek']['min'] ?? null,
            'reference' => '?',
            'salaryMax' => $publication->get('preferences')['salary']['max'] ?? null,
            'salaryMin' => $publication->get('preferences')['salary']['min'] ?? null,
            'salaryPeriod' => $publication->get('preferences')['salaryPeriod'] ?? null,
            'shift' => null, // @TODO: match fields -> $publication->get('preferences')['shiftServices']
            'skills' => $publication->get('descriptions')['requirementsDescriptions'] ?? null,
            'summary' => $publication->get('descriptions')['summary'] ?? null,
            'tasksAndProfiles' => $publication->get('descriptions')['functionDescription'] ?? null,
        ]);

        // loop through mappings to add the Cockpit data
        foreach($job->getFieldValues() as $field => $value) {
            $mapping = $mappings->get($field) ?? $value;
            $job->setFieldValue($field, $mapping);
        }

        // validate if the data matches our model
        if (!$job->validate()) {
            Console::stdout('   > Validation errors: ' . print_r($job->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Job element invalid', __METHOD__);
            return false;
        }

        // save the job
        if (!Craft::$app->elements->saveElement($job)) {
            Console::stdout('   > Error unable to save job: ' . print_r($job->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to save job', __METHOD__);
            return false;
        }

        // add address after a succesful save
        $this->_saveLocation($publication, $job);

        return true;
    }

    /**
     * @param string $cockpitId
     * @return bool
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function deleteJobByCockpitId(string $cockpitId): bool
    {
        Console::stdout('Start publication delete ' . $cockpitId . PHP_EOL, Console::FG_CYAN);
        $job = Job::find()->cockpitId($cockpitId)->one();

        if (!$job) {
            Console::stdout('   > Error unable to delete job because it doesn\'t exist ' . PHP_EOL, Console::FG_RED);
            Craft::error('Job not found', __METHOD__);
            return false;
        }

        $title = $job->title;

        if (!Craft::$app->elements->deleteElement($job)) {
            Console::stdout('   > Error unable to delete job: ' . print_r($job->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to delete job', __METHOD__);
            return false;
        }

        Console::stdout('   > Job deleted: ' . $title . ' [' . $cockpitId . ']' . PHP_EOL);

        return true;
    }

    /**
     * @param int $id
     * @return bool
     * @throws \Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function deleteJobById(int $id): bool
    {
        $job = Job::find()->id($id)->one();

        if (!$job) {
            Console::stdout('   > Error unable to delete job because it doesn\'t exist ' . PHP_EOL, Console::FG_RED);
            Craft::error('Job not found', __METHOD__);
            return false;
        }

        if (!Craft::$app->elements->deleteElement($job)) {
            Console::stdout('   > Error unable to delete job: ' . print_r($job->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to delete job', __METHOD__);
            return false;
        }

        Console::stdout('   > Job deleted: ' . $title . ' [' . $cockpitId . ']' . PHP_EOL);

        return true;
    }

    /**
     * @param ConfigEvent $event
     * @return void
     * @throws Exception
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

    /**
     * Save the location of the job and attach to the job
     * @param Job $job
     * @return void
     */
    private function _saveLocation(Collection $publication, Job $job): void
    {
        // gets address as a collection
        $address = $job->getAddress();

        // if collection is empty, create new. otherwise take first address (as we will only provide one)
        if ($address->isEmpty()) {
            $address = new Address();
        } else {
            $address = $address->first();
        }

        $address->setOwner($job);
        $address->setPrimaryOwner($job);

        $address->title = $publication->get('title') . ' - ' . $publication->get('jobRequest')['data']['company']['name'];
        $address->addressLine1 = ($publication->get('jobRequest')['data']['location']['street'] ?? null) . ' ' . ($publication->get('jobRequest')['data']['location']['housenumber'] ?? null);
        $address->addressLine2 = ($publication->get('jobRequest')['data']['location']['housenumberSuffix'] ?? null);
        $address->postalCode = ($publication->get('jobRequest')['data']['location']['zipcode'] ?? null);
        $city = Cockpit::$plugin->getPostcodes()->mapPostcode($address->postalCode);
        $address->locality = $city ? $city : ($publication->get('jobRequest')['data']['location']['city'] ?? null);
        $address->countryCode = $publication->get('jobRequest')['data']['location']['countryCode'] ?? null;

        if (Cockpit::$plugin->getSettings()->enableMapbox) {
            $addressString = "{$address->addressLine1} {$address->addressLine2} {$address->locality}, {$address->countryCode}";
            Console::stdout('   > Add address: ' . $addressString.PHP_EOL);
            $coords = Cockpit::$plugin->getMap()->getGeoPoints($addressString);

            if ($coords) {
                $address->latitude = $coords[1] ?? null;
                $address->longitude = $coords[0] ?? null;
            }
        }

        // save the address
        if (!Craft::$app->elements->saveElement($address)) {
            Console::stdout('   > Error unable to save address: ' . print_r($address->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to save address', __METHOD__);
        }
    }
}
