<?php

namespace Drupal\migrate_element\Command;

use Drush\Drush;
use Drush\Commands\DrushCommands;

class MyCustomImportCommands extends DrushCommands {

  /**
   * Import nodes from YAML files.
   *
   * @param string $directory
   *   The directory containing the YAML files.
   */
  public function importNodes($directory) {
    // Call the import function from the module
    migrate_element_nodes($directory);
    $this->output()->writeln("Import completed.");
  }

}

