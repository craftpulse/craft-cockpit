<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craftpulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\assetbundles\cockpit;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Class MatchFieldAsset
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class MatchFieldAsset extends AssetBundle
{
// Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = '@craftpulse/cockpit/web/assets/dist/';
        $this->depends = [
            CpAsset::class,
            CockpitCpAsset::class,
        ];

        parent::init();
    }
}
