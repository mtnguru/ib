<?php

namespace Drupal\az_book\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\book\BookManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Provides a block that displays a hierarchical book menu for the current page.
 *
 * @Block(
 *   id = "az_book_block",
 *   admin_label = @Translation("AZ Book Block")
 * )
 */
class AzBookBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The book manager service.
   *
   * @var \Drupal\book\BookManagerInterface
   */
  protected $bookManager;

  /**
   * Constructs the AzBookBlock.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BookManagerInterface $book_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->bookManager = $book_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('book.manager')
    );
  }

  /**
   * {@inheritdoc}
   */

  public function build() {
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!$node instanceof Node) {
      return ['#markup' => $this->t('No book available.')];
    }

    // Use the book property set by book.module.
    $book_info = isset($node->book) ? $node->book : NULL;
    if (empty($book_info) || empty($book_info['bid'])) {
      return ['#markup' => $this->t('This node is not part of a book.')];
    }

    $book_id = $book_info['bid'];
    $current_nid = $node->id();
    $can_admin = \Drupal::currentUser()->hasPermission('administer book outlines');

    // Load the top-level book node to use as the block title.
    $book_root = Node::load($book_id);
    $book_title = $book_root ? $book_root->label() : $this->t('Book');
    $url = Url::fromRoute('entity.node.canonical', ['node' => $book_id]);
    $book_url = $url->toString();

    $book_tree = $this->bookManager->bookTreeAllData($book_id);
    $key = array_key_first($book_tree);
    $first = $book_tree[$key];
    $activeTrail = $this->buildTreeItems($book_tree, $current_nid, $can_admin);

    $build = [
      '#attached' => ['library' => ['az_book/az-book-block'],],
      '#theme' => 'az_book_block',
      '#book_id' => $book_id,
      '#book_url' => $book_url,
      '#book_tree' => $book_tree,
      '#book_title' => $book_title,
      '#book_title_classes' => 'az-book-block',
      '#can_admin' => $can_admin,
      '#status' => false,
    ];

    return $build;
  }

  /**
   * Builds the tree recursively.
   */
  protected function buildTreeItems(array &$tree, $current_nid, $can_admin) {
    $items = [];

    $isActiveTrail = false;

    foreach ($tree as &$item) {
      $node = $item['link']['nid'] ? Node::load($item['link']['nid']) : NULL;
      if (!$node) {
        continue;
      }

      // Skip unpublished nodes unless admin toggle will handle them.
      $is_unpublished = !$node->isPublished();
      if ($is_unpublished && !$can_admin) {
        continue;
      }

      if ($node->hasField('field_sub_title')) {
        $field = $node->get('field_sub_title');
        error_log('Field value raw: ' . print_r($field->getValue(), true));
        error_log('Is empty: ' . ($field->isEmpty() ? 'YES' : 'NO'));
      } else {
        error_log('Field does not exist on this node.');
      }

      // ✅ Build a proper link to the node.
      $url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()]);
//    if ($node->hasField('field_sub_title') && !$node->get('field_sub_title')->isEmpty()) {
      if ($node->hasField('field_sub_title')) {
        $field_sub_title = $node->get('field_sub_title')->value;
      }
      $link = Link::fromTextAndUrl($node->getTitle(), $url)->toRenderable();
      $link['#attributes']['class'][] = 'az-book-link';
      $link['#attributes']['class'][] = 'az-book-ajax-link';
      $link['#attributes']['data-nid'] = $node->id();
      $link['#options']['attributes']['title'] = $field_sub_title;
      $item['markup'] = \Drupal::service('renderer')->renderPlain($link);


      $item['classes'][] = 'book-item';
      if (!$node->isPublished()) {
        $item['classes'][] = 'is-unpublished';
      }

      // Add active class for the current node.
      if ($node->id() == $current_nid) {
        $item['classes'][] = 'is-current';
        $isActiveTrail = true;
      }

      // Check that the node has the field and it's not empty
      if ($node->hasField('field_stage') && !$node->get('field_stage')->isEmpty()) {
        // Get the referenced term ID(s)
        $term_ids = $node->get('field_stage')->getValue(); // array of arrays ['target_id' => X]
        $tid = $term_ids[0]['target_id'];

        // Load the term to get its name
        $term = \Drupal\taxonomy\Entity\Term::load($tid);
        $stage_name = $term->getName();
        switch ($stage_name) {
          case 'Placeholder':
            $item['classes'][] = 'is-placeholder';
            break;
          case 'Draft':
            $item['classes'][] = 'is-draft';
            break;
          case 'Review':
            $item['classes'][] = 'is-review';
            break;
          case 'Published':
            $item['classes'][] = 'is-published';
            break;
          case 'Hold':
            $item['classes'][] = 'is-hold';
            break;
          case 'Rejected':
            $item['classes'][] = 'is-rejected';
            break;
          case 'Archived':
            $item['classes'][] = 'is-archived';
            break;
        }

        // Print or use the stage name
        // echo $stage_name;
      }
      if (!empty($item['below'])) {
        $item['classes'][] = 'has-children';
        $isActive = $this->buildTreeItems($item['below'], $current_nid, $can_admin);
        if ($isActive) {
          $item['classes'][] = 'is-expanded';
          $isActiveTrail = true;
        }
      }
    }
    return $isActiveTrail;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Everyone can view it, unless node access restricts it.
    return AccessResult::allowed();
  }

}
