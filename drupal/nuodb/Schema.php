<?php

/**
 * @file
 * Definition of Drupal\Core\Database\Driver\nuodb\Schema
 */

namespace Drupal\Core\Database\Driver\nuodb;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\Database\SchemaObjectDoesNotExistException;
use Drupal\Core\Database\Schema as DatabaseSchema;

use Exception;

/**
 * @addtogroup schemaapi
 * @{
 */

class Schema extends DatabaseSchema {

  /**
   * Maximum length of a table comment in Nuodb.
   */
  const COMMENT_MAX_TABLE = 60;

  /**
   * Maximum length of a column comment in Nuodb.
   */
  const COMMENT_MAX_COLUMN = 255;

  /**
   * Get information about the table and database name from the prefix.
   *
   * @return
   *   A keyed array with information about the database, table name and prefix.
   */
/*
  protected function getPrefixInfo($table = 'default', $add_prefix = TRUE) {
    $info = array('prefix' => $this->connection->tablePrefix($table));
    if ($add_prefix) {
      $table = $info['prefix'] . $table;
    }
    if (($pos = strpos($table, '.')) !== FALSE) {
      $info['database'] = substr($table, 0, $pos);
      $info['table'] = substr($table, ++$pos);
    }
    else {
      $db_info = Database::getConnectionInfo();
      $info['database'] = $db_info['default']['database'];
      $info['table'] = $table;
    }
    return $info;
  }
*/
  /**
   * Build a condition to match a table name against a standard information_schema.
   *
   * Nuodb uses databases like schemas rather than catalogs so when we build
   * a condition to query the information_schema.tables, we set the default
   * database as the schema unless specified otherwise, and exclude table_catalog
   * from the condition criteria.
   */
/*
  protected function buildTableNameCondition($table_name, $operator = '=', $add_prefix = TRUE) {
    $info = $this->connection->getConnectionOptions();

    $table_info = $this->getPrefixInfo($table_name, $add_prefix);

    $condition = new Condition('AND');
    $condition->condition('table_schema', $table_info['database']);
    $condition->condition('table_name', $table_info['table'], $operator);
    return $condition;
  }
*/

  protected function _createIndexSql($table, $name, $fields) {
    $query = 'CREATE INDEX "' . $this->prefixNonTable($table, $name, 'idx') . '" ON {' . $table . '} (';
    $query .= $this->createKeySql($fields) . ')';
    return $query;
  }

  protected function _createUniqueSql($table, $name, $fields) {
    $query = 'CREATE UNIQUE INDEX "' . $this->prefixNonTable($table, $name, 'idx') . '" ON {' . $table . '} (';
    $query .= $this->createKeySql($fields) . ')';
    return $query;
  }

  /**
   * Generate SQL to create a new table from a Drupal schema definition.
   *
   * @param $name
   *   The name of the table to create.
   * @param $table
   *   A Schema API table definition array.
   * @return
   *   An array of SQL statements to create the table.
   */

  protected function _old_createTableSql($name, $table) {
    $info = $this->connection->getConnectionOptions();

    $sql = "CREATE TABLE {" . $name . "} (\n";

    // Add the SQL statement for each field.
    foreach ($table['fields'] as $field_name => $field) {
      $sql .= $this->createFieldSql($field_name, $this->processField($field)) . ", \n";
    }

    // Process keys & indexes.
    $keys = $this->createKeysSql($table);
    if (count($keys)) {
      $sql .= implode(", \n", $keys) . ", \n";
    }

    // Remove the last comma and space.
    $sql = substr($sql, 0, -3) . "\n) ";

    return array($sql);
  }

