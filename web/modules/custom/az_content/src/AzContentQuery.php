<?php
/**
* @file
*/


/**
 * Displays children pages as a block
 */

namespace Drupal\az_content;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides query for recent content
 *   Array $settings configures query parameters.
 */
class AzContentQuery {

  static public function nodeQuery(array $set) {
    $sorted = FALSE;
    $query = \Drupal::database()->select('node_field_data', 'nfd');

    ////////// Set the output fields
    if (isset($set['fields'])) {
      foreach ($set['fields'] as $name => $fields) {
        $query->fields(($name === 'null') ? NULL : $name, $fields);
      }
    } else {
      $query->fields('nfd', ['nid', 'title', 'status']);
    }

    ////////// FILTERS
    if (isset($set['nid'])) {
      $query->condition('nfd.nid', $set['nid'], is_array($set['nid']) ? 'IN' : '=');
    }
    if (isset($set['status'])) {
      $query->condition('nfd.status', $set['status'], is_array($set['status']) ? 'IN' : '=');
    }
    if (isset($set['types'])) {
      $query->condition('nfd.type', $set['types'], is_array($set['types']) ? 'IN' : '=');
    }
    if (isset($set['promoted'])) {
      $query->condition('nfd.promote', $set['promoted'], '=');
    }
    if (isset($set['sticky'])) {
      $query->condition('nfd.sticky', $set['sticky'], '=');
    }
    if (isset($set['exclude'])) {
      $query->condition('nfd.nid', $set['exclude'], is_array($set['exclude']) ? 'NOT IN' : '!=');
    }
    if (isset($set['author'])) {
      $query->condition('nfd.uid', $set['author'], is_array($set['author']) ? 'IN' : '=');
    }

    // Assigned To (user reference)
    if (isset($set['assigned'])) {
      $query->join('node__field_assigned_to', 'nfat', 'nfd.nid = nfat.entity_id');
      $query->condition('nfat.field_assigned_to_target_id', $set['assigned'], is_array($set['assigned']) ? 'IN' : '=');
    }

    // Approval field
    if (isset($set['approval'])) {
      $query->join('node__field_approval', 'nfa', 'nfd.nid = nfa.entity_id');
      $query->condition('nfa.field_approval_value', $set['approval'], is_array($set['approval']) ? 'IN' : '=');
    }

    // Topics (taxonomy)
    if (isset($set['topics'])) {
      $query->join('node__field_topics', 'nft', 'nfd.nid = nft.entity_id');
      $query->condition('nft.field_topics_target_id', $set['topics'], is_array($set['topics']) ? 'IN' : '=');
    }

    // Groups
    if (isset($set['groups'])) {
      $query->join('group_content_field_data', 'gcfd', 'gcfd.entity_id = nfd.nid');
      $query->condition('gcfd.type', ['theories-group_membership', 'book-group_membership'], 'NOT IN');
      $query->condition('gcfd.gid', $set['groups'], is_array($set['groups']) ? 'IN' : '=');
    }

    if (isset($set['groupsExclude'])) {
      $query->join('group_content_field_data', 'gcfd', 'gcfd.entity_id = nfd.nid');
      $query->condition('gcfd.type', ['theories-group_membership', 'book-group_membership'], 'NOT IN');
      $query->condition('gcfd.gid', $set['groupsExclude'], is_array($set['groupsExclude']) ? 'NOT IN' : '!=');
    }

    // Page reference
    if (isset($set['pages'])) {
      $query->join('node__field_page', 'nfp', 'nfd.nid = nfp.entity_id');
      $query->condition('nfp.field_page_target_id', $set['pages'], is_array($set['pages']) ? 'IN' : '=');
    }

    // Publish date age filter
    if (isset($set['publishAge'])) {
      $request_time = \Drupal::time()->getRequestTime();
      $seconds = $request_time - ($set['publishAge'] * 60);
      $date = \Drupal::service('date.formatter')->format($seconds, 'custom', 'Y-m-d\TH:i:s', 'UTC');
      $query->join('node__field_publish_date', 'nfpd', 'nfd.nid = nfpd.entity_id');
      $query->condition('nfpd.field_publish_date_value', $date, '>');
    }

    // Count query early exit
    if (isset($set['count']) && $set['count']) {
      return $query->countQuery()->execute()->fetchField();
    }

    $totalRows = (isset($set['getTotalRows']) && $set['getTotalRows'])
      ? $query->countQuery()->execute()->fetchField()
      : -1;

    ////////// SORTS
    if (!$sorted) {
      if (isset($set['sticky'])) {
        $query->orderBy('nfd.sticky', 'DESC');
      }

      $sort = $set['sort'] ?? 'changed';
      $order = $set['sortOrder'] ?? 'DESC';

      switch ($sort) {
        case 'none':
          $sorted = TRUE;
          break;

        case 'changed':
          $query->orderBy('nfd.changed', $order);
          $sorted = TRUE;
          break;

        case 'created':
          $query->orderBy('nfd.created', $order);
          $sorted = TRUE;
          break;

        case 'select-atom':
          // Join element (for atomic number)
          $query->join('node__field_element', 'nfe', 'nfd.nid = nfe.entity_id');

          // Atomic number from element node
          $query->leftJoin('node__field_atomic_number', 'nfan', 'nfan.entity_id = nfe.field_element_target_id');
          $query->orderBy('nfan.field_atomic_number_value', 'ASC');

          // Protons from atom
          $query->leftJoin('node__field_protons', 'nfp', 'nfd.nid = nfp.entity_id');
          $query->orderBy('nfp.field_protons_value', 'ASC');

          // Abundance & half-life (optional)
          $query->leftJoin('node__field_abundance', 'nfab', 'nfd.nid = nfab.entity_id');
          $query->leftJoin('node__field_half_life', 'nfhl', 'nfd.nid = nfhl.entity_id');

          // Stability taxonomy term name
          $query->leftJoin('node__field_stability', 'nfs', 'nfd.nid = nfs.entity_id');
          $query->leftJoin('taxonomy_term_field_data', 'ttfd', 'ttfd.tid = nfs.field_stability_target_id');

          // NEW: Join media reference field
          $query->leftJoin('node__field_atom_image', 'nfai', 'nfd.nid = nfai.entity_id');
          $query->leftJoin('media__field_media_image', 'mfi', 'mfi.entity_id = nfai.field_atom_image_target_id');
          $query->leftJoin('file_managed', 'fm', 'fm.fid = mfi.field_media_image_target_id');

          // Optional: Join media → file to get file info (if needed in fields)
          // Only add if you're selecting file-related fields
          if (!empty($set['fields']) && in_array('file_uri', array_merge(...array_values($set['fields'] ?? [])))) {
            $query->leftJoin('media__field_media_image', 'mfi', 'mfi.entity_id = nfai.field_atom_image_target_id');
            $query->leftJoin('file_managed', 'fm', 'fm.fid = mfi.field_media_image_target_id');
          }

          // Approval
          $query->leftJoin('node__field_approval', 'nfap', 'nfd.nid = nfap.entity_id');

          // Final sort by title
          $query->orderBy('nfd.title', 'ASC');
          $sorted = TRUE;
          break;

        case 'element':
          $query->leftJoin('node__field_atomic_number', 'nfan', 'nfan.entity_id = nfd.nid');
          $query->orderBy('nfan.field_atomic_number_value', 'ASC');
          $sorted = TRUE;
          break;

        case 'title':
          $query->orderBy('nfd.title', 'ASC');
          $sorted = TRUE;
          break;
      }
    }

    // Execute with pagination/more options
    switch ($set['more'] ?? 'none') {
      case 'none':
        $results = $query->execute()->fetchAllAssoc('nid');
        break;

      case 'limit':
        $query->range(0, $set['limit'] ?? 10);
        $results = $query->execute()->fetchAllAssoc('nid');
        break;

      case 'ajax':
        if (isset($set['pageNum'])) {
          $query->range($set['pageNum'] * $set['pageNumItems'], $set['pageNumItems']);
        }
        $results = $query->execute()->fetchAllAssoc('nid');
        break;

      case 'pager':
        $result = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
          ->limit($set['pageNumItems'] ?? 20)
          ->element($set['pagerId'] ?? 0)
          ->execute();
        $results = $result->fetchAllAssoc('nid');
        break;

      default:
        $results = $query->execute()->fetchAllAssoc('nid');
    }

    return [
      'results' => $results,
      'numRows' => count($results),
      'totalRows' => (int) $totalRows,
    ];
  }

