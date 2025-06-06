<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\variables;

use craftpulse\cockpit\Cockpit;
use nystudio107\pluginvite\variables\ViteVariableInterface;
use nystudio107\pluginvite\variables\ViteVariableTrait;

/**
 * Class CockpitVariable
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 */
class CockpitVariable implements ViteVariableInterface
{
    use ViteVariableTrait;

    // Public Methods
    // =========================================================================
    public function postcodeMapper(?string $postcode): ?string
    {
        if (!$postcode) {
            return null;
        }

        return Cockpit::$plugin->getPostcodes()->mapPostcode($postcode);
    }
}
