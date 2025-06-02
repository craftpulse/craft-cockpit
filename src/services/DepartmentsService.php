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
use craft\fieldlayoutelements\TitleField;
use craft\helpers\ProjectConfig;
use craft\models\FieldLayout;
use craftpulse\cockpit\elements\Department;
use craftpulse\cockpit\fieldlayoutelements\AddressField;

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
     * This creates the native fields for the job section
     * @return array[]|null
     */
    public function createFields(): ?array
    {
        return [
            [
                'class' => TitleField::class,
                'attribute' => 'title',
                'name' => 'title',
                'label' => Craft::t('cockpit', 'Department'),
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

}
