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
use craft\helpers\Console;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\Contact;
use Illuminate\Support\Collection;
use yii\base\Exception;

/**
 * Class ContactsService
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class ContactsService extends Component
{
    /**
     * @param string $id
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \yii\base\InvalidConfigException
     */
    public function fetchContactByCockpitId(string $id): bool
    {
        if (!$id) {
            Craft::error('Contact ID is required');
            Console::stdout('Error on fetching contact: Contact ID is required' . PHP_EOL, Console::FG_RED);
            return false;
        }

        // Get contact by ID
        $contact = Cockpit::$plugin->getApi()->getUserById($id);

        if (!$contact) {
            Craft::error('Contact not found');
            Console::stdout('   > Error on fetching contact: Contact not found' . PHP_EOL, Console::FG_RED);
            return false;
        }

        Console::stdout('   > Contact ' . $contact->get('firstName') . ' ' . $contact->get('lastName') . ' found ' . PHP_EOL);

        $success = $this->upsertContact($contact);

        if ($success) {
            Console::stdout('   > Contact ' . $contact->get('firstName') . ' ' . $contact->get('lastName'). ' added / updated in our system ' . PHP_EOL, Console::FG_GREEN);
        } else {
            Console::stdout('   > Couldn\'t add contact in our system ' . PHP_EOL, Console::FG_RED);
        }

        return $success;
    }

    public function upsertContact(Collection $data): bool
    {
        // set / create contact
        $contact = Contact::find()->cockpitId($data->get('id'))->one();

        if (!$contact) {
            $contact = new Contact();
        }

        $contact->cockpitId = $data->get('id');
        $contact->title = $data->get('firstName') . ' ' . $data->get('lastName');
        $contact->firstName = $data->get('firstName') ?? null;
        $contact->lastName = $data->get('lastName') ?? null;
        $contact->email = $data->get('emailAddress') ?? null;
        $contact->phone = $data->get('publicPhoneNumber')['number'] ?? null;
        $contact->functionTitle = $data->get('functionTitle') ?? null;
        $contact->cockpitDepartmentIds = $data->get('departments') ?? null;

        if ($contact->cockpitDepartmentIds) {
            $contact->cockpitDepartmentIds = collect($contact->cockpitDepartmentIds)->pluck('id');
        }

// @TODO: create a layer in between for mapping data between field layout and publication
        $mappings = collect([]);

        // loop through mappings to add the Cockpit data
        foreach($contact->getFieldValues() as $field => $value) {
            $mapping = $mappings->get($field) ?? $value;
            $contact->setFieldValue($field, $mapping);
        }

        // validate if the data matches our model
        if (!$contact->validate()) {
            Console::stdout('   > Validation errors: ' . print_r($contact->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Contact element invalid', __METHOD__);
            return false;
        }

        // save the contact
        if (!Craft::$app->elements->saveElement($contact)) {
            Console::stdout('   > Error unable to save contact: ' . print_r($contact->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to save contact', __METHOD__);
            return false;
        }

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
            $fieldsService->deleteLayoutsByType(Contact::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Contact::class)->id;
        $layout->type = Contact::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);

        // Invalidate job caches
        Craft::$app->getElements()->invalidateCachesForElementType(Contact::class);
    }

    /**
     * @param ConfigEvent $event
     * @return void
     */
    public function handleDeletedFieldLayout(ConfigEvent $event): void
    {
        $fieldsService = Craft::$app->getFields();
        $fieldsService->deleteLayoutsByType(Contact::class);
    }
}
