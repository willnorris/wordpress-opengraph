<?php
/**
 * Plugin Name: Open Graph
 * Plugin URI: https://wordpress.org/plugins/opengraph
 * Description: Adds Open Graph metadata to your pages
 * Author: Will Norris & Matthias Pfefferle
 * Author URI: https://github.com/pfefferle/wordpress-opengraph
 * Version: 2.0.2
 * License: Apache License, Version 2.0
 * License URI: http://www.apache.org/licenses/LICENSE-2.0.html
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: opengraph
 *
 * @package opengraph
 */

// If you have the opengraph plugin running alongside jetpack, we assume you'd
// rather use our opengraph support, so disable jetpack's opengraph functionality.
add_filter( 'jetpack_enable_opengraph', '__return_false' );
add_filter( 'jetpack_enable_open_graph', '__return_false' );


// Disable strict mode by default.
defined( 'OPENGRAPH_STRICT_MODE' ) || define( 'OPENGRAPH_STRICT_MODE', false );
// Set the maximum number of images to include in Open Graph metadata.
defined( 'OPENGRAPH_MAX_IMAGES' ) || define( 'OPENGRAPH_MAX_IMAGES', 3 );

/**
 * Add Open Graph XML prefix to <html> element.
 *
 * @uses apply_filters calls 'opengraph_prefixes' filter on RDFa prefix array
 *
 * @param string $output The current list of prefixes.
 *
 * @return string The updated list of prefixes.
 */
function opengraph_add_prefix( $output ) {
	$prefixes = array(
		'og' => 'http://ogp.me/ns#',
	);
	$prefixes = apply_filters( 'opengraph_prefixes', $prefixes );

	$prefix_str = implode(
		' ',
		array_map(
			function ( $k, $v ) {
				return $k . ': ' . $v;
			},
			array_keys( $prefixes ),
			$prefixes
		)
	);

	$output = preg_replace( '/(prefix\s*=\s*[\"|\'])/i', '${1}' . $prefix_str . ' ', $output, -1, $count );

	if ( ! $count ) {
		$output .= ' prefix="' . esc_attr( $prefix_str ) . '"';
	}

	return $output;
}
add_filter( 'language_attributes', 'opengraph_add_prefix' );


/**
 * Add additional prefix namespaces that are supported by the opengraph plugin.
 *
 * @param array $prefixes The current list of prefixes.
 *
 * @return array The updated list of prefixes.
 */
function opengraph_additional_prefixes( $prefixes ) {
	if ( is_author() ) {
		$prefixes['profile'] = 'http://ogp.me/ns/profile#';
	}
	if ( is_singular() ) {
		$prefixes['article'] = 'http://ogp.me/ns/article#';
	}

	return $prefixes;
}


/**
 * Get the Open Graph metadata for the current page.
 *
 * @uses apply_filters() Calls 'opengraph_{$name}' for each property name
 * @uses apply_filters() Calls 'twitter_{$name}' for each property name
 * @uses apply_filters() Calls 'opengraph_metadata' before returning metadata array
 */
function opengraph_metadata() {
	$metadata = array();

	// Namespace => filter prefix and default properties.
	$namespaces = array(
		// Default properties defined at http://ogp.me/.
		'og'        => array(
			'opengraph',
			array(
				// Required properties.
				'title'       => '',
				'type'        => '',
				'image'       => array(),
				'url'         => '',

				// Optional properties.
				'audio'       => array(),
				'description' => '',
				'determiner'  => '',
				'locale'      => '',
				'site_name'   => '',
				'video'       => array(),
			),
		),
		'twitter'   => array(
			'twitter',
			array(
				'card'    => '',
				'creator' => '',
			),
		),
		'fediverse' => array(
			'fediverse',
			array(
				'creator' => array(),
			),
		),
	);

	foreach ( $namespaces as $namespace => list( $filter_prefix, $properties ) ) {
		foreach ( $properties as $property => $default ) {
			/**
			 * Filter a single metadata property.
			 *
			 * The dynamic portion of the hook name, `$filter_prefix`, is one of
			 * `opengraph`, `twitter` or `fediverse`; `$property` is the property
			 * name, e.g. `opengraph_title` or `twitter_card`.
			 *
			 * @param string|array $default  The default value.
			 * @param array        $metadata The metadata collected so far. Open Graph
			 *                               properties are collected first, so the
			 *                               Twitter and Fediverse filters can read them.
			 */
			$metadata[ "$namespace:$property" ] = apply_filters( "{$filter_prefix}_{$property}", $default, $metadata );
		}
	}

	/**
	 * Filter the Open Graph metadata.
	 *
	 * @param array $metadata The metadata array.
	 */
	return apply_filters( 'opengraph_metadata', $metadata );
}


