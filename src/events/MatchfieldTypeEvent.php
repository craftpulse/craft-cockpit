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

use craftpulse\cockpit\models\MatchfieldType;
use yii\base\Event;

/**
 * Matchfield type event class.
 */
class MatchfieldTypeEvent extends Event
{
    /**
     * @var MatchfieldType|null The matchfield type model associated with the event.
     */
    public ?MatchfieldType $matchfieldType = null;

    /**
     * @var bool Whether the matchfield type is brand new
     */
    public bool $isNew = false;
}
