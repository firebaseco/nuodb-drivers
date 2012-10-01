<?php

/**
 * @file
 * Definition of Drupal\Core\Database\Driver\nuodb\Truncate
 */

namespace Drupal\Core\Database\Driver\nuodb;

use Drupal\Core\Database\Query\Truncate as QueryTruncate;

class Truncate extends QueryTruncate {
  public function __toString() {
    // Create a sanitized comment string to prepend to the query.
    $comments = $this->connection->makeComment($this->comments);

    return $comments . 'TRUNCATE TABLE {' . $this->connection->escapeTable($this->table) . '} ';
  }
}