/**
 * Register filters for default Open Graph metadata.
 */
function opengraph_default_metadata() {
	// Core metadata attributes.
	add_filter( 'opengraph_title', 'opengraph_default_title', 5 );
	add_filter( 'opengraph_type', 'opengraph_default_type', 5 );
	add_filter( 'opengraph_url', 'opengraph_default_url', 5 );

	// Image metadata attributes with fallbacks.
	add_filter( 'opengraph_image', 'opengraph_default_image', 5 );
	add_filter( 'opengraph_image', 'opengraph_fallback_image', 35 );

	add_filter( 'opengraph_description', 'opengraph_default_description', 5 );
	add_filter( 'opengraph_locale', 'opengraph_default_locale', 5 );
	add_filter( 'opengraph_site_name', 'opengraph_default_sitename', 5 );
	add_filter( 'opengraph_audio', 'opengraph_default_audio', 5 );
	add_filter( 'opengraph_video', 'opengraph_default_video', 5 );

	// Additional prefixes.
	add_filter( 'opengraph_prefixes', 'opengraph_additional_prefixes' );

	// Additional profile metadata.
	add_filter( 'opengraph_metadata', 'opengraph_profile_metadata' );

	// Additional article metadata.
	add_filter( 'opengraph_metadata', 'opengraph_article_metadata' );

	// twitter card metadata.
	add_filter( 'twitter_card', 'twitter_default_card', 5, 2 );
	add_filter( 'twitter_creator', 'twitter_default_creator', 5 );

	// fediverse creator metadata.
	add_filter( 'fediverse_creator', 'fediverse_default_creator', 5 );
}
add_action( 'wp', 'opengraph_default_metadata' );


/**
 * Default title property, using the page title.
 *
 * @param string $title The current title.
 *
 * @return string The title.
 */
function opengraph_default_title( $title ) {
	if ( $title ) {
		return $title;
	}

	// Set default title, because twitter is requiring one.
	$title = __( 'Untitled', 'opengraph' );

	if ( is_home() || is_front_page() ) {
		$title = get_bloginfo( 'name' );
	} elseif ( is_singular() ) {
		$title = get_the_title( get_queried_object_id() );
		// Fall back to description.
		if ( empty( $title ) ) {
			$title = opengraph_default_description( null, 5 );
		}
	} elseif ( is_author() ) {
		$author = get_queried_object();
		$title  = $author->display_name;
	} elseif ( ( is_category() || is_tag() ) && single_term_title( '', false ) ) {
		$title = single_term_title( '', false );
	} elseif ( is_archive() && get_post_format() ) {
		$title = get_post_format_string( get_post_format() );
	} elseif ( is_archive() && get_the_archive_title() ) {
		$title = get_the_archive_title();
	}

	return wp_strip_all_tags( $title );
}


/**
 * Default type property.
 *
 * @param string $type The current type.
 *
 * @return string The type.
 */
function opengraph_default_type( $type = '' ) {
	if ( empty( $type ) ) {
		if ( is_singular( array( 'post', 'page' ) ) ) {
			$type = 'article';
		} elseif ( is_author() ) {
			$type = 'profile';
		} else {
			$type = 'website';
		}
	}

	return $type;
}


/**
 * Default image property.
 *
 * The avatar on author pages, otherwise the images of the queried post, see
 * opengraph_image_ids().
 *
 * @param array $image The current list of images.
 *
 * @return array The list of images.
 */
function opengraph_default_image( $image = array() ) {
	// Show avatar on profile pages.
	if ( is_author() ) {
		return array( get_avatar_url( get_queried_object_id(), array( 'size' => 512 ) ) );
	}

	$max_images = opengraph_max_images();
	$limit      = $max_images - count( $image );

	$post_id = get_queried_object_id();

	if ( is_singular() && ! post_password_required( $post_id ) && $limit > 0 ) {
		foreach ( opengraph_image_ids( $post_id, $limit ) as $id ) {
			$image[] = wp_get_attachment_image_url( $id, 'large' );
		}
	}

	$image = array_values( array_unique( array_filter( $image ) ) );

	return array_slice( $image, 0, $max_images );
}