  protected function createTableSql($name, $table) {
    $sql_fields = array();
    foreach ($table['fields'] as $field_name => $field) {
      $sql_fields[] = $this->createFieldSql($field_name, $this->processField($field));
    }

    $sql_keys = array();
    if (isset($table['primary key']) && is_array($table['primary key'])) {
      $sql_keys[] = 'PRIMARY KEY (' . implode(', ', $table['primary key']) . ')';
    }

    $sql = "CREATE TABLE {" . $name . "} (\n\t";
    $sql .= implode(",\n\t", $sql_fields);
    if (count($sql_keys) > 0) {
      $sql .= ",\n\t";
    }
    $sql .= implode(",\n\t", $sql_keys);
    $sql .= "\n)";
    $statements[] = $sql;

    if (isset($table['indexes']) && is_array($table['indexes'])) {
      foreach ($table['indexes'] as $key_name => $key) {
        $statements[] = $this->_createIndexSql($name, $key_name, $key);
      }
    }

    if (isset($table['unique_keys']) && is_array($table['unique_keys'])) {
      foreach ($table['unique'] as $key_name => $key) {
        $statements[] = $this->_createUniqueSql($name, $key_name, $key);
      }
    }

    // Add table comment.
//    if (!empty($table['description'])) {
//      $statements[] = 'COMMENT ON TABLE {' . $name . '} IS ' . $this->prepareComment($table['description']);
//    }

    // Add column comments.
//    foreach ($table['fields'] as $field_name => $field) {
//      if (!empty($field['description'])) {
//        $statements[] = 'COMMENT ON COLUMN {' . $name . '}.' . $field_name . ' IS ' . $this->prepareComment($field['description']);
//      }
//    }

    return $statements;
  }


  /**
   * Create an SQL string for a field to be used in table creation or alteration.
   *
   * Before passing a field out of a schema definition into this function it has
   * to be processed by _db_process_field().
   *
   * @param $name
   *   Name of the field.
   * @param $spec
   *   The field specification, as per the schema data structure format.
   */
  protected function createFieldSql($name, $spec) {
//    $sql = "`" . $name . "` " . $spec['nuodb_type'];  NuoDB can't handle backticks -- it corrupts the metadata. DB-2062
    $sql = $name . " " . $spec['nuodb_type'];

    if (in_array($spec['nuodb_type'], array('VARCHAR', 'CHAR', 'TINYTEXT', 'MEDIUMTEXT', 'LONGTEXT', 'TEXT'))) {
      if (isset($spec['length'])) {
        $sql .= '(' . $spec['length'] . ')';
      }
    }
    elseif (isset($spec['precision']) && isset($spec['scale'])) {
      $sql .= '(' . $spec['precision'] . ', ' . $spec['scale'] . ')';
    }

    // TODO: disable "unsigned" because NuoDB does not support it.
//    if (!empty($spec['unsigned'])) {
//      $sql .= ' unsigned';
//    }
//    if (!empty($spec['unsigned'])) {
//      $sql .= " CHECK ($name >= 0)";
//    }

    if (isset($spec['not null'])) {
      if ($spec['not null']) {
        $sql .= ' NOT NULL';
      }
      else {
        $sql .= ' NULL';
      }
    }

    if (!empty($spec['auto_increment'])) {
      $sql .= ' GENERATED ALWAYS AS IDENTITY';
    }

    // $spec['default'] can be NULL, so we explicitly check for the key here.
    if (array_key_exists('default', $spec)) {
      if (is_string($spec['default'])) {
        $spec['default'] = "'" . $spec['default'] . "'";
      }
      elseif (!isset($spec['default'])) {
        $spec['default'] = 'NULL';
      }
      $sql .= ' DEFAULT ' . $spec['default'];
    }

    if (empty($spec['not null']) && !isset($spec['default'])) {
      $sql .= ' DEFAULT NULL';
    }

    return $sql;
  }

  /**
   * Set database-engine specific properties for a field.
   *
   * @param $field
   *   A field description array, as specified in the schema documentation.
   */
  protected function processField($field) {

    if (!isset($field['size'])) {
      $field['size'] = 'normal';
    }

    // Set the correct database-engine specific datatype.
    // In case one is already provided, force it to uppercase.
    if (isset($field['nuodb_type'])) {
      $field['nuodb_type'] = drupal_strtoupper($field['nuodb_type']);
    }
    else {
      $map = $this->getFieldTypeMap();
      $field['nuodb_type'] = $map[$field['type'] . ':' . $field['size']];
    }

    if (isset($field['type']) && $field['type'] == 'serial') {
      $field['auto_increment'] = TRUE;
    }

    return $field;
  }

