=== Open Graph ===
Contributors: willnorris
Tags: social, opengraph, ogp, facebook
Requires at least: 2.3
Tested up to: 3.3.2
Stable tag: 1.4
License: Apache License, Version 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0.html


Adds Open Graph metadata to your pages so that they look great when shared on
sites like Facebook and Google+.

== Description ==

The [Open Graph protocol][] enables any web page to become a rich object in a
social graph.  Most notably, this allows for these pages to be used with
Facebook's [Like Button][] and [Graph API][] as well as within [Google+][]
posts.

The Open Graph plugin inserts the Open Graph metadata into WordPress posts and
pages, and provides a simple extension mechansim for other plugins and themes
to override this data, or to provide additional Open Graph data.

This plugin does not directly add social plugins like the Facebook Like Button
or the Google [+1 Button][] to your pages (though they're pretty simple to
add).  It makes the data available so that other services can do interesting
things with it.

[Open Graph Protocol]: http://ogp.me/
[Like Button]: https://developers.facebook.com/docs/reference/plugins/like
[Graph API]: https://developers.facebook.com/docs/reference/api/
[Google+]: http://www.google.com/+
[+1 Button]: https://developers.google.com/+/plugins/+1button/


== Frequently Asked Questions ==

= How do I extend the Open Graph plugin? =

There are two main ways to provide Open Graph metadata from your plugin or
theme.  First, you can implement the filter for a specific property.  These
filters are of the form `opengraph_{name}` where {name} is the unqualified Open
Graph property name.  For example, if you have a plugin that defines a custom
post type named "movie", you could override the Open Graph 'type' property for
those posts using a function like:

    function my_og_type( $type ) {
        if ( get_post_type() == "movie" ) {
            $type = "movie";
        }
        return $type;
    }
    add_filter( 'opengraph_type', 'my_og_type' );

This will work for all of the core Open Graph properties.  However, if you want
to add a custom property, such as 'fb:admin', then you would need to hook into
the `opengraph_metadata` filter.  This filter is passed an associative array,
whose keys are the qualified Open Graph property names.  For example:

    function my_og_metadata( $metadata ) {
        $metadata['fb:admin'] = '12345,67890';
        return $metadata;
    }
    add_filter( 'opengraph_metadata', 'my_og_metadata' );

Note that you may need to define the RDFa prefix for your properties.  Do this
using the `opengraph_prefixes` filter.


== Changelog ==

= version 1.4 (Aug 24, 2012) =
 - better default description
 - include all images that are attached to a post, so that users can choose
   which to use when sharing the page.  If the post has a post thumbnail, that
   is still used as the primary image.

= version 1.3 (May 21, 2012) =
 - add 'opengraph_prefixes' filter for defining additional prefixes
 - add new basic properties, and remove some old ones.  This is a breaking
   change for anyone that was using the old properties, but they can always be
   added using the 'opengraph_metadata' filter.  (see [f476552][] for details)
 - updates to many default values, particularly for individual posts and pages
   (thanks pfefferle)
 - add basic support for array values (see [d987eb7][])

[f476552]: https://github.com/willnorris/wordpress-opengraph/commit/f47655202d59c0e5b5032b4b86764f7a87813640
[d987eb7]: https://github.com/willnorris/wordpress-opengraph/commit/d987eb76e2da1431e5df3311fde3d9c2407b06f5

= version 1.2 (Feb 21, 2012) =
 - switch to newer RDFa prefix syntax rather than XML namespaces (props
   pfefferle) 

= version 1.1 (Nov 7, 2011) =
 - fix function undefined error when theme doesn't support post thumbnails

= version 1.0 (Apr 24, 2010) =
 - initial public release

