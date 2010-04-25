=== Open Graph ===
Contributors: willnorris
Tags: social, opengraph, facebook
Tested up to: 3.0
Stable tag: trunk

Add Open Graph metadata to your pages.

== Description ==

The [Open Graph Protocol][] defines a mechanism for adding additional metadata to webpages to
identify them as "social objects".  Most notably, this allows for these pages to be used with
the [Facebook Like Button][].  This plugin inserts the Open Graph metadata into WordPress posts
and pages, and provides a simple extension mechansim for other plugins to override this data,
or to provide additional Open Graph data.

Note that this plugin does not actually add the Facebook Like Button to your pages, or do
anything directly with the Open Graph data.  It makes the data available so that other services
can do interesting things with it.

[Open Graph Protocol]: http://opengraphprotocol.org/
[Facebook Like Button]: http://developers.facebook.com/docs/reference/plugins/like


== Frequently Asked Questions ==

= How do I extend the Open Graph plugin? =

There are two main ways to provide Open Graph metadata from your plugin or theme.  First, you can
implement the filter for a specific property.  These filters are of the form `opengraph_{name}`
where {name} is the unqualified Open Graph property name.  For example, if you have a plugin that
defines a custom post type named "movie", you could override the Open Graph 'type' property for
those posts using a function like:

	function my_og_type( $type ) {
	    if ( get_post_type() == "movie" ) {
	        $type = "movie";
	    }
	    return $type;
	}
	add_filter( 'opengraph_type', 'my_og_type' );

This will work for all of the core Open Graph properties.  However, if you want to add a custom 
property, such as 'fb:admin', then you would need to hook into the `opengraph_metadata` filter.
This filter is passed an associative array, whose keys are the qualified Open Graph property names.
For example:

	function my_og_metadata( $metadata ) {
	    $metadata['fb:admin'] = '12345,67890';
	    return $metadata;
	}
	add_filter( 'opengraph_metadata', 'my_og_metadata' );

Note that it is your responsibility to make sure that the XML namespace is registered for the
prefix that you use.  To see one method for doing this, see the `opengraph_add_namespace` function
in the Open Graph plugin.