  public function getFieldTypeMap() {
    // Put :normal last so it gets preserved by array_flip. This makes
    // it much easier for modules (such as schema.module) to map
    // database types back into schema types.
    // $map does not use drupal_static as its value never changes.
    static $map = array(
      'varchar:normal'  => 'VARCHAR',
      'char:normal'     => 'CHAR',

      'text:tiny'       => 'TEXT',
      'text:small'      => 'TEXT',
      'text:medium'     => 'TEXT',
      'text:big'        => 'TEXT',
      'text:normal'     => 'TEXT',

      'serial:tiny'     => 'SMALLINT',
      'serial:small'    => 'SMALLINT',
      'serial:medium'   => 'INTEGER',
      'serial:big'      => 'BIGINT',
      'serial:normal'   => 'INT',

      'int:tiny'        => 'SMALLINT',
      'int:small'       => 'SMALLINT',
      'int:medium'      => 'INTEGER',
      'int:big'         => 'BIGINT',
      'int:normal'      => 'INT',

      'float:tiny'      => 'REAL',
      'float:small'     => 'REAL',
      'float:medium'    => 'REAL',
      'float:big'       => 'DOUBLE PRECISION',
      'float:normal'    => 'REAL',

      'numeric:normal'  => 'DECIMAL',

      'blob:big'        => 'BLOB',
      'blob:normal'     => 'BLOB',
    );
    return $map;
  }

  protected function createKeysSql($spec) {
    $keys = array();

    if (!empty($spec['primary key'])) {
      $keys[] = 'PRIMARY KEY (' . $this->createKeysSqlHelper($spec['primary key']) . ')';
    }
    if (!empty($spec['unique keys'])) {
      foreach ($spec['unique keys'] as $key => $fields) {
//        $keys[] = 'UNIQUE `' . $key . '` (' . $this->createKeysSqlHelper($fields) . ')';  DB-2062
        $keys[] = 'UNIQUE ' . $key . ' (' . $this->createKeysSqlHelper($fields) . ')';
      }
    }
    if (!empty($spec['indexes'])) {
      foreach ($spec['indexes'] as $index => $fields) {
//        $keys[] = 'INDEX `' . $index . '` (' . $this->createKeysSqlHelper($fields) . ')';  DB-2062
        $keys[] = 'INDEX ' . $index . ' (' . $this->createKeysSqlHelper($fields) . ')';
      }
    }

    return $keys;
  }

  protected function createKeySql($fields) {
    $return = array();
    foreach ($fields as $field) {
      if (is_array($field)) {
//        $return[] = '`' . $field[0] . '`(' . $field[1] . ')';  DB-2062
        $return[] = $field[0] . '(' . $field[1] . ')';
      }
      else {
//        $return[] = '`' . $field . '`';
        $return[] =  $field;
      }
    }
    return implode(', ', $return);
  }

  protected function createKeysSqlHelper($fields) {
    $return = array();
    foreach ($fields as $field) {
      if (is_array($field)) {
//        $return[] = '`' . $field[0] . '`(' . $field[1] . ')';  DB-2062
        $return[] = $field[0] . '(' . $field[1] . ')';
      }
      else {
//        $return[] = '`' . $field . '`';
        $return[] = $field;
      }
    }
    return implode(', ', $return);
  }

  public function renameTable($table, $new_name) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException(t("Cannot rename @table to @table_new: table @table doesn't exist.", array('@table' => $table, '@table_new' => $new_name)));
    }
    if ($this->tableExists($new_name)) {
      throw new SchemaObjectExistsException(t("Cannot rename @table to @table_new: table @table_new already exists.", array('@table' => $table, '@table_new' => $new_name)));
    }

    $info = $this->getPrefixInfo($new_name);
