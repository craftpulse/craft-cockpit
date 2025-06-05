<?php

namespace craftpulse\cockpit\integrations\formie;

use Craft;
use craft\helpers\Console;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use verbb\formie\base\Crm;
use verbb\formie\base\Integration;
use verbb\formie\elements\Submission;
use verbb\formie\models\IntegrationCollection;
use verbb\formie\models\IntegrationField;
use verbb\formie\models\IntegrationFormSettings;
use yii\base\Exception;
use craftpulse\cockpit\Cockpit as Plugin;

class Cockpit extends Crm
{
    /**
     * @var array|null
     */
    public ?array $applicationFieldMappings = null;
    public ?array $applicationCanddiateFieldMappings = null;
    public ?array $applicationSpontaneousFieldMappings = null;

    public bool $mapToApplication = false;
    public bool $mapToApplicationCandidate = false;
    public bool $mapToSpontaneousApplication = false;

    public static function displayName(): string
    {
        return Craft::t('formie', 'Cockpit ATS');
    }

    public function getIconUrl(): string
    {
        return Craft::$app->getAssetManager()->getPublishedUrl("@craftpulse/cockpit/icon.svg", true);
    }

    public function getDescription(): string
    {
        return Craft::t('cockpit', 'This is a Cockpit application integration.');
    }

    public function getSettingsHtml(): string
    {
        $variables = $this->getSettingsHtmlVariables();

        return Craft::$app->getView()->renderTemplate('cockpit/integrations/formie/_plugin-settings', $variables);
    }

    /**
     * @param $form
     * @return string
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getFormSettingsHtml($form): string
    {
        $formSettings = $this->getFormSettingsHtmlVariables($form);

        return Craft::$app->getView()->renderTemplate('cockpit/integrations/formie/_form-settings', $formSettings);
    }

    public function fetchFormSettings(): IntegrationFormSettings
    {
        $settings = [];

        try {

            $candidateFields = [
                new IntegrationField([
                    'handle' => 'firstName',
                    'name' => Craft::t('formie', 'Candidate first name'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'lastName',
                    'name' => Craft::t('formie', 'Candidate last name'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'email',
                    'name' => Craft::t('formie', 'Candidate email address'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'phoneCountry',
                    'name' => Craft::t('formie', 'Candidate phone country code'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'phoneNumber',
                    'name' => Craft::t('formie', 'Candidate phone number'),
                    'required' => true,
                ]),
            ];

            $applicationFields = [
                new IntegrationField([
                    'handle' => 'departmentId',
                    'name' => Craft::t('formie', 'Department ID'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'emailCommunicationConsent',
                    'name' => Craft::t('formie', 'Consent email communicaiton'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'allowMediation',
                    'name' => Craft::t('formie', 'Consent mediation'),
                    'required' => true,
                ]),
                new IntegrationField([
                    'handle' => 'cv',
                    'name' => Craft::t('formie', 'CV'),
                ]),
                new IntegrationField([
                    'handle' => 'utmSource',
                    'name' => Craft::t('formie', 'UTM Source'),
                ]),
                new IntegrationField([
                    'handle' => 'utmMedium',
                    'name' => Craft::t('formie', 'UTM Medium'),
                ]),
                new IntegrationField([
                    'handle' => 'utmCampaign',
                    'name' => Craft::t('formie', 'UTM Campaign'),
                ]),
                new IntegrationField([
                    'handle' => 'utmTerm',
                    'name' => Craft::t('formie', 'UTM Term'),
                ]),
                new IntegrationField([
                    'handle' => 'utmContent',
                    'name' => Craft::t('formie', 'UTM Content'),
                ]),
            ];

            if ($this->mapToApplication) {
                $settings['application'] = array_merge($candidateFields, array_merge([
                    new IntegrationField([
                        'handle' => 'publicationId',
                        'name' => Craft::t('formie', 'Publication ID'),
                        'required' => true,
                    ]),
                ],$applicationFields));
            }

            if ($this->mapToApplicationCandidate) {
                $settings['application-candidate'] = array_merge([
                    new IntegrationField([
                        'handle' => 'publicationId',
                        'name' => Craft::t('formie', 'Publication ID'),
                        'required' => true,
                    ]),
                ],$applicationFields);
            }

            if ($this->mapToSpontaneousApplication) {
                $settings['application-spontaneous'] = array_merge($candidateFields,$applicationFields);
            }
        } catch (Throwable $e) {
            Integration::apiError($this, $e);
        }

        return new IntegrationFormSettings($settings);
    }

    public function sendPayload(Submission $submission): bool
    {
        try {
            $response = null;

            Console::stdout(PHP_EOL.PHP_EOL);

            $applicationValues = $this->getFieldMappingValues($submission, $this->applicationFieldMappings, 'application');
            $applicationCandidateValues = $this->getFieldMappingValues($submission, $this->applicationCanddiateFieldMappings, 'application-candidate');
            $applicationSpontaneousValues = $this->getFieldMappingValues($submission, $this->applicationSpontaneousFieldMappings, 'application-spontaneous');

            if ($this->mapToApplication) {
                if ($applicationValues['cv'] ?? null) {
                    $cvField = preg_match('/\{field:(.*?)\}/', $this->applicationFieldMappings['cv'], $matches);
                    $fieldHandle = $matches[1] ?? null;

                    if ($fieldHandle) {
                        $fieldValue = $submission->getFieldValue($fieldHandle);

                        $cv = $fieldValue->one()->id ?? null;
                        $applicationValues['cv'] = $cv;
                    }
                }


                Console::stdout('Start apply for job '.$applicationValues['publicationId'].PHP_EOL,Console::FG_CYAN);
                $response = Plugin::$plugin->getApplication()->applyForJob($applicationValues);
            }

            if ($response) {
                Console::stdout('Success'.PHP_EOL,Console::FG_GREEN);
                return true;
            }

            Console::stdout(PHP_EOL.PHP_EOL);

            return false;
        } catch (Throwable $e) {
            Integration::apiError($this, $e);

            return false;
        }

        return true;
    }

    public function fetchConnection(): bool
    {
        if (Plugin::$plugin->getSettings()->apiKey && Plugin::$plugin->getSettings()->apiUrl) {
            return true;
        }

        return false;
    }

}