/**
 * Get the attachment IDs of the images of a post.
 *
 * Walks the image sources in order (post thumbnail, content images, attached
 * images) and stops as soon as enough unique image attachments are found, so
 * the more expensive sources only run when the cheaper ones did not fill the
 * list. IDs that do not belong to an image attachment (deleted, or not an
 * image) are skipped and do not count toward the limit.
 *
 * @param int $post_id The post ID.
 * @param int $limit   The maximum number of IDs.
 *
 * @return int[] The attachment IDs.
 */
function opengraph_image_ids( $post_id, $limit ) {
	if ( is_attachment() ) {
		return wp_attachment_is_image( $post_id ) ? array( $post_id ) : array();
	}

	/**
	 * Filter the image sources of a post.
	 *
	 * Each source is a callable that takes the post ID and returns (or yields)
	 * attachment IDs, most relevant first.
	 *
	 * @param callable[] $sources The image sources, in order.
	 */
	$sources = apply_filters(
		'opengraph_image_sources',
		array(
			'opengraph_thumbnail_image_ids',
			'opengraph_content_image_ids',
			'opengraph_attached_image_ids',
		)
	);

	$ids = array();

	foreach ( $sources as $source ) {
		foreach ( call_user_func( $source, $post_id ) as $id ) {
			$id = (int) $id;

			// Skip duplicates and anything that is not an image attachment,
			// so they do not use up a slot.
			if ( ! $id || isset( $ids[ $id ] ) || ! wp_attachment_is_image( $id ) ) {
				continue;
			}

			$ids[ $id ] = true;

			if ( count( $ids ) >= $limit ) { // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found -- $ids changes in the loop.
				break 2;
			}
		}
	}

	return array_keys( $ids );
}


/**
 * Image source: the post thumbnail.
 *
 * @param int $post_id The post ID.
 *
 * @return int[] The attachment IDs.
 */
function opengraph_thumbnail_image_ids( $post_id ) {
	return has_post_thumbnail( $post_id ) ? array( get_post_thumbnail_id( $post_id ) ) : array();
}


/**
 * Image source: the `<img>` tags in the post content.
 *
 * One pass over the raw post content covers images inserted through the
 * block editor (image, cover, gallery, media & text, nested blocks) as well
 * as classic editor content. IDs are yielded one by one, so the caller can
 * stop early without resolving the remaining images.
 *
 * @param int $post_id The post ID.
 *
 * @return Generator<int> The attachment IDs.
 */
function opengraph_content_image_ids( $post_id ) {
	$tags = new WP_HTML_Tag_Processor( get_post_field( 'post_content', $post_id, 'raw' ) );

	while ( $tags->next_tag( 'img' ) ) {
		$id = opengraph_image_tag_to_id( $tags );

		if ( $id ) {
			yield $id;
		}
	}
}


/**
 * Get the attachment ID of an `<img>` tag.
 *
 * The editor adds a `wp-image-{id}` class to every inserted image, which is
 * what core itself uses to look up attachments in the content. Only images
 * without that class are resolved through their URL, which is expensive.
 *
 * @param WP_HTML_Tag_Processor $tags The tag processor, positioned on an `<img>` tag.
 *
 * @return int The attachment ID, or 0 if none was found.
 */
function opengraph_image_tag_to_id( $tags ) {
	$class = $tags->get_attribute( 'class' );

	if ( is_string( $class ) && preg_match( '/(?:^|\s)wp-image-([0-9]+)(?:\s|$)/i', $class, $matches ) ) {
		return (int) $matches[1];
	}

	$src = $tags->get_attribute( 'src' );

	if ( ! is_string( $src ) || ! str_starts_with( $src, wp_get_upload_dir()['baseurl'] ) ) {
		return 0;
	}

	return opengraph_attachment_url_to_id( $src );
}


/**
 * Get the attachment ID for an image URL in the uploads directory.
 *
 * Tries the URL as is first, then without query string, then without a
 * `-500x500` size suffix (in case the original is not actually called that),
 * and finally with a `-scaled` suffix for images that were big enough to get
 * scaled down on upload:
 * https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/
 *
 * @param string $src The image URL.
 *
 * @return int The attachment ID, or 0 if none was found.
 */
