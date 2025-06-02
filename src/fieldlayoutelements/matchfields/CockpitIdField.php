<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\fieldlayoutelements\matchfields;

use craft\base\ElementInterface;
use craft\fieldlayoutelements\TextField;

use craftpulse\cockpit\elements\MatchFieldEntry;

use yii\base\InvalidArgumentException;

/**
 * Class MatchFieldIdField
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class CockpitIdField extends TextField
{
    /**
     * @inheritdoc
     */
    public string $attribute = 'matchFieldId';

    /**
     * @var bool Whether the input should get a `readonly` attribute.
     */
    public bool $readonly = true;

    /**
     * @inheritdoc
     */
    public function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof MatchFieldEntry) {
            throw new InvalidArgumentException(sprintf('%s can only be used in match field field layouts.', self::class));
        }

        return parent::inputHtml($element, $static);
    }
}
