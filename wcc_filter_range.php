<?php
/*
Plugin Name: Woocommerce Range Filter
Plugin URI: https://bhavyasaggi.github.io/plugins/woocommerce-range-filter
Description: Woocommerce Range Filter
Version: 0.1.0
Author: Bhavya Saggi
Author URI: https://bhavyasaggi.github.io/
License: MIT

------------------------------------------------------------------------

Copyright

*/

// use Automattic\WooCommerce\Internal\ProductAttributesLookup\Filterer;

/* Convert a string representing a number to a float */
function to_numerical($attr)
{
  if (is_numeric($attr)) {
    return floatval($attr);
  }
  if (is_string($attr)) {
    if (strpos($attr, ',') !== false) {
      $attr = str_replace(',', '.', $attr);
      if (is_numeric($attr)) {
        return floatval($attr);
      }
    } else if (strpos($attr, '/') !== false) {
      $exploded = explode('/', $attr);
      if (count($exploded) > 2 || !is_numeric($exploded[0]) || !is_numeric($exploded[1])) {
        return false;
      }
      $num = floatval($exploded[0]);
      $den = floatval($exploded[1]);
      return $num / $den;
    }
  }
  return false;
}

/* Filter an array by keys */
function array_filter_key($input, $callback)
{
  if (!is_array($input)) {
    trigger_error('array_filter_key() expects parameter 1 to be array, ' . gettype($input) . ' given', E_USER_WARNING);
    return null;
  }

  if (empty($input)) {
    return $input;
  }

  $filtered_keys = array_filter(array_keys($input), $callback);
  if (empty($filtered_keys)) {
    return array();
  }

  $input = array_intersect_key(array_flip($filtered_keys), $input);

  return $input;
}

/* Check if a string ends with another string*/
function ends_with($haystack, $needle)
{
  $length = strlen($needle);
  if ($length == 0) {
    return true;
  }

  return (substr($haystack, -$length) === $needle);
}

function wcc_filter_taxonomy_terms($taxonomy, $query_type)
{
  global $wp;

  if (!taxonomy_exists($taxonomy)) {
    return 'non-existent ' . $taxonomy;
  }

  $_chosen_taxonomy = is_tax() ? get_queried_object()->taxonomy : '';
  if ($taxonomy === $_chosen_taxonomy) {
    return 'is-chosen ' . $taxonomy;
  }

  $terms = get_terms(
    $taxonomy
    ,
    array('hide_empty' => '1')
  );
  if (0 === count($terms)) {
    return 'empty ' . $taxonomy;
  }

  // $term_ids = wp_list_pluck($terms, 'term_id');
  // $term_counts = wc_get_container()->get(Filterer::class)->get_filtered_term_product_counts($term_ids, $taxonomy, $query_type);

  $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes();
  $current_values = isset($_chosen_attributes[$taxonomy]['terms']) ? $_chosen_attributes[$taxonomy]['terms'] : array();

  $terms_data = array();
  foreach ($terms as $term) {
    $current_term_id = absint(is_tax() ? get_queried_object()->term_id : 0);
    if ($term->term_id === $current_term_id) {
      continue;
    }

    $is_set = in_array($term->slug, $current_values, true);
    // $count = isset($term_counts[$term->term_id]) ? $term_counts[$term->term_id] : 0;

    $terms_data[] = array(
      "name" => $term->name,
      "slug" => $term->slug,
      "count" => $term->count,
      "taxonomy" => $term->taxonomy,
      "term_id" => $term->term_id,
      "term_taxonomy_id" => $term->term_taxonomy_id,
      "is_set" => $is_set,
      // "count_active" => $count,
    );

  }

  return $terms_data;

}

function wcc_filter_range($query)
{
  $tax_query = array();

  $min_value_keys = array_filter_key($_GET, function ($key) {
    return ends_with($key, 'min_value');
  });
  ksort($min_value_keys);
  $max_value_keys = array_filter_key($_GET, function ($key) {
    return ends_with($key, 'max_value');
  });
  ksort($max_value_keys);

  // Needs work.
  if (!is_array($min_value_keys) || !is_array($max_value_keys)) {
    return;
  }

  $attribute_names = array_map(function ($key) {
    return explode('_', $key)[0];
  }, array_keys(array_merge($min_value_keys, $max_value_keys)));

  $tax_query['relation'] = 'AND';
  foreach ($attribute_names as $key => $attribute_name) {
    $attribute_min_value = (isset($_GET[$attribute_name . '_min_value']) ? $_GET[$attribute_name . '_min_value'] : '');
    $attribute_max_value = (isset($_GET[$attribute_name . '_max_value']) ? $_GET[$attribute_name . '_max_value'] : '');
    $min_value = to_numerical($attribute_min_value);
    $max_value = to_numerical($attribute_max_value);
    if ($min_value === false) {
      $min_value = -INF;
    }
    if ($max_value === false) {
      $max_value = INF;
    }

    $terms = get_terms('pa_' . $attribute_name);
    $amps = [];
    foreach ($terms as $key => $term) {
      $value = to_numerical($term->name);
      if ($value && $value <= $max_value && $value >= $min_value) {
        $amps[] = $term->term_id;
      }
    }

    array_push(
      $tax_query,
      array(
        'taxonomy' => 'pa_' . $attribute_name,
        'terms' => $amps,
        'operator' => 'IN'
      )
    );
  }

  if (!empty($query->get('tax_query'))) {
    $tax_query = array_merge($query->get('tax_query'), $tax_query);
  }

  $query->set('tax_query', $tax_query);
}

function wcc_filter_range_init()
{
  add_action('woocommerce_product_query', 'wcc_filter_range');
}

add_action('init', 'disable_bloat');

?>