<?php
// inject_migration_data.php – works with drush php-script on Drupal 10+

// Proper Drupal bootstrap (required!)
if (!defined('DRUPAL_DIR')) {
  // Drush already bootstrapped Drupal for us – just use the service
  $data_rows = [];

  $migrations = [
    'taxonomy_terms' => '/tmp/drupal_export/taxonomy_terms.json',
    'atom'           => '/tmp/drupal_export/atom.json',
    'element'        => '/tmp/drupal_export/element.json',
  ];

  foreach ($migrations as $migration_id => $file) {
    if (!file_exists($file)) {
      print "ERROR: File not found: $file\n";
      continue;
    }

    $json = file_get_contents($file);
    $data = json_decode($json, TRUE);

    if (json_last_error() !== JSON_ERROR_NONE) {
      print "ERROR: Invalid JSON in $file\n";
      continue;
    }

    if (empty($data)) {
      print "WARNING: $file is empty – skipping $migration_id\n";
      continue;
    }

    // Update the migration config
    $config = \Drupal::configFactory()
      ->getEditable("migrate_plus.migration.$migration_id");

    $config->set('source.data_rows', $data);
    $config->save();

    print "Injected " . count($data) . " rows into migration: $migration_id\n";
  }

  print "\nAll done! Now run:\n";
  print "  drush cr\n";
  print "  drush migrate:status\n";
  print "  drush migrate:import taxonomy_terms\n";
  print "  drush migrate:import atom\n";
  print "  drush migrate:import element\n";
}