  static public function commentQuery(Array $set) {
    $sorted = false; // Prevent duplicate sorts.

    ////////// Connect to database - root table comment_field_data
    $query = \Drupal::database()->select('comment_field_data', 'cfd');

    ////////// Set the output fields
    if (isset($set['fields'])) {
      foreach ($set['fields'] as $name => $fields) {
        $query->fields(($name == 'null') ? null : $name, $fields);
      }
    } else {
      $query->fields('cfd', ['cid']);
//    $query->fields('nfd', ['nid', 'title', 'status']);
    }

    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    // COMMENT FILTERS
    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    ////////// Filter on core Drupal published value
    if (isset($set['statusComment'])) {
      $query->condition('cfd.status', $set['statusComment'], (is_array($set['statusComment'])) ? 'IN' : '=');
    }

    ////////// Author
    if (isset($set['authorComment'])) {
      $query->condition('cfd.uid', $set['authorComment'], (is_array($set['authorComment'])) ? 'IN' : '=');
    }

    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    // PARENT NODE FILTERS
    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    ////////// Join in the parent node table.
    $query->join('node_field_data', 'nfd', 'nfd.nid = cfd.entity_id');

    ////////// Filter on core Drupal published value
    if (isset($set['status'])) {
      $query->condition('nfd.status', $set['status'], '=');
    }

    ////////// Content Types
    if (isset($set['types'])) {
      $query->condition('nfd.type', $set['types'], (is_array($set['types'])) ? 'IN' : '=');
    }

    ////////// Author
    if (isset($set['author'])) {
      $query->condition('nfd.uid', $set['author'], (is_array($set['author'])) ? 'IN' : '=');
    }

    ////////// Topics
    if (isset($set['topics'])) {
      $query->join('node__field_topics', 'nft', 'nfd.nid = nft.entity_id');
      $query->condition('nft.field_topics_target_id', $set['topics'], (is_array($set['topics'])) ? 'IN' : '=');
    }

    ////////// Groups
    if (isset($set['groups'])) {
      $query->join('group_content_field_data', 'gcfd', 'gcfd.entity_id = nfd.nid');
      $query->condition('gcfd.type', ['theories-group_membership','book-group_membership'], 'NOT IN');
      $query->condition('gcfd.gid', $set['groups'], (is_array($set['groups'])) ? 'IN' : '=');
    }

    ////////// Exclude content from this/these groups.
    if (isset($set['groupsExclude'])) {
      $query->join('group_content_field_data', 'gcfd', 'gcfd.entity_id = nfd.nid');
      $query->condition('gcfd.type', ['theories-group_membership', 'book-group_membership'], 'NOT IN');
      $query->condition('gcfd.gid', $set['groupsExclude'], (is_array($set['groupsExclude'])) ? 'NOT IN' : '!=');
    }

    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    // EXECUTE COUNT QUERY
    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    if ((isset($set['getTotalRows']) && $set['getTotalRows']) ||
        (isset($set['count']) && $set['count'])) {
      $totalRows = $query->countQuery()->execute()->fetchField();
      if (isset($set['count'])) {
        return $totalRows;
      }
    } else {
      $totalRows = -1;
    }

    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    // SORTS
    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    if (!$sorted) {
      $sort = (isset($set['sort'])) ? $set['sort'] : 'changed';
      $order = (isset($set['sortOrder'])) ? $set['sortOrder'] : 'DESC';
      switch ($sort) {
        case 'none':
          $sorted = true;
          break;
        case 'changed':
          $query->orderBy('cfd.changed', $order);
          $sorted = true;
          break;
        case 'created':
          $query->orderBy('cfd.created', $order);
          $sorted = true;
          break;
      }
    }

    // Execute the query depending on 'more' setting.
    switch ((isset($set['more'])) ? $set['more'] : 'none') {
      case 'none':
        $results = $query->execute()->fetchAllAssoc('nid');
        break;

      case 'ajax':
        // If using AJAX then we keep track of page number and items per page.
        if (isset($set['pageNum'])) {
          $query->range($set['pageNum'] * $set['pageNumItems'], $set['pageNumItems']);
        }
        $result = $query->execute();
        $results = $result->fetchAllAssoc('cid');

        break;

      case 'pager':
        $result = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
          ->limit((isset($set['pageNumItems'])) ? $set['pageNumItems'] : 20)
          ->element((isset($set['pagerId'])) ? $set['pagerId'] : 0)
          ->execute();
        $results = $result->fetchAllAssoc('cid');
        break;
    }
    return [
      'results' => $results,
      'numRows' => count($results),
      'totalRows' => (int)$totalRows,
    ];
  }

