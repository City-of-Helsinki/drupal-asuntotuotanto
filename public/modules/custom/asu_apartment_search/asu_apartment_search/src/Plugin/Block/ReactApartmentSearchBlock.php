<?php

namespace Drupal\asu_apartment_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'ReactApartmentSearchBlock' block.
 *
 * @Block(
 *  id = "react_apartment_search_block",
 *  admin_label = @Translation("React - Apartment search block"),
 * )
 */
class ReactApartmentSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // @codingStandardsIgnoreStart
    /*
      @TODO: Change the ID for react widget and create template if necessary.
        Apartment search uses it's own route. Override the route with this block
        when frontend theme is ready and apartment search is needed to be
        implemented via blocks.

    $build['asu_apartment_search'] = [
      '#markup' => '<div id="search"></div>',
      '#attached' => [
        'library' => 'asu_apartment_search/apartment-search'
      ],
    ];

    */
    // @codingStandardsIgnoreEnd
    return $build;
  }

}
