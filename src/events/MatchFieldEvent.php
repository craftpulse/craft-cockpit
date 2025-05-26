<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craftpulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\events;

use craftpulse\cockpit\models\MatchField;
use yii\base\Event;

/**
 * Matchfield type event class.
 */
class MatchFieldEvent extends Event
{
    /**
     * @var MatchField|null The match field model associated with the event.
     */
    public ?MatchField $matchField = null;

    /**
     * @var bool Whether the matchfield type is brand new
     */
    public bool $isNew = false;
}