  static public function mediaQuery(Array $set) {
    $sorted = false; // Prevent duplicate sorts.

    ////////// Connect to database - root table comment_field_data
    $query = \Drupal::database()->select('media_field_data', 'mfd');

    ////////// Set the output fields
    if (isset($set['fields'])) {
      foreach ($set['fields'] as $name => $fields) {
        $query->fields(($name == 'null') ? null : $name, $fields);
      }
    } else {
      $query->fields('mfd', ['mid']);
    }

    ////////// Filter on core Drupal published value
    if (isset($set['status'])) {
      $query->condition('mfd.status', $set['status'], (is_array($set['status'])) ? 'IN' : '=');
    }

    ////////// Author
    if (isset($set['author'])) {
      $query->condition('mfd.uid', $set['author'], (is_array($set['author'])) ? 'IN' : '=');
    }

    ////////// Content Types
    if (isset($set['types'])) {
      $query->condition('mfd.bundle', $set['types'], (is_array($set['types'])) ? 'IN' : '=');
    }

    ////////// Topics
    if (isset($set['topics'])) {
      $query->join('media__field_topics', 'mft', 'mfd.mid = mft.entity_id');
      $query->condition('mft.field_topics_target_id', $set['topics'], (is_array($set['topics'])) ? 'IN' : '=');
    }

    ////////// Groups
    if (isset($set['groups'])) {
      $query->join('media__field_group', 'mft', 'mfd.mid = mft.entity_id');
      $query->condition('mft.field_group_target_id', $set['groups'], (is_array($set['groups'])) ? 'IN' : '=');
    }

    ////////// Exclude content from this/these groups.
    if (isset($set['groupsExclude'])) {
      $query->join('media__field_group', 'mft', 'mfd.mid = mft.entity_id');
      $query->condition('mft.field_group_target_id', $set['groupsExclude'], (is_array($set['groupsExclude'])) ? 'IN' : '=');
    }

    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    // EXECUTE COUNT QUERY
    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    if ((isset($set['getTotalRows']) && $set['getTotalRows']) ||
      (isset($set['count']) && $set['count'])) {
      $totalRows = $query->countQuery()->execute()->fetchField();
      if (isset($set['count'])) {
        return $totalRows;
      }
    } else {
      $totalRows = -1;
    }

    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    // SORTS
    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    if (!$sorted) {
      $sort = (isset($set['sort'])) ? $set['sort'] : 'changed';
      $order = (isset($set['sortOrder'])) ? $set['sortOrder'] : 'DESC';
      switch ($sort) {
        case 'none':
          $sorted = true;
          break;
        case 'changed':
          $query->orderBy('mfd.changed', $order);
          $sorted = true;
          break;
        case 'created':
          $query->orderBy('mfd.created', $order);
          $sorted = true;
          break;
      }
    }

    // Execute the query depending on 'more' setting.
    switch ((isset($set['more'])) ? $set['more'] : 'none') {
      case 'none':
        $results = $query->execute()->fetchAllAssoc('mid');
        break;

      case 'ajax':
        // If using AJAX then we keep track of page number and items per page.
        if (isset($set['pageNum'])) {
          $query->range($set['pageNum'] * $set['pageNumItems'], $set['pageNumItems']);
        }
        $result = $query->execute();
        $results = $result->fetchAllAssoc('mid');

        break;

      case 'pager':
        $result = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
          ->limit((isset($set['pageNumItems'])) ? $set['pageNumItems'] : 20)
          ->element((isset($set['pagerId'])) ? $set['pagerId'] : 0)
          ->execute();
        $results = $result->fetchAllAssoc('mid');
        break;
    }
    return [
      'results' => $results,
      'numRows' => count($results),
      'totalRows' => (int)$totalRows,
    ];
  }

