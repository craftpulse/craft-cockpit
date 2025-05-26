<?php
/**
 * Cockpit ATS plugin for Craft CMS
 *
 * This plugin fully synchronises with the Cockpit ATS system.
 *
 * @link      https://craft-pulse.com
 * @copyright Copyright (c) 2025 CraftPulse
 */

namespace craftpulse\cockpit\migrations;

use Craft;
use craft\db\Migration;
use craft\db\MigrationManager;
use craft\db\Table as CraftTable;
use craft\enums\PropagationMethod;
use craft\helpers\Db;

use craftpulse\cockpit\elements\Contact;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\elements\Contact as ContactElement;
use craftpulse\cockpit\elements\Job as JobElement;
use craftpulse\cockpit\elements\Office as OfficeElement;

class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var ?string The database driver to use
     */
    public ?string $driver = null;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @throws Exception|Throwable
     */
    public function safeUp(): bool
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;

        if ($this->createTables()) {
            $this->addForeignKeys();
            $this->createIndexes();
            //$this->addFieldLayouts();

            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropForeignKeys();
        $this->dropTables();
        $this->dropProjectConfig();

        $this->delete(CraftTable::FIELDLAYOUTS, ['type' => [ContactElement::class, JobElement::class, OfficeElement::class]]);

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables.
     *
     * @return bool
     * @throws Exception|Throwable
     */
    protected function createTables(): bool
    {
        if (!$this->db->tableExists(Table::CONTACTS)) {
            $this->createTable(
                TABLE::CONTACTS,
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'fieldLayoutId' => $this->integer(),

                    // connectors
                    'cockpitId' => $this->string()->notNull(),
                ]
            );
        }

        if(!$this->db->tableExists(Table::JOBS)) {
            $this->createTable(
                TABLE::JOBS,
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'fieldLayoutId' => $this->integer(),

                    // connectors
                    'cockpitId' => $this->string()->notNull(),
                ]
            );
        }

        if(!$this->db->tableExists(Table::OFFICES)) {
            $this->createTable(
                TABLE::OFFICES,
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'fieldLayoutId' => $this->integer(),

                    // connectors
                    'cockpitId' => $this->string()->notNull(),
                ]
            );
        }

        if(!$this->db->tableExists(Table::MATCHFIELD_TYPES)) {
            $this->createTable(Table::MATCHFIELD_TYPES, [
                'id' => $this->primaryKey(),
                'fieldLayoutId' => $this->integer(),
                'name' => $this->string()->notNull(),
                'handle' => $this->string()->notNull(),
                'enableVersioning' => $this->boolean()->defaultValue(false)->notNull(),
                'title' => $this->string()->notNull(),
                'titleFormat' => $this->string()->notNull(),
                'titleTranslationMethod' => $this->string()->defaultValue('site')->notNull(),
                'titleTranslationKeyFormat' => $this->string(),
                'propagationMethod' => $this->string()->defaultValue(PropagationMethod::All->value)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'cockpitId' => $this->string()->notNull(),
                'uid' => $this->uid(),
            ]);
        }

        return true;
    }

    /**
     * Creates the indexes.
     */
    public function createIndexes(): void
    {
        $this->createIndex(null, Table::CONTACTS, 'cockpitId', false);
        $this->createIndex(null, Table::JOBS, 'cockpitId', false);
        $this->createIndex(null, Table::OFFICES, 'cockpitId', false);
        $this->createIndex(null, Table::MATCHFIELD_TYPES, 'cockpitId', false);
    }

        /**
     * @return void
     */
    public function addForeignKeys(): void
    {
        if($this->db->tableExists(Table::CONTACTS)) {
            $this->addForeignKey(
                null,
                Table::CONTACTS,
                'id',
                CraftTable::ELEMENTS,
                'id',
                'CASCADE',
                null
            );
        }

        if($this->db->tableExists(Table::JOBS)) {
            $this->addForeignKey(
                null,
                Table::JOBS,
                'id',
                CraftTable::ELEMENTS,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        if($this->db->tableExists(Table::OFFICES)) {
            $this->addForeignKey(
                null,
                Table::OFFICES,
                'id',
                CraftTable::ELEMENTS,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        if($this->db->tableExists(Table::MATCHFIELD_TYPES)) {
            $this->addForeignKey(
                null,
                Table::MATCHFIELD_TYPES,
                'id',
                CraftTable::ELEMENTS,
                'id',
                'CASCADE',
                'CASCADE'
            );
        }
    }

    /**
     * @return void
     */
    public function dropForeignKeys(): void
    {
        if ($this->db->tableExists(TABLE::CONTACTS)) {
            Db::dropAllForeignKeysToTable(TABLE::CONTACTS);
        }

        if ($this->db->tableExists(TABLE::JOBS)) {
            Db::dropAllForeignKeysToTable(TABLE::JOBS);
        }

        if ($this->db->tableExists(Table::OFFICES)) {
            Db::dropAllForeignKeysToTable(Table::OFFICES);
        }

        if ($this->db->tableExists(Table::MATCHFIELD_TYPES)) {
            Db::dropAllForeignKeysToTable(Table::MATCHFIELD_TYPES);
        }
    }

    /**
     * @return void
     */
    public function dropTables(): void
    {
        if (Craft::$app->db->schema->getTableSchema(TABLE::CONTACTS)) {
            $this->dropTable(TABLE::CONTACTS);
        }

        if (Craft::$app->db->schema->getTableSchema(TABLE::JOBS)) {
            $this->dropTable(TABLE::JOBS);
        }

        if (Craft::$app->db->schema->getTableSchema(Table::OFFICES)) {
            $this->dropTable(Table::OFFICES);
        }

        if (Craft::$app->db->schema->getTableSchema(Table::MATCHFIELD_TYPES)) {
            $this->dropTable(Table::MATCHFIELD_TYPES);
        }
    }

    /**
     * Deletes the project config entry.
     */
    public function dropProjectConfig(): void
    {
        Craft::$app->projectConfig->remove('commerce');
    }
}