function opengraph_attachment_url_to_id( $src ) {
	$img_id = attachment_url_to_postid( $src );

	if ( 0 === $img_id ) {
		$src    = strtok( $src, '?' );
		$img_id = attachment_url_to_postid( $src );
	}

	if ( 0 === $img_id ) {
		$src = preg_replace( '/-(?:\d+x\d+)(\.[a-zA-Z]+)$/', '$1', $src, 1, $count );
		if ( $count > 0 ) {
			$img_id = attachment_url_to_postid( $src );
		}
	}

	if ( 0 === $img_id ) {
		$src    = preg_replace( '/(\.[a-zA-Z]+)$/', '-scaled$1', $src );
		$img_id = attachment_url_to_postid( $src );
	}

	return $img_id;
}


/**
 * Image source: the images attached to the post.
 *
 * @param int $post_id The post ID.
 *
 * @return int[] The attachment IDs.
 */
function opengraph_attached_image_ids( $post_id ) {
	// Full post objects, so the post cache is primed for the checks that follow.
	$query = new WP_Query(
		array(
			'post_parent'    => $post_id,
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'order'          => 'ASC',
			'orderby'        => 'menu_order ID',
			'posts_per_page' => opengraph_max_images(),
		)
	);

	return wp_list_pluck( $query->posts, 'ID' );
}

/**
 * Fallback image property, using the site icon, custom logo, or header images.
 *
 * @param array $image The current list of images.
 *
 * @return array The list of images.
 */
function opengraph_fallback_image( $image = array() ) {
	if ( $image ) {
		return $image;
	}

	$max_images = opengraph_max_images();

	// Try site icon.
	if ( has_site_icon() ) {
		$image[] = get_site_icon_url( 512 );
	}

	// Try custom logo second.
	if ( empty( $image ) ) {
		$custom_logo = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'large' );
		if ( $custom_logo ) {
			$image[] = $custom_logo;
		}
	}

	// Try header images.
	if ( empty( $image ) ) {
		if ( is_random_header_image() ) {
			foreach ( get_uploaded_header_images() as $header_image ) {
				$image[] = $header_image['url'];
				if ( count( $image ) >= $max_images ) {
					break;
				}
			}
		} elseif ( get_header_image() ) {
			$image[] = get_header_image();
		}
	}

	return array_unique( $image );
}

/**
 * Append the URLs of media attached to the queried post.
 *
 * @param string $type The media type, e.g. 'audio' or 'video'.
 * @param array  $urls The current list of URLs.
 *
 * @return array The list of URLs.
 */
function opengraph_attached_media_urls( $type, $urls = array() ) {
	if ( ! is_singular() ) {
		return $urls;
	}

	foreach ( get_attached_media( $type, get_queried_object_id() ) as $attachment ) {
		$urls[] = wp_get_attachment_url( $attachment->ID );
	}

	return $urls;
}


/**
 * Default audio property, using get_attached_media.
 *
 * @param array $audio The current list of audio files.
 *
 * @return array The list of audio files.
 */
function opengraph_default_audio( $audio = array() ) {
	return opengraph_attached_media_urls( 'audio', $audio );
}


/**
 * Default video property, using get_attached_media.
 *
 * @param array $video The current list of video files.
 *
 * @return array The list of video files.
 */
function opengraph_default_video( $video = array() ) {
	return opengraph_attached_media_urls( 'video', $video );
}


/**
 * Default url property, using the permalink for the page.
 *
 * @param string $url The current URL.
 *
 * @return string The URL.
 */
function opengraph_default_url( $url = '' ) {
	if ( empty( $url ) ) {
		if ( is_singular() ) {
			$url = get_permalink();
		} elseif ( is_author() ) {
			$url = get_author_posts_url( get_queried_object_id() );
		}
	}

	return esc_url( $url );
}


/**
 * Default site_name property, using the bloginfo name.
 *
 * @param string $name The current site name.
 *
 * @return string The site name.
 */
function opengraph_default_sitename( $name = '' ) {
	if ( empty( $name ) ) {
		$name = get_bloginfo( 'name' );
	}

	return wp_strip_all_tags( $name );
}


/**
 * Default description property, using the excerpt or content for posts, or the
 * bloginfo description.
 *
 * @param string $description The current description.
 * @param int    $length      The maximum length of the description.
 *
 * @return string The description.
 */
