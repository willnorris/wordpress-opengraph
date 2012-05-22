<?php
/*
 Plugin Name: Open Graph
 Plugin URI: http://wordpress.org/extend/plugins/opengraph
 Description: Adds Open Graph metadata to your pages
 Author: Will Norris
 Author URI: http://willnorris.com/
 Version: 1.3
 License: Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0.html)
 Text Domain: opengraph
 */


define('OPENGRAPH_PREFIX_URI', 'http://ogp.me/ns#');
$opengraph_prefix_set = false;


/**
 * Add Open Graph XML prefix to <html> element.
 *
 * @uses apply_filters calls 'opengraph_prefixes' filter on RDFa prefix array
 */
function opengraph_add_prefix( $output ) {
  $prefixes = array(
    'og' => OPENGRAPH_PREFIX_URI
  );
  $prefixes = apply_filters('opengraph_prefixes', $prefixes);

  $prefix_str = '';
  foreach( $prefixes as $k => $v ) {
    $prefix_str .= $k . ': ' . $v . ' ';
  }
  $prefix_str = trim($prefix_str);

  if (preg_match('/(prefix\s*=\s*[\"|\'])/i', $output)) {
    $output = preg_replace('/(prefix\s*=\s*[\"|\'])/i', '${1}' . $prefix_str, $output);
  } else {
    $output .= ' prefix="' . $prefix_str . '"';
  }
  return $output;
}
add_filter('language_attributes', 'opengraph_add_prefix');


/**
 * Get the Open Graph metadata for the current page.
 *
 * @uses apply_filters() Calls 'opengraph_{$name}' for each property name
 * @uses apply_filters() Calls 'opengraph_metadata' before returning metadata array
 */
function opengraph_metadata() {
  $metadata = array();

   // defualt properties defined at http://ogp.me/
  $properties = array(
    // required
    'title', 'type', 'image', 'url',

    // optional
    'audio', 'description', 'determiner', 'locale', 'site_name', 'video',
  );

  foreach ($properties as $property) {
    $filter = 'opengraph_' . $property;
    $metadata["og:$property"] = apply_filters($filter, '');
  }
  return apply_filters('opengraph_metadata', $metadata);
}


/**
 * Register filters for default Open Graph metadata.
 */
function opengraph_default_metadata() {
  add_filter('opengraph_title', 'opengraph_default_title', 5);
  add_filter('opengraph_type', 'opengraph_default_type', 5);
  add_filter('opengraph_image', 'opengraph_default_image', 5);
  add_filter('opengraph_url', 'opengraph_default_url', 5);

  add_filter('opengraph_description', 'opengraph_default_description', 5);
  add_filter('opengraph_locale', 'opengraph_default_locale', 5);
  add_filter('opengraph_site_name', 'opengraph_default_sitename', 5);
}
add_filter('wp', 'opengraph_default_metadata');


/**
 * Default title property, using the page title.
 */
function opengraph_default_title( $title ) {
  if ( is_singular() && empty($title) ) {
    global $post;
    $title = $post->post_title;
  }
  return $title;
}


/**
 * Default type property.
 */
function opengraph_default_type( $type ) {
  if ( is_singular( array('post', 'page', 'aside', 'status') ) && empty($type) ) {
    $type = 'article';
  } elseif ( empty($type) ) {
    $type = 'blog';
  }
  return $type;
}


/**
 * Default image property, using the post-thumbnail.
 */
function opengraph_default_image( $image ) {
  global $post;
  if ( function_exists('has_post_thumbnail') ) {
    if ( is_singular() && empty($image) && has_post_thumbnail($post->ID) ) {
      $thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'post-thumbnail');
      if ($thumbnail) {
        $image = $thumbnail[0];
      }
    }
  }
  return $image;
}


/**
 * Default url property, using the permalink for the page.
 */
function opengraph_default_url( $url ) {
  if ( is_singular() && empty($url) ) $url = get_permalink();
  return $url;
}


/**
 * Default site_name property, using the bloginfo name.
 */
function opengraph_default_sitename( $name ) {
  if ( empty($name) ) $name = get_bloginfo('name');
  return $name;
}


/**
 * Default description property, using the bloginfo description.
 */
function opengraph_default_description( $description ) {
  if ( is_singular() && empty($description) ) {
    if ( has_excerpt() ) {
      $description = get_the_excerpt();
    } else {
      global $post;
      $description = wp_trim_words( strip_shortcodes($post->post_content), 25, '...' );
    }
  } elseif ( empty($description) ) {
    $description = get_bloginfo('description');
  }
  return $description;
}


/**
 * Default locale property, using the WordPress locale.
 */
function opengraph_default_locale( $locale ) {
  if ( empty($locale) ) $locale = get_locale();
  return $locale;
}


/**
 * Output Open Graph <meta> tags in the page header.
 */
function opengraph_meta_tags() {
  $metadata = opengraph_metadata();
  foreach ( $metadata as $key => $value ) {
    if ( empty($key) || empty($value) ) continue;
    if ( is_array( $value ) ) {
      foreach ( $value as $v ) {
        echo '<meta property="' . esc_attr($key) . '" content="' . esc_attr($v) . '" />' . "\n";
      }
    } else {
      echo '<meta property="' . esc_attr($key) . '" content="' . esc_attr($value) . '" />' . "\n";
    }
  }
}
add_action('wp_head', 'opengraph_meta_tags');

