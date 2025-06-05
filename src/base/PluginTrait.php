<?php

namespace craftpulse\cockpit\base;

use Craft;
use craft\base\Element;
use craft\controllers\UsersController;
use craft\elements\Address;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\Tag;
use craft\elements\User;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineEditUserScreensEvent;
use craft\events\DefineFieldLayoutFieldsEvent;
use craft\events\DefineHtmlEvent;
use craft\events\ElementIndexTableAttributeEvent;
use craft\fieldlayoutelements\addresses\CountryCodeField;
use craft\fieldlayoutelements\addresses\LabelField;
use craft\fieldlayoutelements\addresses\LatLongField;
use craft\fieldlayoutelements\addresses\OrganizationField;
use craft\fieldlayoutelements\addresses\OrganizationTaxIdField;
use craft\fieldlayoutelements\assets\AltField;
use craft\fieldlayoutelements\assets\AssetTitleField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fieldlayoutelements\TextField;
use craft\fieldlayoutelements\TitleField;
use craft\fieldlayoutelements\users\AffiliatedSiteField;
use craft\fieldlayoutelements\users\EmailField;
use craft\fieldlayoutelements\users\FullNameField as UserFullNameField;
use craft\fieldlayoutelements\users\PhotoField;
use craft\fieldlayoutelements\users\UsernameField;
use craft\models\FieldLayout;

use craftpulse\cockpit\behaviors\CandidateBehaviour;
use craftpulse\cockpit\controllers\CandidateController;
use craftpulse\cockpit\elements\Contact;
use craftpulse\cockpit\elements\Department;
use craftpulse\cockpit\elements\Job;
use craftpulse\cockpit\elements\MatchFieldEntry;
use craftpulse\cockpit\fieldlayoutelements\AddressField;
use craftpulse\cockpit\fieldlayoutelements\AddressCoordinates;
use craftpulse\cockpit\fieldlayoutelements\matchfields\CockpitIdField;
use craftpulse\cockpit\fieldlayoutelements\matchfields\MatchFieldTitleField;

use craftpulse\cockpit\services\MatchField;
use yii\base\Behavior;
use yii\base\Event;
use yii\base\InvalidConfigException;

trait PluginTrait
{
    private function _registerUserFields(): void
    {
        Event::on(
            UsersController::class,
            UsersController::EVENT_DEFINE_EDIT_SCREENS,
            function(DefineEditUserScreensEvent $event) {
            if (Craft::$app->getUser()->checkPermission('cockpit:settings')) {
                $event->screens[CandidateController::SCREEN_COCKPIT] = [
                    'label' => Craft::t('cockpit', 'Cockpit'),
                ];
            }
        });
    }

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
//                case User::class:
//                    $event->fields[] = [
//                        'class' => TextField::class,
//                        'attribute' => 'cockpitId',
//                        'name' => 'cockpitId',
//                        'mandatory' => true,
//                        'label' => Craft::t('cockpit', 'Cockpit Candidate ID'),
//                        'width' => '100%',
//                    ];
//                    break;

                case Address::class:
                    $event->fields[] = AddressCoordinates::class;
                    break;

                case Contact::class:
                    $event->fields[] = TitleField::class;
                    break;

                case Job::class:
                case Department::class:
                    $event->fields[] = TitleField::class;
                    $event->fields[] = [
                        'class' => AddressField::class,
                        'attribute' => 'address',
                        'name' => 'address',
                        'mandatory' => true,
                        'label' => Craft::t('cockpit', 'Address'),
                        'width' => '100%',
                    ];
                    break;

                case MatchFieldEntry::class:
                    $event->fields[] = MatchFieldTitleField::class;
                    $event->fields[] = CockpitIdField::class;
                    break;
            }
        });
    }

    /**
     * Register Cockpit’s project config event listeners
     * @throws InvalidConfigException
     */
    private function _registerProjectConfigEventListeners(): void
    {
        $projectConfigService = Craft::$app->getProjectConfig();

        $jobsService = $this->getJobs();
        $projectConfigService->onAdd(self::CONFIG_JOB_FIELD_LAYOUT_KEY, [$jobsService, 'handleChangedFieldLayout'])
            ->onUpdate(self::CONFIG_JOB_FIELD_LAYOUT_KEY, [$jobsService, 'handleChangedFieldLayout'])
            ->onRemove(self::CONFIG_JOB_FIELD_LAYOUT_KEY, [$jobsService, 'handleDeletedFieldLayout']);

        $matchFieldsService = $this->getMatchFields();
        $projectConfigService->onAdd(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleChangedMatchField'])
            ->onUpdate(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleChangedMatchField'])
            ->onRemove(MatchField::CONFIG_MATCHFIELDS_KEY . '.{uid}', [$matchFieldsService, 'handleDeletedMatchField']);

        $departmentsService = $this->getDepartments();
        $projectConfigService->onAdd(self::CONFIG_DEPARTMENT_FIELD_LAYOUT_KEY, [$departmentsService, 'handleChangedFieldLayout'])
            ->onUpdate(self::CONFIG_DEPARTMENT_FIELD_LAYOUT_KEY, [$departmentsService, 'handleChangedFieldLayout'])
            ->onRemove(self::CONFIG_DEPARTMENT_FIELD_LAYOUT_KEY, [$departmentsService, 'handleDeletedFieldLayout']);

        $contactService = $this->getContacts();
        $projectConfigService->onAdd(self::CONFIG_CONTACT_FIELD_LAYOUT_KEY, [$contactService, 'handleChangedFieldLayout'])
            ->onUpdate(self::CONFIG_CONTACT_FIELD_LAYOUT_KEY, [$contactService, 'handleChangedFieldLayout'])
            ->onRemove(self::CONFIG_CONTACT_FIELD_LAYOUT_KEY, [$contactService, 'handleDeletedFieldLayout']);
    }

    private function _registerSidebarPanels(): void
    {
        $sidepanels = [
            [
                'element' => Job::class,
                'template' => 'cockpit/_components/_job-sidebar',
            ],
            [
                'element' => Department::class,
                'template' => 'cockpit/_components/_department-sidebar',
            ],
            [
                'element' => Contact::class,
                'template' => 'cockpit/_components/_contact-sidebar',
            ]
        ];

        foreach ($sidepanels as $panel) {
            Event::on(
                $panel['element'],
                Element::EVENT_DEFINE_SIDEBAR_HTML,
                function (DefineHtmlEvent $event) use ($panel) {
                    /** @var Element $element */
                    $element = $event->sender;

                    $data = [];

                    $html = Craft::$app->getView()->renderTemplate($panel['template'], array_merge([
                        'variable' => true,
                        'element' => $element,
                    ], $data));

                    $event->html .= $html;
                },
            );
        }
    }
}