function opengraph_default_description( $description = '', $length = 55 ) {
	if ( $description ) {
		return $description;
	}

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( post_password_required( $post ) ) {
			$description = __( 'This content is password protected.', 'opengraph' );
		} elseif ( ! empty( $post->post_excerpt ) ) {
			$description = $post->post_excerpt;
		} else {
			$description = $post->post_content;
		}
	} elseif ( is_author() ) {
		$id          = get_queried_object_id();
		$description = get_user_meta( $id, 'description', true );
	} elseif ( ( is_category() || is_tag() ) && term_description() ) {
		$description = term_description();
	} elseif ( is_archive() && get_the_archive_description() ) {
		$description = get_the_archive_description();
	} else {
		$description = get_bloginfo( 'description' );
	}

	// Strip description to first 55 words. wp_trim_words() strips tags itself,
	// but the `excerpt_more` filter may add HTML back, so strip once more after.
	$description = opengraph_trim_text( strip_shortcodes( $description ), $length );

	return wp_strip_all_tags( $description );
}


/**
 * Default locale property, using the WordPress locale.
 *
 * @param string $locale The current locale.
 *
 * @return string The locale.
 */
function opengraph_default_locale( $locale = '' ) {
	if ( empty( $locale ) ) {
		$locale = get_locale();
	}

	return $locale;
}


/**
 * Default twitter-card type.
 *
 * Twitter takes the image from `og:image`, so the card type only depends on
 * whether a singular post has one: `summary_large_image` if it does,
 * `summary` otherwise.
 *
 * @param string $card     The current card type.
 * @param array  $metadata The metadata collected so far, including `og:image`.
 *
 * @return string The card type.
 */
function twitter_default_card( $card = '', $metadata = array() ) {
	if ( $card ) {
		return $card;
	}

	if ( is_singular() && ! empty( $metadata['og:image'] ) ) {
		return 'summary_large_image';
	}

	return 'summary';
}


/**
 * Get a contact-method value of the author of the queried post.
 *
 * @param string $key The user meta key, e.g. 'twitter'.
 *
 * @return string The value, or an empty string outside singular views.
 */
function opengraph_queried_author_meta( $key ) {
	if ( ! is_singular() ) {
		return '';
	}

	return (string) get_the_author_meta( $key, get_queried_object()->post_author );
}


/**
 * Default twitter-card creator.
 *
 * @see https://developer.twitter.com/en/docs/twitter-for-websites/cards/guides/getting-started
 *
 * @param string $creator The current creator.
 *
 * @return string The creator.
 */
function twitter_default_creator( $creator = '' ) {
	if ( $creator ) {
		return $creator;
	}

	$twitter = opengraph_queried_author_meta( 'twitter' );

	if ( ! $twitter ) {
		return $creator;
	}

	// Check if twitter-account matches "http://twitter.com/username".
	if ( preg_match( '/^http:\/\/twitter\.com\/(#!\/)?(\w+)/i', $twitter, $matches ) ) {
		$creator = '@' . $matches[2];
	} elseif ( preg_match( '/^@?(\w+)$/i', $twitter, $matches ) ) { // Check if twitter-account matches "(@)username".
		$creator = '@' . $matches[1];
	}

	return $creator;
}


/**
 * Default fediverse creator.
 *
 * @see https://github.com/mastodon/mastodon/pull/30398
 *
 * @param string $creator The current creator.
 *
 * @return string The creator.
 */
function fediverse_default_creator( $creator = '' ) {
	$webfinger = opengraph_queried_author_meta( 'fediverse' );

	if ( ! $webfinger ) {
		return $creator;
	}

	return str_replace( 'acct:', '', ltrim( $webfinger, '@' ) );
}


/**
 * Output Open Graph <meta> tags in the page header.
 */
