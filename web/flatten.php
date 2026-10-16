# Save as flatten_export.php on the old D8 site and run: drush scr flatten_export.php
<?php
use Drupal\Component\Serialization\Json;

$dir = '/tmp/drupal_export';

$types = ['taxonomy_term', 'node', 'node'];
$bundles = ['', 'atom', 'element'];
$files = ['taxonomy_terms.json', 'atom.json', 'element.json'];

foreach (['taxonomy_terms', 'atom', 'element'] as $i => $key) {
  $file = "$dir/{$files[$i]}";
  if (!file_exists($file)) continue;

  $data = json_decode(file_get_contents($file), TRUE);
  $flat = [];

  foreach ($data as $item) {
    $row = [];
    foreach ($item as $field => $values) {
      // Flatten: take first delta, then first value (or target_id for references)
      $first = $values[0] ?? NULL;
      if (is_array($first)) {
        if (isset($first['target_id'])) {
          $row[$field] = $first['target_id'];
        } elseif (isset($first['value'])) {
          $row[$field] = $first['value'];
        } else {
          $row[$field] = NULL;
        }
      } else {
        $row[$field] = $first;
      }
    }
    // Special case: parent should be 0 if missing or empty
    $row['parent'] = $row['parent'] ?? 0;
    $flat[] = $row;
  }

  file_put_contents($file, Json::encode($flat));
  echo "Flattened $file → " . count($flat) . " rows\n";
}