  static public function groupQuery(Array $set) {
    $sorted = false; // Prevent duplicate sorts.

    ////////// Build database query = root table group_field_data
    $query = \Drupal::database()->select('group_field_data', 'gfd');

    ////////// Set the output fields
    if (isset($set['fields'])) {
      foreach ($set['fields'] as $name => $fields) {
        $query->fields(($name == 'null') ? null : $name, $fields);
      }
    } else {
      $query->fields('gfd', ['gid']);
    }

    ////////// Filter on core Drupal published value
    if (isset($set['status'])) {
      $query->condition('gfd.status', $set['status'], (is_array($set['status'])) ? 'IN' : '=');
    }

    ////////// Content Types
    if (isset($set['types'])) {
      $query->condition('mfd.bundle', $set['types'], (is_array($set['types'])) ? 'IN' : '=');
    }

    ////////// Topics
    if (isset($set['topics'])) {
      $query->join('media__field_topics', 'mft', 'mfd.mid = mft.entity_id');
      $query->condition('mft.field_topics_target_id', $set['topics'], (is_array($set['topics'])) ? 'IN' : '=');
    }

    ////////// Groups
    if (isset($set['groups'])) {
      $query->join('media__field_group', 'mft', 'mfd.mid = mft.entity_id');
      $query->condition('mft.field_group_target_id', $set['groups'], (is_array($set['groups'])) ? 'IN' : '=');
    }

    ////////// Exclude content from this/these groups.
    if (isset($set['groupsExclude'])) {
      $query->join('media__field_group', 'mft', 'mfd.mid = mft.entity_id');
      $query->condition('mft.field_group_target_id', $set['groupsExclude'], (is_array($set['groupsExclude'])) ? 'IN' : '=');
    }

    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    // EXECUTE COUNT QUERY
    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

    if ((isset($set['getTotalRows']) && $set['getTotalRows']) ||
      (isset($set['count']) && $set['count'])) {
      $totalRows = $query->countQuery()->execute()->fetchField();
      if (isset($set['count'])) {
        return $totalRows;
      }
    } else {
      $totalRows = -1;
    }

    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    // SORTS
    //\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
    if (!$sorted) {
      $sort = (isset($set['sort'])) ? $set['sort'] : 'changed';
      $order = (isset($set['sortOrder'])) ? $set['sortOrder'] : 'DESC';
      switch ($sort) {
        case 'none':
          $sorted = true;
          break;
        case 'changed':
          $query->orderBy('mfd.changed', $order);
          $sorted = true;
          break;
        case 'created':
          $query->orderBy('mfd.created', $order);
          $sorted = true;
          break;
      }
    }

    // Execute the query depending on 'more' setting.
    switch ((isset($set['more'])) ? $set['more'] : 'none') {
      case 'none':
        $results = $query->execute()->fetchAllAssoc('mid');
        break;

      case 'ajax':
        // If using AJAX then we keep track of page number and items per page.
        if (isset($set['pageNum'])) {
          $query->range($set['pageNum'] * $set['pageNumItems'], $set['pageNumItems']);
        }
        $result = $query->execute();
        $results = $result->fetchAllAssoc('mid');

        break;

      case 'pager':
        $result = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
          ->limit((isset($set['pageNumItems'])) ? $set['pageNumItems'] : 20)
          ->element((isset($set['pagerId'])) ? $set['pagerId'] : 0)
          ->execute();
        $results = $result->fetchAllAssoc('mid');
        break;
    }
    return [
      'results' => $results,
      'numRows' => count($results),
      'totalRows' => (int)$totalRows,
    ];
  }
}

