<?php
/*
 Plugin Name: Open Graph
 Plugin URI: http://wordpress.org/extend/plugins/opengraph
 Description: Adds Open Graph metadata to your pages
 Author: Will Norris
 Author URI: http://willnorris.com/
 Version: 1.0-trunk
 License: Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0.html)
 Text Domain: opengraph
 */


define('OPENGRAPH_NS_URI', 'http://opengraphprotocol.org/schema/');
$opengraph_ns_set = false;


/**
 * Add Open Graph XML namespace to <html> element.
 */
function opengraph_add_namespace( $output ) {
	global $opengraph_ns_set;
	$opengraph_ns_set = true;

	$output .= ' xmlns:og="' . esc_attr(OPENGRAPH_NS_URI) . '"';
	return $output;
}
add_filter('language_attributes', 'opengraph_add_namespace');


/**
 * Get the Open Graph metadata for the current page.
 *
 * @uses apply_filters() Calls 'opengraph_metadata' before returning metadata array
 */
function opengraph_metadata() {
	$metadata = array();

	$properties = array('og:title', 'og:type', 'og:image', 'og:url', 'og:site_name', 'og:description');
	foreach ($properties as $property) {
		$filter = 'opengraph_metadata_' . $property;
		$metadata[$property] = apply_filters($filter, '');
	}

	return apply_filters('opengraph_metadata', $metadata);
}


/**
 * Register filters for default Open Graph metadata.
 */
function opengraph_default_metadata() {
	add_filter('opengraph_metadata_og:title', 'opengraph_default_title', 5);
	add_filter('opengraph_metadata_og:type', 'opengraph_default_type', 5);
	add_filter('opengraph_metadata_og:image', 'opengraph_default_image', 5);
	add_filter('opengraph_metadata_og:url', 'opengraph_default_url', 5);
}
add_filter('wp', 'opengraph_default_metadata');


/**
 * Default title property.
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
	if ( empty($type) ) $type = 'blog';
	return $type;
}


/**
 * Default image property.
 */
function opengraph_default_image( $image ) {
	global $post;
	if ( is_singular() && empty($image) && has_post_thumbnail($post->ID) ) {
		$thumbnail = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'post-thumbnail');
		if ($thumbnail) {
			$image = $thumbnail[0];
		}
	}

	return $image;
}


/**
 * Default url property.
 */
function opengraph_default_url( $url ) {
	if ( empty($url) ) $url = get_permalink();
	return $url;
}


/**
 * Output Open Graph <meta> tags in the page header.
 */
function opengraph_meta_tags() {
	global $opengraph_ns_set;

	$xml_ns = '';
	if ( !$opengraph_ns_set ) {
		$xml_ns = 'xmlns:og="' . esc_attr(OPENGRAPH_NS_URI) . '" ';
	}

	$metadata = opengraph_metadata();
	foreach ( $metadata as $key => $value ) {
		if (empty($key) || empty($value)) continue;
		echo '<meta ' . $xml_ns . 'property="' . esc_attr($key) . '" content="' . esc_attr($value) . '" />' . "\n";
	}
}
add_action('wp_head', 'opengraph_meta_tags');

