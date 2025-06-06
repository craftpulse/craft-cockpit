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

/**
 *
 */
class Cockpit extends Crm
{
    /**
     * @var array|null
     */
    public ?array $applicationFieldMappings = null;
    /**
     * @var array|null
     */
    public ?array $applicationCandidateFieldMappings = null;
    /**
     * @var array|null
     */
    public ?array $applicationSpontaneousFieldMappings = null;
    /**
     * @var array|null
     */
    public ?array $applicationSpontaneousCandidateFieldMappings = null;

    /**
     * @var bool
     */
    public bool $mapToApplication = false;
    /**
     * @var bool
     */
    public bool $mapToApplicationCandidate = false;
    /**
     * @var bool
     */
    public bool $mapToSpontaneousApplication = false;
    /**
     * @var bool
     */
    public bool $mapToSpontaneousCandidateApplication = false;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('formie', 'Cockpit ATS');
    }

    /**
     * @return string
     */
    public function getIconUrl(): string
    {
        return Craft::$app->getAssetManager()->getPublishedUrl("@craftpulse/cockpit/icon.svg", true);
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return Craft::t('cockpit', 'This is a Cockpit application integration.');
    }

    /**
     * @return string
     * @throws Exception
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
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

    /**
     * @return IntegrationFormSettings
     * @throws \verbb\formie\errors\IntegrationException
     */
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
                    'name' => Craft::t('formie', 'Candidate phone country code (ISO)'),
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
                        'handle' => 'candidateId',
                        'name' => Craft::t('formie', 'Candidate ID'),
                        'required' => true,
                    ]),
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

            if ($this->mapToSpontaneousCandidateApplication) {
                $settings['application-spontaneous-candidate'] = array_merge([
                    new IntegrationField([
                        'handle' => 'candidateId',
                        'name' => Craft::t('formie', 'Candidate ID'),
                        'required' => true,
                    ]),
                ],$applicationFields);
            }
        } catch (Throwable $e) {
            Integration::apiError($this, $e);
        }

        return new IntegrationFormSettings($settings);
    }

    /**
     * @param Submission $submission
     * @return bool
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Throwable
     * @throws \craft\errors\InvalidFieldException
     * @throws \verbb\formie\errors\IntegrationException
     * @throws \yii\base\InvalidConfigException
     */
    public function sendPayload(Submission $submission): bool
    {
        try {
            $response = null;

            Console::stdout(PHP_EOL.PHP_EOL);
            Console::stdout('Send payload from Formie to Cockpit'.PHP_EOL);

            $applicationValues = $this->getFieldMappingValues($submission, $this->applicationFieldMappings, 'application');
            $applicationCandidateValues = $this->getFieldMappingValues($submission, $this->applicationCandidateFieldMappings, 'application-candidate');
            $applicationSpontaneousValues = $this->getFieldMappingValues($submission, $this->applicationSpontaneousFieldMappings, 'application-spontaneous');
            $applicationSpontaneousCandidateValues = $this->getFieldMappingValues($submission, $this->applicationSpontaneousCandidateFieldMappings, 'application-spontaneous-candidate');

            // application unknown canddiate
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

                Console::stdout('Start apply for job'.$applicationValues['publicationId'].PHP_EOL,Console::FG_CYAN);
                $response = Plugin::$plugin->getApplication()->applyForJob($applicationValues);
            }

            // application known candidate
            if ($this->mapToApplicationCandidate) {
                if ($applicationCandidateValues['cv'] ?? null) {
                    $cvField = preg_match('/\{field:(.*?)\}/', $this->applicationCandidateFieldMappings['cv'], $matches);
                    $fieldHandle = $matches[1] ?? null;

                    if ($fieldHandle) {
                        $fieldValue = $submission->getFieldValue($fieldHandle);

                        $cv = $fieldValue->one()->id ?? null;
                        $applicationCandidateValues['cv'] = $cv;
                    }
                }

                Console::stdout('Start apply for job known candidate '.$applicationCandidateValues['publicationId'].PHP_EOL,Console::FG_CYAN);
                $response = Plugin::$plugin->getApplication()->applyForJob($applicationCandidateValues);
            }

            // spontaneous application known candidate
            if ($this->mapToSpontaneousCandidateApplication) {
                if ($applicationSpontaneousCandidateValues['cv'] ?? null) {
                    $cvField = preg_match('/\{field:(.*?)\}/', $this->applicationSpontaneousCandidateValues['cv'], $matches);
                    $fieldHandle = $matches[1] ?? null;

                    if ($fieldHandle) {
                        $fieldValue = $submission->getFieldValue($fieldHandle);

                        $cv = $fieldValue->one()->id ?? null;
                        $applicationSpontaneousCandidateValues['cv'] = $cv;
                    }
                }

                Console::stdout('Start apply for spontaneous job known candidate '.$applicationSpontaneousCandidateValues['candidateId'].PHP_EOL,Console::FG_CYAN);
                $response = Plugin::$plugin->getApplication()->applyForSpontaneousJob($applicationSpontaneousCandidateValues);
            }


            // spontaneouspplication unknown canddiate
            if ($this->mapToSpontaneousApplication) {
                if ($applicationSpontaneousValues['cv'] ?? null) {
                    $cvField = preg_match('/\{field:(.*?)\}/', $this->applicationSpontaneousFieldMappings['cv'], $matches);
                    $fieldHandle = $matches[1] ?? null;

                    if ($fieldHandle) {
                        $fieldValue = $submission->getFieldValue($fieldHandle);

                        $cv = $fieldValue->one()->id ?? null;
                        $applicationSpontaneousValues['cv'] = $cv;
                    }
                }

                Console::stdout('Start apply for spontaneous job unkown candidate'.$applicationSpontaneousValues['email'].PHP_EOL,Console::FG_CYAN);
                $response = Plugin::$plugin->getApplication()->applyForSpontaneousJob($applicationSpontaneousValues);
            }

            if ($response) {
                Console::stdout('Success'.PHP_EOL,Console::FG_GREEN);
                return true;
            }

            Console::stdout(PHP_EOL.PHP_EOL);
        } catch (Throwable $e) {
            Integration::apiError($this, $e);
            Console::stdout('Formie error: '.$e->getMessage().PHP_EOL, Console::FG_RED);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function fetchConnection(): bool
    {
        if (Plugin::$plugin->getSettings()->apiKey && Plugin::$plugin->getSettings()->apiUrl) {
            return true;
        }

        return false;
    }

}
