<?php

/* TODO: Copyright Notice */

/**
 * @see Zend_Db_Adapter_Pdo_Abstract
 */
require_once 'Zend/Db/Adapter/Pdo/Abstract.php';


/**
 * Class for connecting to NuoDB databases and performing common operations.
 *
 * @category   Zend
 * @package    Zend_Db
 * @subpackage Adapter
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Db_Adapter_Pdo_Nuodb extends Zend_Db_Adapter_Pdo_Abstract
{

    /**
     * PDO type.
     *
     * @var string
     */
    protected $_pdoType = 'nuodb';

    protected static $_default_schema = null;

    /**
     * Keys are UPPERCASE SQL datatypes or the constants
     * Zend_Db::INT_TYPE, Zend_Db::BIGINT_TYPE, or Zend_Db::FLOAT_TYPE.
     *
     * Values are:
     * 0 = 32-bit integer
     * 1 = 64-bit integer
     * 2 = float or decimal
     *
     * @var array Associative array of datatypes to values 0, 1, or 2.
     */
    protected $_numericDataTypes = array(
        Zend_Db::INT_TYPE    => Zend_Db::INT_TYPE,
        Zend_Db::BIGINT_TYPE => Zend_Db::BIGINT_TYPE,
        Zend_Db::FLOAT_TYPE  => Zend_Db::FLOAT_TYPE,
        'INT'                => Zend_Db::INT_TYPE,
        'INTEGER'            => Zend_Db::INT_TYPE,
        'MEDIUMINT'          => Zend_Db::INT_TYPE,
        'SMALLINT'           => Zend_Db::INT_TYPE,
        'TINYINT'            => Zend_Db::INT_TYPE,
        'BIGINT'             => Zend_Db::BIGINT_TYPE,
        'SERIAL'             => Zend_Db::BIGINT_TYPE,
        'DEC'                => Zend_Db::FLOAT_TYPE,
        'DECIMAL'            => Zend_Db::FLOAT_TYPE,
        'DOUBLE'             => Zend_Db::FLOAT_TYPE,
        'DOUBLE PRECISION'   => Zend_Db::FLOAT_TYPE,
        'FIXED'              => Zend_Db::FLOAT_TYPE,
        'FLOAT'              => Zend_Db::FLOAT_TYPE
    );

    /**
     * Override _dsn() and ensure that charset is incorporated in NuoDb
     * @see Zend_Db_Adapter_Pdo_Abstract::_dsn()
     */
    protected function _dsn()
    {
        parent::_checkRequiredOptions($this->_config);
	$_default_schema = $this->_config['schema'];
	$dsn = 'nuodb:database=' . $this->_config['dbname'] 
	     . ';schema=' . $this->_config['schema'];
        return $dsn;
    }

    /**
     * Checks required options
     *
     * @param  array $config
     * @throws Zend_Db_Adapter_Exception
     * @return void
     */
    protected function _checkRequiredOptions(array $config)
    {
        parent::_checkRequiredOptions($config);

        if (!array_key_exists('schema', $config)) {
            /** @see Zend_Db_Adapter_Exception */
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception("Nuodb configuration must have a key for 'schema' specified");
        }
    }

    /**
     * Check if the adapter supports real SQL parameters.
     *
     * @param string $type 'positional' or 'named'
     * @return bool
     */
    public function supportsParameters($type)
    {
        switch ($type) {
            case 'named':
                return true;
            case 'positional':
            default:
                return false;
        }
    }
    
    /**
     * Creates a PDO object and connects to the database.
     *
     * @return void
     * @throws Zend_Db_Adapter_Exception
     */
    protected function _connect()
    {
        if ($this->_connection) {
            return;
        }
	$this->_autoQuoteIdentifiers = 0;
	$this->_caseFolding = Zend_Db::CASE_LOWER;
        parent::_connect();
    }

    /**
     * @return string
     */
    public function getQuoteIdentifierSymbol()
    {
        return "'";
    }

    /**
     * Returns a list of the tables in the database.
     *
     * @return array
     */
    public function listTables()
    {
        return $this->fetchCol('SHOW TABLES');
    }

    /**
     * Returns the column descriptions for a table.
     *
     * The return value is an associative array keyed by the column name,
     * as returned by the RDBMS.
     *
     * The value of each array element is an associative array
     * with the following keys:
     *
     * SCHEMA_NAME      => string; name of database or schema
     * TABLE_NAME       => string;
     * COLUMN_NAME      => string; column name
     * COLUMN_POSITION  => number; ordinal position of column in table
     * DATA_TYPE        => string; SQL datatype name of column
     * DEFAULT          => string; default expression of column, null if none
     * NULLABLE         => boolean; true if column can have nulls
     * LENGTH           => number; length of CHAR/VARCHAR
     * SCALE            => number; scale of NUMERIC/DECIMAL
     * PRECISION        => number; precision of NUMERIC/DECIMAL
     * UNSIGNED         => boolean; unsigned property of an integer type
     * PRIMARY          => boolean; true if column is part of the primary key
     * PRIMARY_POSITION => integer; position of column in primary key
     * IDENTITY         => integer; true if column is auto-generated with unique values
     *
     * @param string $tableName
     * @param string $schemaName OPTIONAL
     * @return array
     */
    public function describeTable($tableName, $schemaName = null)
    {
        if (! $schemaName) {
 	   $_default_schema = $this->_config['schema'];
	   $schemaName = $_default_schema;
	}
        $sql1 = "select f.fieldPosition as ordinal, f.field as column_name, d.name as column_type, f.length, f.flags, f.precision, f.scale, f.defaultValue, f.generator_sequence is not null as auto  from system.fields f join system.datatypes d on (d.id = f.datatype) where f.schema = '" . $schemaName . "' and f.tableName = '" . $tableName . "' order by f.fieldPosition;";
	$sql2 = "select f.field, f.position, (d.indextype = 0) as IS_PK from system.indexFields f join SYSTEM.INDEXES d on (d.indexname = f.indexname) where (f.schema = '" . $schemaName . "' and f.tableName = '" . $tableName . "' and d.indextype = 0);";

        $stmt = $this->query($sql1);
        // Use FETCH_NUM so we are not dependent on the CASE attribute of the PDO connection
        $result = $stmt->fetchAll(Zend_Db::FETCH_NUM);

	$ordinal_field       = 0;
	$column_name_field   = 1;
        $column_type_field   = 2;
        $length_field        = 3;
        $flags_field         = 4;
        $precision_field     = 5;
        $scale_field         = 6;
        $default_value_field = 7;
        $auto_field          = 8;

        $desc = array();
        $i = 1;
        $p = 1;

        foreach ($result as $row) {

            list($length, $scale, $precision, $unsigned, $primary, $primaryPosition, $identity)
                = array(null, null, null, null, false, null, false);

            if (preg_match('/unsigned/', $row[$column_type_field])) {
                $unsigned = true;
            }
	    // TODO - convert $row[$type] ?	    

	    $nullable = true;
	    if ($row[$flags_field] != '<null>') {
	       if (!($row[$flags_field] & 1)) {
	       	  $nullable = false;
	       }
            }

            $desc[$this->foldCase($row[$column_name_field])] = array(
                'SCHEMA_NAME'      => $schemaName,
                'TABLE_NAME'       => $this->foldCase($tableName),
                'COLUMN_NAME'      => $this->foldCase($row[$column_name_field]),
                'COLUMN_POSITION'  => $i,
                'DATA_TYPE'        => $row[$column_type_field],
                'DEFAULT'          => $row[$default_value_field],
                'NULLABLE'         => $nullable,
                'LENGTH'           => $row[$length_field],
                'SCALE'            => $row[$scale_field],
                'PRECISION'        => $row[$precision_field],
                'UNSIGNED'         => $unsigned,
                'PRIMARY'          => $primary,
                'PRIMARY_POSITION' => $primaryPosition,
                'IDENTITY'         => (bool) ($row[$auto_field] == 'TRUE')
            );
            ++$i;
        }


        $stmt = $this->query($sql2);

        // Use FETCH_NUM so we are not dependent on the CASE attribute of the PDO connection
        $result = $stmt->fetchAll(Zend_Db::FETCH_NUM);

	$field_field     = 0;
	$position_field  = 1;
        $is_pk_field     = 2;

        foreach ($result as $row) {
            $desc[$this->foldCase($row[$field_field])]['PRIMARY'] = (bool) ($row[$is_pk_field] == 'TRUE');
            $desc[$this->foldCase($row[$field_field])]['PRIMARY_POSITION'] = (int) $row[$position_field] + 1;
        }

        return $desc;
    }

    /**
     * Adds an adapter-specific LIMIT clause to the SELECT statement.
     *
     * @param  string $sql
     * @param  integer $count
     * @param  integer $offset OPTIONAL
     * @throws Zend_Db_Adapter_Exception
     * @return string
     */
     public function limit($sql, $count, $offset = 0)
     {
        $count = intval($count);
        if ($count <= 0) {
            /** @see Zend_Db_Adapter_Exception */
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception("LIMIT argument count=$count is not valid");
        }

        $offset = intval($offset);
        if ($offset < 0) {
            /** @see Zend_Db_Adapter_Exception */
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception("LIMIT argument offset=$offset is not valid");
        }

        if ($offset > 0) {
            $sql .= " OFFSET $offset";
        }
        $sql .= " FETCH $count";

        return $sql;
    }

}
