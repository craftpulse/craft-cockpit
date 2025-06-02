<?php

namespace craftpulse\cockpit\base;

use Craft;
use craft\elements\Address;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\fieldlayoutelements\addresses\AddressField;
use craft\fieldlayoutelements\addresses\CountryCodeField;
use craft\fieldlayoutelements\addresses\LabelField;
use craft\fieldlayoutelements\addresses\LatLongField;
use craft\fieldlayoutelements\addresses\OrganizationField;
use craft\fieldlayoutelements\addresses\OrganizationTaxIdField;
use craft\fieldlayoutelements\assets\AltField;
use craft\fieldlayoutelements\assets\AssetTitleField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fieldlayoutelements\FullNameField;
use craft\fieldlayoutelements\TitleField;
use craft\fieldlayoutelements\users\AffiliatedSiteField;
use craft\fieldlayoutelements\users\EmailField;
use craft\fieldlayoutelements\users\FullNameField as UserFullNameField;
use craft\fieldlayoutelements\users\PhotoField;
use craft\fieldlayoutelements\users\UsernameField;
use craft\models\FieldLayout;

use craftpulse\cockpit\elements\MatchFieldEntry;
use craftpulse\cockpit\fieldlayoutelements\matchfields\MatchFieldIdField;
use craftpulse\cockpit\fieldlayoutelements\matchfields\MatchFieldTitleField;

use craftpulse\cockpit\services\MatchField;
use yii\base\Event;

trait PluginTrait
{
    /**
     * Register event listeners for field layouts.
     */
    private function _registerFieldLayoutListener(): void
    {
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_NATIVE_FIELDS,
            function(DefineFieldLayoutFieldsEvent $event) {
            /** @var FieldLayout $fieldLayout */
            $fieldLayout = $event->sender;

                switch ($fieldLayout->type) {
                    case MatchFieldEntry::class:
                        $event->fields[] = MatchFieldTitleField::class;
                        $event->fields[] = MatchFieldIdField::class;
                        break;
                }
        });
    }

    /**
     * Register Cockpit’s project config event listeners
     */
    private function _registerProjectConfigEventListeners(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        $jobsService = $this->getJobs();
        $projectConfigService->onAdd(self::CONFIG_JOBFIELD_LAYOUT_KEY, [$jobsService, 'handleChangedFieldLayout'])
            ->onUpdate(self::CONFIG_JOBFIELD_LAYOUT_KEY, [$jobsService, 'handleChangedFieldLayout'])
            ->onRemove(self::CONFIG_JOBFIELD_LAYOUT_KEY, [$jobsService, 'handleDeletedFieldLayout']);

        $matchFieldsService = $this->getMatchFields();
        $projectConfigService->onAdd(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleChangedMatchField'])
            ->onUpdate(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleChangedMatchField'])
            ->onRemove(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleDeletedMatchField']);
    }
}