//    return $this->connection->query('RENAME TABLE {' . $table . '} TO `' . $info['table'] . '`');  DB-2062
    return $this->connection->query('RENAME TABLE {' . $table . '} TO ' . $info['table'] );
  }

  public function dropTable($table) {
    if (!$this->tableExists($table)) {
      return FALSE;
    }

    $this->connection->query('DROP TABLE {' . $table . '}');
    return TRUE;
  }

  public function addField($table, $field, $spec, $keys_new = array()) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException(t("Cannot add field @table.@field: table doesn't exist.", array('@field' => $field, '@table' => $table)));
    }
    if ($this->fieldExists($table, $field)) {
      throw new SchemaObjectExistsException(t("Cannot add field @table.@field: field already exists.", array('@field' => $field, '@table' => $table)));
    }

    $fixnull = FALSE;
    if (!empty($spec['not null']) && !isset($spec['default'])) {
      $fixnull = TRUE;
      $spec['not null'] = FALSE;
    }
    $query = 'ALTER TABLE {' . $table . '} ADD ';
    $query .= $this->createFieldSql($field, $this->processField($spec));
    if ($keys_sql = $this->createKeysSql($keys_new)) {
      $query .= ', ADD ' . implode(', ADD ', $keys_sql);
    }
    $this->connection->query($query);
    if (isset($spec['initial'])) {
      $this->connection->update($table)
        ->fields(array($field => $spec['initial']))
        ->execute();
    }
    if ($fixnull) {
      $spec['not null'] = TRUE;
      $this->changeField($table, $field, $field, $spec);
    }
  }

  public function dropField($table, $field) {
    if (!$this->fieldExists($table, $field)) {
      return FALSE;
    }

//    $this->connection->query('ALTER TABLE {' . $table . '} DROP `' . $field . '`'); // DB-2062
    $this->connection->query('ALTER TABLE {' . $table . '} DROP ' . $field );
    return TRUE;
  }

  public function fieldSetDefault($table, $field, $default) {
    if (!$this->fieldExists($table, $field)) {
      throw new SchemaObjectDoesNotExistException(t("Cannot set default value of field @table.@field: field doesn't exist.", array('@table' => $table, '@field' => $field)));
    }

    if (!isset($default)) {
      $default = 'NULL';
    }
    else {
      $default = is_string($default) ? "'$default'" : $default;
    }

//    $this->connection->query('ALTER TABLE {' . $table . '} ALTER COLUMN `' . $field . '` SET DEFAULT ' . $default); DB-2062
    $this->connection->query('ALTER TABLE {' . $table . '} ALTER COLUMN ' . $field . ' SET DEFAULT ' . $default);
  }

  public function fieldSetNoDefault($table, $field) {
    if (!$this->fieldExists($table, $field)) {
      throw new SchemaObjectDoesNotExistException(t("Cannot remove default value of field @table.@field: field doesn't exist.", array('@table' => $table, '@field' => $field)));
    }

//    $this->connection->query('ALTER TABLE {' . $table . '} ALTER COLUMN `' . $field . '` DROP DEFAULT'); DB-2062
    $this->connection->query('ALTER TABLE {' . $table . '} ALTER COLUMN ' . $field . ' DROP DEFAULT');
  }

  public function indexExists($table, $name) {
    // Returns one row for each column in the index. Result is string or FALSE.
    $row = $this->connection->query("SELECT INDEXNAME FROM SYSTEM.INDEXES WHERE TABLENAME = '$name'")->fetchAssoc();
    return isset($row['INDEXNAME']);
  }

  public function addPrimaryKey($table, $fields) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException(t("Cannot add primary key to table @table: table doesn't exist.", array('@table' => $table)));
    }
    if ($this->indexExists($table, 'PRIMARY')) {
      throw new SchemaObjectExistsException(t("Cannot add primary key to table @table: primary key already exists.", array('@table' => $table)));
    }

    $this->connection->query('ALTER TABLE {' . $table . '} ADD PRIMARY KEY (' . $this->createKeySql($fields) . ')');
  }

  public function dropPrimaryKey($table) {
    if (!$this->indexExists($table, 'PRIMARY')) {
      return FALSE;
    }

    $this->connection->query('ALTER TABLE {' . $table . '} DROP PRIMARY KEY');
    return TRUE;
  }

  public function addUniqueKey($table, $name, $fields) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException(t("Cannot add unique key @name to table @table: table doesn't exist.", array('@table' => $table, '@name' => $name)));
    }
    if ($this->indexExists($table, $name)) {
      throw new SchemaObjectExistsException(t("Cannot add unique key @name to table @table: unique key already exists.", array('@table' => $table, '@name' => $name)));
    }

