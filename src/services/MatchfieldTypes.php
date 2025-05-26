<?php

namespace craftpulse\cockpit\services;

use Craft;

/**
 * Class MatchfieldTypes
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
class MatchfieldTypes extends Component
{
    public const CONFIG_MATCHFIELDTYPES_KEY = 'cockpit.matchfieldTypes';

    /**
     * @var array|null
     */
    private ?array $_allMatchfieldTypes = null;
}
