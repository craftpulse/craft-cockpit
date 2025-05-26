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
use craft\base\Field;
use craft\base\Model;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

use craftpulse\cockpit\records\MatchfieldType as MatchfieldTypeRecord;

use yii\base\InvalidConfigException;

/**
 * Matchfield type model.
 *
 * @property string $cpEditUrl
 * @property string $cpEditVariantUrl
 */
class MatchfieldType extends Model
{
    /**
     * @var int|null ID
     */
    public ?int $id = null;

    /**
     * @var string|null Name
     */
    public ?string $name = null;

    /**
     * @var string|null Handle
     */
    public ?string $handle = null;

    /**
     * @var string|null Template
     */
    public ?string $template = null;

    /**
     * @var string Cockpit ID
     */
    public ?string $cockpitId = null;

    /**
     * @var string|null UID
     */
    public ?string $uid = null;

    /**
     * @return null|string
     */
    public function __toString()
    {
        return (string)$this->handle;
    }

    /**
     * @inerhitdoc
     */
    public function getHandle(): ?string
    {
        return $this->handle;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [['id'], 'number', 'integerOnly' => true],
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['handle'], UniqueValidator::class, 'targetClass' => MatchfieldTypeRecord::class, 'targetAttribute' => ['handle'], 'message' => 'Not Unique'],
            [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']],
        ];
    }

    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl('cockpit/settings/matchfieldtypes/' . $this->id);
    }

    /**
     * Returns the product types’s config.
     *
     * @return array
     * @since 5.2.0
     */
    public function getConfig(): array
    {
        $config = [
            'name' => $this->name,
            'handle' => $this->handle,
        ];

        //$config['matchfieldFieldLayouts'] = $generateLayoutConfig($this->getFieldLayout());

        return $config;
    }
}
