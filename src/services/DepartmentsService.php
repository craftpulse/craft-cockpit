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
use craft\errors\ElementNotFoundException;
use craft\events\ConfigEvent;
use craft\fieldlayoutelements\TextField;
use craft\fieldlayoutelements\TitleField;
use craft\helpers\Console;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craftpulse\cockpit\Cockpit;
use craftpulse\cockpit\elements\Department;
use craftpulse\cockpit\fieldlayoutelements\AddressField;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Class DepartmentSerivce
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class DepartmentsService extends Component
{
    /**
     * @param string $id
     * @return bool
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws GuzzleException
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function fetchDepartmentByCockpitId(string $id): bool
    {
        if (!$id) {
            Craft::error('Department ID is required');
            Console::stdout('Error on fetching department: Publication ID is required' . PHP_EOL, Console::FG_RED);
            return false;
        }

        // Get department by ID
        $department = Cockpit::$plugin->getApi()->getDepartmentById($id);

        if (!$department) {
            Craft::error('Department not found');
            Console::stdout('   > Error on fetching department: Department not found' . PHP_EOL, Console::FG_RED);
            return false;
        }

        Console::stdout('   > Department ' . $department->get('name') . ' found ' . PHP_EOL);

        $success = $this->upsertDepartment($department);

        if ($success) {
            Console::stdout('   > Department ' . $department->get('name') . ' added / updated in our system ' . PHP_EOL, Console::FG_GREEN);
        } else {
            Console::stdout('   > Couldn\'t add department in our system ' . PHP_EOL, Console::FG_RED);
        }

        return $success;
    }

    /**
     * @param Collection $data
     * @return bool
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function upsertDepartment(Collection $data): bool
    {
        $department = Department::find()->cockpitId($data->get('id'))->one();

        if (!$department) {
            $department = new Department();
        }

        // save native fields
        $creationDate = $data->get('mutationData')['createdAt'] ?? null;
        $department->cockpitId = $data->get('id');
        $department->title = $data->get('name') ?? null;
        $department->email = $data->get('emailAddress') ?? null;
        $department->phone = $data->get('phoneNumber')['number'] ?? null;
        $department->reference = $data->get('referenceNumber') ?? null;
        $department->dateCreated = $creationDate ? Carbon::parse($creationDate) : Carbon::now();;

        // save field layout fields
// @TODO: create a layer in between for mapping data between field layout and publication
        $mappings = collect([]);

        // loop through mappings to add the Cockpit data
        foreach($department->getFieldValues() as $field => $value) {
            $mapping = $mappings->get($field) ?? $value;
            $department->setFieldValue($field, $mapping);
        }

        // validate if the data matches our model
        if (!$department->validate()) {
            Console::stdout('   > Validation errors: ' . print_r($department->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Department element invalid', __METHOD__);
            return false;
        }

        // save the department
        if (!Craft::$app->elements->saveElement($department)) {
            Console::stdout('   > Error unable to save department: ' . print_r($department->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to save department', __METHOD__);
            return false;
        }

        // add address after a succesful save
        $this->_saveLocation($data, $department);

        return true;
    }

    /**
     * @param string $cockpitId
     * @return bool
     * @throws Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function deleteDepartmentByCockpitId(string $cockpitId): bool
    {
        $department = Department::find()->cockpitId($cockpitId)->one();

        if (!$department) {
            Console::stdout('   > Error unable to delete department because it doesn\'t exist ' . PHP_EOL, Console::FG_RED);
            Craft::error('Department not found', __METHOD__);
            return false;
        }

        $title = $department->title;

        if (!Craft::$app->elements->deleteElement($department)) {
            Console::stdout('   > Error unable to delete department: ' . print_r($department->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to delete department', __METHOD__);
            return false;
        }

        Console::stdout('   > Department deleted: ' . $title . ' [' . $cockpitId . ']' . PHP_EOL);

        return true;
    }

    /**
     * @param int $id
     * @return bool
     * @throws Throwable
     * @throws \yii\base\InvalidConfigException
     */
    public function deleteJobById(int $id): bool
    {
        $department = Department::find()->id($id)->one();

        if (!$department) {
            Console::stdout('   > Error unable to delete department because it doesn\'t exist ' . PHP_EOL, Console::FG_RED);
            Craft::error('Department not found', __METHOD__);
            return false;
        }

        if (!Craft::$app->elements->deleteElement($department)) {
            Console::stdout('   > Error unable to delete department: ' . print_r($department->getErrors(), true) . PHP_EOL, Console::FG_RED);
            Craft::error('Unable to delete department', __METHOD__);
            return false;
        }

        Console::stdout('   > Department deleted: ' . $title . ' [' . $cockpitId . ']' . PHP_EOL);

        return true;
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
            $fieldsService->deleteLayoutsByType(Department::class);
            return;
        }

        // Save the field layout
        $layout = FieldLayout::createFromConfig(reset($data));
        $layout->id = $fieldsService->getLayoutByType(Department::class)->id;
        $layout->type = Department::class;
        $layout->uid = key($data);
        $fieldsService->saveLayout($layout, false);

        // Invalidate job caches
        Craft::$app->getElements()->invalidateCachesForElementType(Department::class);
    }

    /**
     * @param ConfigEvent $event
     * @return void
     */
    public function handleDeletedFieldLayout(ConfigEvent $event): void
    {
        $fieldsService = Craft::$app->getFields();
        $fieldsService->deleteLayoutsByType(Department::class);
    }

    /**
     * Save the location of the department and attach to the department
     * @param Collection $data
     * @param Department $department
     * @return void
     */
    private function _saveLocation(Collection $data, Department $department): void
    {
        // gets address as a collection
        $address = $department->getAddress();

        // if collection is empty, create new. otherwise take first address (as we will only provide one)
        if ($address->isEmpty()) {
            $address = new Address();
        } else {
            $address = $address->first();
        }

        $address->setOwner($department);
        $address->setPrimaryOwner($department);

        $address->title = $data->get('name') . ' - ' . $data->get('address')['city'];
        $address->addressLine1 = ($data->get('address')['street'] ?? null) . ' ' . ($data->get('address')['housenumber'] ?? null);
        $address->addressLine2 = ($data->get('address')['housenumberSuffix'] ?? null);
        $address->postalCode = ($data->get('address')['zipcode'] ?? null);
        $address->locality = ($data->get('address')['city'] ?? null);
        $address->countryCode = $data->get('address')['countryCode'] ?? null;

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