//    $this->connection->query('ALTER TABLE {' . $table . '} ADD UNIQUE `' . $name . '` (' . $this->createKeySql($fields) . ')');  DB-2062
    $this->connection->query('ALTER TABLE {' . $table . '} ADD UNIQUE ' . $name . ' (' . $this->createKeySql($fields) . ')');
  }

  public function dropUniqueKey($table, $name) {
    if (!$this->indexExists($table, $name)) {
      return FALSE;
    }

//    $this->connection->query('ALTER TABLE {' . $table . '} DROP KEY `' . $name . '`');  DB-2062
    $this->connection->query('ALTER TABLE {' . $table . '} DROP KEY ' . $name);
    return TRUE;
  }

  public function addIndex($table, $name, $fields) {
    if (!$this->tableExists($table)) {
      throw new SchemaObjectDoesNotExistException(t("Cannot add index @name to table @table: table doesn't exist.", array('@table' => $table, '@name' => $name)));
    }
    if ($this->indexExists($table, $name)) {
      throw new SchemaObjectExistsException(t("Cannot add index @name to table @table: index already exists.", array('@table' => $table, '@name' => $name)));
    }

    $this->connection->query($this->_createIndexSql($table, $name, $fields));
  }

  public function dropIndex($table, $name) {
    if (!$this->indexExists($table, $name)) {
      return FALSE;
    }
    $this->connection->query('DROP INDEX ' . $this->prefixNonTable($table, $name, 'idx'));
    return TRUE;
  }

  public function changeField($table, $field, $field_new, $spec, $keys_new = array()) {
    if (!$this->fieldExists($table, $field)) {
      throw new SchemaObjectDoesNotExistException(t("Cannot change the definition of field @table.@name: field doesn't exist.", array('@table' => $table, '@name' => $field)));
    }
    if (($field != $field_new) && $this->fieldExists($table, $field_new)) {
      throw new SchemaObjectExistsException(t("Cannot rename field @table.@name to @name_new: target field already exists.", array('@table' => $table, '@name' => $field, '@name_new' => $field_new)));
    }

//    $sql = 'ALTER TABLE {' . $table . '} CHANGE `' . $field . '` ' . $this->createFieldSql($field_new, $this->processField($spec));  DB-2062
    $sql = 'ALTER TABLE {' . $table . '} CHANGE ' . $field . ' ' . $this->createFieldSql($field_new, $this->processField($spec));
    if ($keys_sql = $this->createKeysSql($keys_new)) {
      $sql .= ', ADD ' . implode(', ADD ', $keys_sql);
    }
    $this->connection->query($sql);
  }

  public function prepareComment($comment, $length = NULL) {
    // Work around a bug in some versions of PDO, see http://bugs.php.net/bug.php?id=41125
    $comment = str_replace("'", '’', $comment);

    // Truncate comment to maximum comment length.
    if (isset($length)) {
      // Add table prefixes before truncating.
      $comment = truncate_utf8($this->connection->prefixTables($comment), $length, TRUE, TRUE);
    }

    return $this->connection->quote($comment);
  }

  /**
   * Retrieve a table or column comment.
   */
  public function getComment($table, $column = NULL) {
    return null;
  }

  public function tableExists($table) {
    // TODO: use system.tables
    try {
      $this->connection->queryRange("SELECT 1 FROM {" . $table . "}", 0, 1);
      return TRUE;
    }
    catch (Exception $e) {
      return FALSE;
    }
  }

  public function fieldExists($table, $column) {
  // TODO: use system.fields
    try {
      $this->connection->queryRange("SELECT $column FROM {" . $table . "}", 0, 1);
      return TRUE;
    }
    catch (Exception $e) {
      return FALSE;
    }
  }

}

/**
 * @} End of "addtogroup schemaapi".
 */
