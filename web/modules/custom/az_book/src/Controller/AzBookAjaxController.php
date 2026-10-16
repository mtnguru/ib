<?php

namespace Drupal\az_book\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;

class AzBookAjaxController extends ControllerBase {

  public function load(Node $node) {
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $render_array = $view_builder->view($node, 'full');

    $html = \Drupal::service('renderer')->renderRoot($render_array);

    return new JsonResponse([
      'html' => $html,
      'title' => $node->getTitle(),
      'nid' => $node->id(),
    ]);
  }
}
