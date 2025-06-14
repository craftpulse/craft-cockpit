<?php

namespace craftpulse\cockpit\db;

/**
 * Abstract class Table
 *
 * @author      CraftPulse
 * @package     Cockpit
 * @since       5.0.0
 *
 */
abstract class Table
{
    // Public Constants
    // =========================================================================

    public const CONTACTS = "{{%cockpit_contacts}}";
    public const DEPARTMENTS = "{{%cockpit_departments}}";
    public const JOBS = "{{%cockpit_jobs}}";
    public const MATCHFIELDS = "{{%cockpit_matchfields}}";
    public const MATCHFIELDS_ENTRIES = "{{%cockpit_matchfields_entries}}";
    public const MATCHFIELDS_SITES = "{{%cockpit_matchfields_sites}}";
    public const POSTCODE_MAPPINGS = "{{%cockpit_postcode_mappings}}";
    public const CANDIDATES = "{{%cockpit_candidates}}";
}
