<?php

/**
 * @file
 * Definition of Drupal\Core\Database\Driver\nuodb\Connection
 */

namespace Drupal\Core\Database\Driver\nuodb;

use Drupal\Core\Database\DatabaseExceptionWrapper;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\TransactionCommitFailedException;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Database\Connection as DatabaseConnection;

use PDO;

/**
 * @addtogroup database
 * @{
 */

class Connection extends DatabaseConnection {

  /**
   * Flag to indicate if we have registered the nextID cleanup function.
   *
   * @var boolean
   */
  protected $shutdownRegistered = FALSE;

  public function __construct(array $connection_options = array()) {
    // This driver defaults to transaction support, except if explicitly passed FALSE.
    $this->transactionSupport = !isset($connection_options['transactions']) || ($connection_options['transactions'] !== FALSE);
$this->transactionSupport = FALSE;

    // Nuodb never supports transactional DDL.
    $this->transactionalDDLSupport = FALSE;

    $this->connectionOptions = $connection_options;

//    $dsn = 'nuodb:database=' . $connection_options['database'] 
//	     . ';schema=' . $connection_options['schema'];
    $dsn = 'nuodb:database=' . $connection_options['database'] 
	     . ';schema=drupal';


    
    // Allow PDO options to be overridden.
    $connection_options += array(
      'pdo' => array(),
    );
    $connection_options['pdo'] += array(
      // Convert numeric values to strings when fetching.
      PDO::ATTR_STRINGIFY_FETCHES => TRUE,  // TODO: Is this needed?
      PDO::ATTR_CASE => PDO::CASE_LOWER,  // temporary workaround until DB-1239 is fixed.
      PDO::ATTR_AUTOCOMMIT => TRUE,
    );

    parent::__construct($dsn, $connection_options['username'], $connection_options['password'], $connection_options['pdo']);

  }

  public function mapConditionOperator($operator) {
    static $specials = array(
      'LIKE' => array('postfix' => " ESCAPE '\\'"),
      'NOT LIKE' => array('postfix' => " ESCAPE '\\'"),
    );
    return isset($specials[$operator]) ? $specials[$operator] : NULL;
  }

  public function queryRange($query, $from, $count, array $args = array(), array $options = array()) {
    return $this->query($query . ' OFFSET ' . (int) $from . ' FETCH ' . (int) $count, $args, $options);
  }

  public function queryTemporary($query, array $args = array(), array $options = array()) {
    $tablename = $this->generateTemporaryTableName();
    $this->query(preg_replace('/^SELECT/i', 'CREATE TEMPORARY TABLE {' . $tablename . '} Engine=MEMORY SELECT', $query), $args, $options);
    return $tablename;
  }

  public function driver() {
    return 'nuodb';
  }

  public function databaseType() {
    return 'nuodb';
  }

  public function nextId($existing_id = 0) {
    $new_id = $this->query('INSERT INTO {sequences} (value) VALUES (:existing_id + 1)', array(':existing_id' => $existing_id), array('return' => Database::RETURN_INSERT_ID));
    // This should only happen after an import or similar event.
    if ($existing_id >= $new_id) {
      // If we INSERT a value manually into the sequences table, on the next
      // INSERT, Nuodb will generate a larger value. However, there is no way
      // of knowing whether this value already exists in the table. Nuodb
      // provides an INSERT IGNORE which would work, but that can mask problems
      // other than duplicate keys. Instead, we use INSERT ... ON DUPLICATE KEY
      // UPDATE in such a way that the UPDATE does not do anything. This way,
      // duplicate keys do not generate errors but everything else does.
      $this->query('INSERT INTO {sequences} (value) VALUES (:value) ON DUPLICATE KEY UPDATE value = value', array(':value' => $existing_id));
      $new_id = $this->query('INSERT INTO {sequences} () VALUES ()', array(), array('return' => Database::RETURN_INSERT_ID));
    }
    if (!$this->shutdownRegistered) {
      // Use register_shutdown_function() here to keep the database system
      // independent of Drupal.
      register_shutdown_function(array($this, 'nextIdDelete'));
      $shutdownRegistered = TRUE;
    }
    return $new_id;
  }

  public function nextIdDelete() {
    // While we want to clean up the table to keep it up from occupying too
    // much storage and memory, we must keep the highest value in the table
    // because InnoDB  uses an in-memory auto-increment counter as long as the
    // server runs. When the server is stopped and restarted, InnoDB
    // reinitializes the counter for each table for the first INSERT to the
    // table based solely on values from the table so deleting all values would
    // be a problem in this case. Also, TRUNCATE resets the auto increment
    // counter.
    try {
      $max_id = $this->query('SELECT MAX(value) FROM {sequences}')->fetchField();
      // We know we are using Nuodb here, no need for the slower db_delete().
      $this->query('DELETE FROM {sequences} WHERE value < :value', array(':value' => $max_id));
    }
    // During testing, this function is called from shutdown with the
    // simpletest prefix stored in $this->connection, and those tables are gone
    // by the time shutdown is called so we need to ignore the database
    // errors. There is no problem with completely ignoring errors here: if
    // these queries fail, the sequence will work just fine, just use a bit
    // more database storage and memory.
    catch (DatabaseException $e) {
    }
  }

  /**
   * Overridden to work around issues to Nuodb not supporting transactional DDL.
   */
  protected function popCommittableTransactions() {
    // Commit all the committable layers.
    foreach (array_reverse($this->transactionLayers) as $name => $active) {
      // Stop once we found an active transaction.
      if ($active) {
        break;
      }

      // If there are no more layers left then we should commit.
      unset($this->transactionLayers[$name]);
      if (empty($this->transactionLayers)) {
        if (!PDO::commit()) {
          throw new TransactionCommitFailedException();
        }
      }
      else {
        // Attempt to release this savepoint in the standard way.
        try {
          $this->query('RELEASE SAVEPOINT ' . $name);
        }
        catch (DatabaseExceptionWrapper $e) {
          // However, in Nuodb (InnoDB), savepoints are automatically committed
          // when tables are altered or created (DDL transactions are not
          // supported). This can cause exceptions due to trying to release
          // savepoints which no longer exist.
          //
          // To avoid exceptions when no actual error has occurred, we silently
          // succeed for Nuodb error code 1305 ("SAVEPOINT does not exist").
          if ($e->getPrevious()->errorInfo[1] == '1305') {
            // If one SAVEPOINT was released automatically, then all were.
            // Therefore, clean the transaction stack.
            $this->transactionLayers = array();
            // We also have to explain to PDO that the transaction stack has
            // been cleaned-up.
            PDO::commit();
          }
          else {
            throw $e;
          }
        }
      }
    }
  }
}


/**
 * @} End of "addtogroup database".
 */
