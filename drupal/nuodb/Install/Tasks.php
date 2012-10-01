<?php

/**
 * @file
 * Definition of Drupal\Core\Database\Driver\nuodb\Install\Tasks
 */

namespace Drupal\Core\Database\Driver\nuodb\Install;

use Drupal\Core\Database\Install\Tasks as InstallTasks;

/**
 * Specifies installation tasks for Nuodb and equivalent databases.
 */
class Tasks extends InstallTasks {
  /**
   * The PDO driver name for Nuodb and equivalent databases.
   *
   * @var string
   */
  protected $pdoDriver = 'nuodb';

  /**
   * Returns a human-readable name string for Nuodb and equivalent databases.
   */
  public function name() {
    return st('NuoDB');
  }

  /**
   * Returns the minimum version for Nuodb.
   */
  public function minimumVersion() {
    return 'NuoDB 1.0';
  }
}
