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
use craft\db\Table as CraftTable;
use craft\enums\PropagationMethod;
use craft\helpers\Db;
use craftpulse\cockpit\db\Table;
use craftpulse\cockpit\elements\Contact as ContactElement;
use craftpulse\cockpit\elements\MatchFieldEntry as MatchFieldEntryElement;
use craftpulse\cockpit\elements\Job as JobElement;
use craftpulse\cockpit\elements\Department as DepartmentElement;
use craftpulse\cockpit\models\MatchField;
use yii\base\Exception;

/**
 *
 */
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

        $this->delete(CraftTable::FIELDLAYOUTS, ['type' => [ContactElement::class, JobElement::class, DepartmentElement::class]]);

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Creates the tables.
     *
     * @return bool
     * @throws Exception|Throwable
     * @throws Exception
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
                    'postDate' => $this->dateTime(),
                    'expiryDate' => $this->dateTime(),
                    'fieldLayoutId' => $this->integer(),

                    // Job specific fields
                    'applicationCount' => $this->integer(),
                    'cockpitCompanyId' => $this->string()->notNull(),
                    'cockpitDepartmentId' => $this->string()->notNull(),
                    'cockpitId' => $this->string()->notNull(),
                    'cockpitJobRequestId' => $this->string()->notNull(),
                    'companyName' => $this->string()->notNull(),
                    'expiryDate' => $this->dateTime(),
                    'openPositions' => $this->integer(),
                    'title' => $this->string(),
                ]
            );
        }

        if(!$this->db->tableExists(Table::DEPARTMENTS)) {
            $this->createTable(
                TABLE::DEPARTMENTS,
                [
                    'id' => $this->primaryKey(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                    'fieldLayoutId' => $this->integer(),

                    // connectors
                    'cockpitId' => $this->string()->notNull(),
                    'email' => $this->string(),
                    'phone' => $this->string(),
                    'reference' => $this->string(),
                    'title' => $this->string(),
                ]
            );
        }

        if(!$this->db->tableExists(Table::MATCHFIELDS)) {
            $this->createTable(Table::MATCHFIELDS, [
                'id' => $this->primaryKey(),
                'structureId' => $this->integer(),
                'fieldLayoutId' => $this->integer(),
                'name' => $this->string()->notNull(),
                'handle' => $this->string()->notNull(),
                'type' => $this->string()->notNull(),
                'enableVersioning' => $this->boolean()->defaultValue(false)->notNull(),
                'propagationMethod' => $this->string()->defaultValue(PropagationMethod::All->value)->notNull(),
                'defaultPlacement' => $this->enum('defaultPlacement', [MatchField::DEFAULT_PLACEMENT_BEGINNING, MatchField::DEFAULT_PLACEMENT_END])->defaultValue('end')->notNull(),
                'previewTargets' => $this->json(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'dateDeleted' => $this->dateTime()->null(),
                'uid' => $this->uid(),
                'cockpitId' => $this->string()->notNull()->defaultValue(''),
            ]);
        }

        if(!$this->db->tableExists(Table::MATCHFIELDS_ENTRIES)) {
            $this->createTable(Table::MATCHFIELDS_ENTRIES, [
                'id' => $this->integer()->notNull(),
                'matchFieldId' => $this->integer(),
                'parentId' => $this->integer(),
                'primaryOwnerId' => $this->integer(),
                'fieldId' => $this->integer(),
                'postDate' => $this->dateTime(),
                'expiryDate' => $this->dateTime(),
                'status' => $this->enum('status', [
                    MatchFieldEntryElement::STATUS_ENABLED,
                ])->notNull()->defaultValue(MatchFieldEntryElement::STATUS_ENABLED),
                'deletedWithMatchField' => $this->boolean()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'PRIMARY KEY([[id]])',
            ]);
        }

        if(!$this->db->tableExists(Table::MATCHFIELDS_SITES)) {
            $this->createTable(Table::MATCHFIELDS_SITES, [
                'id' => $this->primaryKey(),
                'matchFieldId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'hasUrls' => $this->boolean()->defaultValue(true)->notNull(),
                'uriFormat' => $this->text(),
                'template' => $this->string(500),
                'enabledByDefault' => $this->boolean()->defaultValue(true)->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
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
        $this->createIndex(null, Table::DEPARTMENTS, 'cockpitId', false);
        $this->createIndex(null, Table::DEPARTMENTS, ['fieldLayoutId'], false);
        $this->createIndex(null, Table::JOBS, 'cockpitId', false);
        $this->createIndex(null, Table::JOBS, 'cockpitJobRequestId', false);
        $this->createIndex(null, Table::JOBS, 'cockpitDepartmentId', false);
        $this->createIndex(null, Table::JOBS, ['fieldLayoutId'], false);
        $this->createIndex(null, Table::DEPARTMENTS, 'cockpitId', false);
        $this->createIndex(null, Table::MATCHFIELDS, ['handle'], false);
        $this->createIndex(null, Table::MATCHFIELDS, ['name'], false);
        $this->createIndex(null, Table::MATCHFIELDS, ['structureId'], false);
        $this->createIndex(null, Table::MATCHFIELDS, ['fieldLayoutId'], false);
        $this->createIndex(null, Table::MATCHFIELDS, ['dateDeleted'], false);
        $this->createIndex(null, Table::MATCHFIELDS_ENTRIES, ['postDate'], false);
        $this->createIndex(null, Table::MATCHFIELDS_ENTRIES, ['expiryDate'], false);
        $this->createIndex(null, Table::MATCHFIELDS_ENTRIES, ['status'], false);
        $this->createIndex(null, Table::MATCHFIELDS_ENTRIES, ['matchFieldId'], false);
        $this->createIndex(null, Table::MATCHFIELDS_ENTRIES, ['primaryOwnerId'], false);
        $this->createIndex(null, Table::MATCHFIELDS_ENTRIES, ['fieldId'], false);
        $this->createIndex(null, Table::MATCHFIELDS_SITES, ['matchFieldId', 'siteId'], true);
        $this->createIndex(null, Table::MATCHFIELDS_SITES, ['siteId'], false);
    }

        /**
     * @return void
     */
    public function addForeignKeys(): void
    {
        $this->addForeignKey(null, Table::CONTACTS, 'id', CraftTable::ELEMENTS, 'id', 'CASCADE', null);
        $this->addForeignKey(null, Table::DEPARTMENTS, ['fieldLayoutId'], CraftTable::FIELDLAYOUTS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::JOBS, 'id', CraftTable::ELEMENTS, 'id', 'CASCADE', null);
        $this->addForeignKey(null, Table::JOBS, ['fieldLayoutId'], CraftTable::FIELDLAYOUTS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::DEPARTMENTS, 'id', CraftTable::ELEMENTS, 'id', 'CASCADE', null);
        $this->addForeignKey(null, Table::MATCHFIELDS, ['structureId'], CraftTable::STRUCTURES, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::MATCHFIELDS, ['fieldLayoutId'], CraftTable::FIELDLAYOUTS, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::MATCHFIELDS_ENTRIES, ['id'], CraftTable::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::MATCHFIELDS_ENTRIES, ['matchFieldId'], Table::MATCHFIELDS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::MATCHFIELDS_ENTRIES, ['parentId'], Table::MATCHFIELDS_ENTRIES, ['id'], 'SET NULL', null);
        $this->addForeignKey(null, Table::MATCHFIELDS_ENTRIES, ['fieldId'], CraftTable::FIELDS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::MATCHFIELDS_ENTRIES, ['primaryOwnerId'], CraftTable::ELEMENTS, ['id'], 'CASCADE', null);
        $this->addForeignKey(null, Table::MATCHFIELDS_SITES, ['siteId'], CraftTable::SITES, ['id'], 'CASCADE', 'CASCADE');
        $this->addForeignKey(null, Table::MATCHFIELDS_SITES, ['matchFieldId'], Table::MATCHFIELDS, ['id'], 'CASCADE', null);
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

        if ($this->db->tableExists(Table::DEPARTMENTS)) {
            Db::dropAllForeignKeysToTable(Table::DEPARTMENTS);
        }

        if ($this->db->tableExists(Table::MATCHFIELDS)) {
            Db::dropAllForeignKeysToTable(Table::MATCHFIELDS);
        }

        if ($this->db->tableExists(Table::MATCHFIELDS_ENTRIES)) {
            Db::dropAllForeignKeysToTable(Table::MATCHFIELDS_ENTRIES);
        }

        if ($this->db->tableExists(Table::MATCHFIELDS_SITES)) {
            Db::dropAllForeignKeysToTable(Table::MATCHFIELDS_SITES);
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

        if (Craft::$app->db->schema->getTableSchema(Table::DEPARTMENTS)) {
            $this->dropTable(Table::DEPARTMENTS);
        }

        if (Craft::$app->db->schema->getTableSchema(Table::MATCHFIELDS)) {
            $this->dropTable(Table::MATCHFIELDS);
        }

        if (Craft::$app->db->schema->getTableSchema(Table::MATCHFIELDS_ENTRIES)) {
            $this->dropTable(Table::MATCHFIELDS_ENTRIES);
        }

        if (Craft::$app->db->schema->getTableSchema(Table::MATCHFIELDS_SITES)) {
            $this->dropTable(Table::MATCHFIELDS_SITES);
        }
    }

    /**
     * Deletes the project config entry.
     */
    public function dropProjectConfig(): void
    {
        Craft::$app->projectConfig->remove('cockpit');
    }
}
