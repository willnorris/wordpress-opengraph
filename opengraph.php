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

	$output .= ' xmlns:og="' . OPENGRAPH_NS_URI . '"';
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

	$properties = array('og:title', 'og:type', 'og:image', 'og:url');
	foreach ($properties as $property) {
		$filter = 'opengraph_metadata_' . $property;
		$metadata[$property] = apply_filters($filter, '');
	}

	return apply_filters('opengraph_metadata', $metadata);
}


/**
 * Register filters for default Open Graph metadata.
 */
function opengraph_default_metadata( $metadata ) {
	add_filter('opengraph_metadata_og:type', 
		create_function('$v', 'return empty($v) ? "blog" : $v;'), 5);
}
add_filter('wp', 'opengraph_default_metadata');


/**
 * Output Open Graph <meta> tags in the page header.
 */
function opengraph_meta_tags() {
	global $opengraph_ns_set;

	$xml_ns = '';
	if ( !$opengraph_ns_set ) {
		$xml_ns = 'xmlns:og="' . OPENGRAPH_NS_URI . '" ';
	}

	$metadata = opengraph_metadata();
	foreach ( $metadata as $key => $value ) {
		if (empty($key) || empty($value)) continue;
		echo '<meta ' . $xml_ns . 'property="' . $key . '" content="' . $value . '" />' . "\n";
	}
}
add_action('wp_head', 'opengraph_meta_tags');