function opengraph_meta_tags() {
	foreach ( opengraph_metadata() as $key => $value ) {
		if ( empty( $key ) ) {
			continue;
		}

		if ( OPENGRAPH_STRICT_MODE !== true ) {
			// Use both the "property" and "name" attributes.
			$template = '<meta property="%1$s" name="%1$s" content="%2$s" />';
		} elseif ( str_starts_with( $key, 'twitter:' ) || str_starts_with( $key, 'fediverse:' ) ) {
			// Use the "name" attribute for Twitter Cards and Fediverse.
			$template = '<meta name="%1$s" content="%2$s" />';
		} else {
			// Use the "property" attribute for Open Graph.
			$template = '<meta property="%1$s" content="%2$s" />';
		}

		foreach ( (array) $value as $v ) {
			// Skip empty values.
			if ( empty( $v ) ) {
				continue;
			}

			printf( $template . PHP_EOL, esc_attr( $key ), esc_attr( $v ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static template, values escaped.
		}
	}
}
add_action( 'wp_head', 'opengraph_meta_tags' );


/**
 * Include profile metadata for author pages.
 *
 * @link http://ogp.me/#type_profile
 *
 * @param array $metadata The current metadata.
 *
 * @return array The updated metadata.
 */
function opengraph_profile_metadata( $metadata ) {
	if ( is_author() ) {
		$id = get_queried_object_id();

		$metadata['profile:first_name'] = get_the_author_meta( 'first_name', $id );
		$metadata['profile:last_name']  = get_the_author_meta( 'last_name', $id );
		$metadata['profile:username']   = get_the_author_meta( 'nicename', $id );
	}

	return $metadata;
}


/**
 * Include article metadata for posts and pages.
 *
 * @link http://ogp.me/#type_article
 *
 * @param array $metadata The current metadata.
 *
 * @return array The updated metadata.
 */
function opengraph_article_metadata( $metadata ) {
	if ( ! is_singular() ) {
		return $metadata;
	}

	$post = get_queried_object();

	// Check if page/post has tags (cached lookup, primed by the main query).
	$tags = get_the_terms( $post->ID, 'post_tag' );
	if ( $tags && ! is_wp_error( $tags ) ) {
		$metadata['article:tag'] = array_merge( (array) ( $metadata['article:tag'] ?? array() ), wp_list_pluck( $tags, 'name' ) );
	}

	// Check if page/post has categories.
	$categories = get_the_category( $post->ID );
	if ( $categories ) {
		$metadata['article:section'][] = $categories[0]->name;
	}

	$metadata['article:published_time'] = get_the_time( 'c', $post->ID );
	$metadata['article:modified_time']  = get_the_modified_time( 'c', $post->ID );
	$metadata['article:author'][]       = get_author_posts_url( $post->post_author );

	$facebook = opengraph_queried_author_meta( 'facebook' );

	if ( $facebook ) {
		$metadata['article:author'][] = $facebook;
	}

	return $metadata;
}


/**
 * Add "twitter" as a contact method
 *
 * @param array $user_contactmethods The current list of contact methods.
 *
 * @return array The updated list of contact methods.
 */
function opengraph_user_contactmethods( $user_contactmethods = array() ) {
	$user_contactmethods['twitter']   = __( 'Twitter', 'opengraph' );
	$user_contactmethods['facebook']  = __( 'Facebook (Profile URL)', 'opengraph' );
	$user_contactmethods['fediverse'] = __( 'Fediverse (username@host.tld)', 'opengraph' );

	return $user_contactmethods;
}
add_filter( 'user_contactmethods', 'opengraph_user_contactmethods', 1 );


/**
 * Add 512x512 icon size
 *
 * @param array $sizes Sizes available for the site icon.
 *
 * @return array updated list of icons.
 */
function opengraph_site_icon_image_sizes( $sizes ) {
	$sizes[] = 512;

	return array_unique( $sizes );
}
add_filter( 'site_icon_image_sizes', 'opengraph_site_icon_image_sizes' );


/**
 * Helper function to trim text using the same default values for length and
 * 'more' text as wp_trim_excerpt.
 *
 * @param string $text The text to trim.
 * @param int    $length The maximum number of words to include.
 *
 * @return string The trimmed text.
 */
function opengraph_trim_text( $text, $length = 55 ) {
	$excerpt_length = apply_filters( 'excerpt_length', $length );
	$excerpt_more   = apply_filters( 'excerpt_more', ' [...]' );

	return wp_trim_words( $text, $excerpt_length, $excerpt_more );
}

/**
 * Get the maximum number of images to include in Open Graph metadata.
 *
 * @return int The maximum number of images to include.
 */
function opengraph_max_images() {
	/**
	 * Filter the maximum number of images to include in Open Graph metadata.
	 *
	 * As of July 2014, Facebook seems to only let you select from the first 3 images.
	 *
	 * @param int $max_images The maximum number of images to include.
	 */
	$max_images = apply_filters( 'opengraph_max_images', OPENGRAPH_MAX_IMAGES );

	// Max images can't be negative or zero.
	return max( 1, (int) $max_images );
}
