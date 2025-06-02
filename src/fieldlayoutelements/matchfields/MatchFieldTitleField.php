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
use craft\fieldlayoutelements\TitleField;

use craftpulse\cockpit\elements\MatchFieldEntry;

use yii\base\InvalidArgumentException;

/**
 * Class MatchFieldTitleField
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class MatchFieldTitleField extends TitleField
{
    /**
     * @inheritdoc
     */
    public string $attribute = 'title';

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
