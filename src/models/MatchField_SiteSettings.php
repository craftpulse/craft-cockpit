<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\models;

use Craft;
use craft\base\Model;
use craft\models\Site;
use craft\validators\SiteIdValidator;
use craft\validators\UriFormatValidator;

use craftpulse\cockpit\Cockpit;

use yii\base\InvalidConfigException;

/**
 * MatchField_SiteSettings model class.
 */
class MatchField_SiteSettings extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var int|null Matchfield ID
     */
    public ?int $matchFieldId = null;

    /**
     * @var int|null Site ID
     */
    public ?int $siteId = null;

    /**
     * @var bool Enabled by default
     */
    public bool $enabledByDefault = true;

    /**
     * @var bool Has URLs?
     */
    public bool $hasUrls = false;

    /**
     * @var string|null URI format
     */
    public ?string $uriFormat = null;

    /**
     * @var string|null Entry template
     */
    public ?string $template = null;

    /**
     * @var MatchField|null
     */
    private ?MatchField $_matchField = null;

    /**
     * Returns the match field.
     *
     * @return MatchField
     * @throws InvalidConfigException if [[matchFieldId]] is missing or invalid
     */
    public function getMatchField(): MatchField
    {
        if (isset($this->_matchField)) {
            return $this->_matchField;
        }

        if (!$this->matchFieldId) {
            throw new InvalidConfigException('Match field site settings model is missing its match field ID');
        }

        if (($this->_matchField = Cockpit::$plugin->getMatchFields()->getMatchFieldById($this->matchFieldId)) === null) {
            throw new InvalidConfigException('Invalid match field ID: ' . $this->matchFieldId);
        }

        return $this->_matchField;
    }

    /**
     * Sets the match field.
     *
     * @param MatchField $matchField
     */
    public function setMatchField(MatchField $matchField): void
    {
        $this->_matchField = $matchField;
    }

    /**
     * Returns the site.
     *
     * @return Site
     * @throws InvalidConfigException if [[siteId]] is missing or invalid
     */
    public function getSite(): Site
    {
        if (!$this->siteId) {
            throw new InvalidConfigException('Match field site settings model is missing its site ID');
        }

        if (($site = Craft::$app->getSites()->getSiteById($this->siteId)) === null) {
            throw new InvalidConfigException('Invalid site ID: ' . $this->siteId);
        }

        return $site;
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels(): array
    {
        $labels = [
            'template' => Craft::t('app', 'Template'),
        ];

        $labels['uriFormat'] = Craft::t('cockpit', 'Match field URI Format');

        return $labels;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['id', 'matchFieldId', 'siteId'], 'number', 'integerOnly' => true];
        $rules[] = [['siteId'], SiteIdValidator::class];
        $rules[] = [['uriFormat', 'template'], 'trim'];
        $rules[] = [['template'], 'string', 'max' => 500];
        $rules[] = ['uriFormat', UriFormatValidator::class];

        if ($this->hasUrls) {
            $rules[] = [['uriFormat'], 'required'];
        }

        return $rules;
    }
}
