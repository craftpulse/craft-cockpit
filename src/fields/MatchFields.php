<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\fields;

use Craft;
use craft\base\ElementInterface;
use craft\fields\BaseRelationField;

use craftpulse\cockpit\elements\MatchFieldEntry;

/**
 * Class Api
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class MatchFields extends BaseRelationField
{
    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return 'tag';
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('cockpit', 'Cockpit Match fields');
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('cockpit', 'Add a match field');
    }

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        return parent::getInputHtml($value, $element);
    }

    /**
     * @inheritdoc
     */
    public static function elementType(): string
    {
        return MatchFieldEntry::class;
    }
}
