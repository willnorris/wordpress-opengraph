<?php
/**
 * Test file for the OpenGraph image handling.
 *
 * @package opengraph
 */

/**
 * Test class for the `og:image` property.
 */
class Test_Opengraph_Images extends Opengraph_TestCase {

	/**
	 * Create a post with the given content.
	 *
	 * @param string $content The post content.
	 *
	 * @return int The post ID.
	 */
	private function create_post( $content = '' ) {
		return self::factory()->post->create( array( 'post_content' => $content ) );
	}

	/**
	 * Image block markup.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string The markup.
	 */
	private function image_block( $attachment_id ) {
		return sprintf(
			'<!-- wp:image {"id":%1$d,"sizeSlug":"large","linkDestination":"none"} -->' .
			'<figure class="wp-block-image size-large"><img src="%2$s" alt="" class="wp-image-%1$d"/></figure>' .
			'<!-- /wp:image -->',
			$attachment_id,
			wp_get_attachment_url( $attachment_id )
		);
	}

	/**
	 * Test the post thumbnail comes first, followed by content and attached images.
	 *
	 * @covers ::opengraph_default_image
	 * @covers ::opengraph_image_ids
	 * @covers ::opengraph_thumbnail_image_ids
	 * @covers ::opengraph_content_image_ids
	 * @covers ::opengraph_attached_image_ids
	 */
	public function test_image_order() {
		$post_id      = $this->create_post();
		$thumbnail_id = $this->create_image();
		$content_id   = $this->create_image();
		$attached_id  = $this->create_image( $post_id );

		set_post_thumbnail( $post_id, $thumbnail_id );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $this->image_block( $content_id ),
			)
		);

		$this->assertSame(
			array(
				$this->image_url( $thumbnail_id ),
				$this->image_url( $content_id ),
				$this->image_url( $attached_id ),
			),
			$this->images_for( $post_id )
		);
	}

	/**
	 * Test a post without any image and without site branding has no image.
	 *
	 * @covers ::opengraph_default_image
	 * @covers ::opengraph_fallback_image
	 */
	public function test_no_images() {
		$this->assertSame( array(), $this->images_for( $this->create_post( 'Just text.' ) ) );
	}

	/**
	 * Test the image block.
	 *
	 * @covers ::opengraph_content_image_ids
	 * @covers ::opengraph_image_tag_to_id
	 */
	public function test_image_block() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post( $this->image_block( $image_id ) );

		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $post_id ) );
	}

	/**
	 * Test the cover block.
	 *
	 * @covers ::opengraph_content_image_ids
	 * @covers ::opengraph_image_tag_to_id
	 */
	public function test_cover_block() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post(
			sprintf(
				'<!-- wp:cover {"url":"%2$s","id":%1$d,"dimRatio":50} -->' .
				'<div class="wp-block-cover"><img class="wp-block-cover__image-background wp-image-%1$d" alt="" src="%2$s" data-object-fit="cover"/>' .
				'<span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span>' .
				'<div class="wp-block-cover__inner-container"><!-- wp:paragraph --><p>Title</p><!-- /wp:paragraph --></div></div>' .
				'<!-- /wp:cover -->',
				$image_id,
				wp_get_attachment_url( $image_id )
			)
		);

		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $post_id ) );
	}

	/**
	 * Test the gallery block, which nests image blocks.
	 *
	 * @covers ::opengraph_content_image_ids
	 */
	public function test_gallery_block() {
		$first_id  = $this->create_image();
		$second_id = $this->create_image();
		$post_id   = $this->create_post(
			'<!-- wp:gallery {"linkTo":"none"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped">' .
			$this->image_block( $first_id ) .
			$this->image_block( $second_id ) .
			'</figure><!-- /wp:gallery -->'
		);

		$this->assertSame(
			array( $this->image_url( $first_id ), $this->image_url( $second_id ) ),
			$this->images_for( $post_id )
		);
	}

	/**
	 * Test the media & text block.
	 *
	 * @covers ::opengraph_content_image_ids
	 */
	public function test_media_text_block() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post(
			sprintf(
				'<!-- wp:media-text {"mediaId":%1$d,"mediaType":"image"} -->' .
				'<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="%2$s" alt="" class="wp-image-%1$d size-full"/></figure>' .
				'<div class="wp-block-media-text__content"><!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph --></div></div>' .
				'<!-- /wp:media-text -->',
				$image_id,
				wp_get_attachment_url( $image_id )
			)
		);

		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $post_id ) );
	}

	/**
	 * Test images nested in group and columns blocks.
	 *
	 * @covers ::opengraph_content_image_ids
	 */
	public function test_nested_blocks() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post(
			'<!-- wp:group --><div class="wp-block-group">' .
			'<!-- wp:columns --><div class="wp-block-columns">' .
			'<!-- wp:column --><div class="wp-block-column">' .
			$this->image_block( $image_id ) .
			'</div><!-- /wp:column -->' .
			'</div><!-- /wp:columns -->' .
			'</div><!-- /wp:group -->'
		);

		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $post_id ) );
	}

	/**
	 * Test a cover block using the featured image has no `<img>` in the content,
	 * so the image comes from the thumbnail source.
	 *
	 * @covers ::opengraph_thumbnail_image_ids
	 */
	public function test_cover_block_with_featured_image() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post(
			'<!-- wp:cover {"useFeaturedImage":true,"dimRatio":50} -->' .
			'<div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span>' .
			'<div class="wp-block-cover__inner-container"></div></div>' .
			'<!-- /wp:cover -->'
		);
		set_post_thumbnail( $post_id, $image_id );

		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $post_id ) );
	}

	/**
	 * Test classic editor content.
	 *
	 * @covers ::opengraph_content_image_ids
	 * @covers ::opengraph_image_tag_to_id
	 */
	public function test_classic_content() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post(
			sprintf(
				'<p>Some text</p><p><img class="alignnone size-medium wp-image-%d" src="%s" alt="" width="300" height="225" /></p>',
				$image_id,
				wp_get_attachment_image_url( $image_id, 'medium' )
			)
		);

		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $post_id ) );
	}

	/**
	 * Test content images are found without the loop globals.
	 *
	 * Plugins like The Events Calendar render their pages without setting up
	 * `$pages`, which made the old `get_the_content()` based collector fatal.
	 *
	 * @see https://github.com/pfefferle/wordpress-opengraph/pull/39
	 *
	 * @covers ::opengraph_content_image_ids
	 */
	public function test_content_images_without_loop_globals() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post( $this->image_block( $image_id ) );

		$this->go_to( get_permalink( $post_id ) );
		$GLOBALS['pages'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['post']  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$metadata = opengraph_metadata();

		$this->assertSame( array( $this->image_url( $image_id ) ), $metadata['og:image'] );

		// A password protected post stays protected without the globals, too.
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'secret',
			)
		);
		$this->go_to( get_permalink( $post_id ) );
		$GLOBALS['pages'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['post']  = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$metadata = opengraph_metadata();

		$this->assertSame( array(), $metadata['og:image'] );
	}

	/**
	 * Test content images without a `wp-image-` class are resolved through their URL.
	 *
	 * @covers ::opengraph_image_tag_to_id
	 * @covers ::opengraph_attachment_url_to_id
	 */
	public function test_content_image_by_url() {
		$image_id = $this->create_image();
		$url      = wp_get_attachment_url( $image_id );
		$sized    = preg_replace( '/(\.[a-z]+)$/', '-150x150$1', $url );
		$post_id  = $this->create_post( sprintf( '<img src="%s?v=1"/>', $sized ) );

		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $post_id ) );
	}

	/**
	 * Test images that cannot be resolved are skipped.
	 *
	 * @covers ::opengraph_image_tag_to_id
	 * @covers ::opengraph_attachment_url_to_id
	 * @covers ::opengraph_default_image
	 */
	public function test_unresolvable_images() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post(
			// External image.
			'<img src="https://example.org/external.jpg"/>' .
			// Deleted attachment.
			'<img class="wp-image-999999" src="' . wp_get_upload_dir()['baseurl'] . '/deleted.jpg"/>' .
			// Class that only looks like the editor class.
			'<img class="not-wp-image-' . $image_id . ' wp-image-' . $image_id . '0"/>' .
			// Uploads URL that is not an attachment.
			'<img src="' . wp_get_upload_dir()['baseurl'] . '/nope.jpg"/>' .
			// No src at all.
			'<img alt="nothing"/>' .
			$this->image_block( $image_id )
		);

		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $post_id ) );
	}

	/**
	 * Test the maximum number of images and deduplication.
	 *
	 * @covers ::opengraph_image_ids
	 * @covers ::opengraph_max_images
	 */
	public function test_max_images() {
		$post_id  = $this->create_post();
		$image_id = $this->create_image();
		$content  = $this->image_block( $image_id ); // Same as the thumbnail, should not count.

		for ( $i = 0; $i < 5; $i++ ) {
			$content .= $this->image_block( $this->create_image() );
		}

		set_post_thumbnail( $post_id, $image_id );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);

		$images = $this->images_for( $post_id );
		$this->assertCount( 3, $images );
		$this->assertSame( $this->image_url( $image_id ), $images[0] );
		$this->assertSame( $images, array_unique( $images ) );

		$five = function () {
			return 5;
		};
		add_filter( 'opengraph_max_images', $five );
		$this->assertCount( 5, $this->images_for( $post_id ) );
		remove_filter( 'opengraph_max_images', $five );

		// Zero or less falls back to one image.
		add_filter( 'opengraph_max_images', '__return_zero' );
		$this->assertCount( 1, $this->images_for( $post_id ) );
		remove_filter( 'opengraph_max_images', '__return_zero' );
	}

	/**
	 * Test unusable IDs do not use up a slot.
	 *
	 * @covers ::opengraph_image_ids
	 */
	public function test_unusable_ids_do_not_count() {
		$post_id = $this->create_post();
		$file_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'document.pdf',
				'post_mime_type' => 'application/pdf',
			)
		);
		$content = '<img class="wp-image-999999"/><img class="wp-image-' . $file_id . '"/>';
		$ids     = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$ids[]    = $this->create_image();
			$content .= $this->image_block( end( $ids ) );
		}
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);

		$this->assertSame( array_map( array( $this, 'image_url' ), $ids ), $this->images_for( $post_id ) );
	}

	/**
	 * Test the plugin never emits more than the maximum, even with images
	 * added by earlier filter callbacks.
	 *
	 * @covers ::opengraph_default_image
	 */
	public function test_default_image_is_capped() {
		$post_id = $this->create_post( $this->image_block( $this->create_image() ) );

		$prepend = function ( $image ) {
			return array_merge( array( 'https://example.org/1.jpg', 'https://example.org/2.jpg', 'https://example.org/3.jpg', 'https://example.org/4.jpg' ), $image );
		};

		add_filter( 'opengraph_image', $prepend, 1 );
		$images = $this->images_for( $post_id );
		remove_filter( 'opengraph_image', $prepend, 1 );

		$this->assertSame( array( 'https://example.org/1.jpg', 'https://example.org/2.jpg', 'https://example.org/3.jpg' ), $images );
	}

	/**
	 * Test images added by an earlier filter callback count toward the limit.
	 *
	 * @covers ::opengraph_default_image
	 */
	public function test_limit_includes_earlier_images() {
		$post_id = $this->create_post();
		$content = '';

		for ( $i = 0; $i < 3; $i++ ) {
			$content .= $this->image_block( $this->create_image() );
		}
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);

		$prepend = function ( $image ) {
			return array_merge( array( 'https://example.org/one.jpg', 'https://example.org/two.jpg' ), $image );
		};

		add_filter( 'opengraph_image', $prepend, 1 );
		$images = $this->images_for( $post_id );
		remove_filter( 'opengraph_image', $prepend, 1 );

		$this->assertCount( 3, $images );
		$this->assertSame( 'https://example.org/one.jpg', $images[0] );
	}

	/**
	 * Test the expensive sources do not run once the list is full.
	 *
	 * @covers ::opengraph_image_ids
	 */
	public function test_sources_stop_when_full() {
		$post_id = $this->create_post();
		$content = '';

		for ( $i = 0; $i < 3; $i++ ) {
			$content .= $this->image_block( $this->create_image() );
		}
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);

		$called  = false;
		$sources = function ( $sources ) use ( &$called ) {
			$sources[] = function () use ( &$called ) {
				$called = true;

				return array();
			};

			return $sources;
		};

		add_filter( 'opengraph_image_sources', $sources );
		$this->assertCount( 3, $this->images_for( $post_id ) );
		remove_filter( 'opengraph_image_sources', $sources );

		$this->assertFalse( $called );
	}

	/**
	 * Test the image sources can be replaced.
	 *
	 * @covers ::opengraph_image_ids
	 */
	public function test_image_sources_filter() {
		$post_id  = $this->create_post();
		$image_id = $this->create_image();

		set_post_thumbnail( $post_id, $this->create_image() );

		$sources = function () use ( $image_id ) {
			return array(
				function () use ( $image_id ) {
					return array( $image_id );
				},
			);
		};

		add_filter( 'opengraph_image_sources', $sources );
		$images = $this->images_for( $post_id );
		remove_filter( 'opengraph_image_sources', $sources );

		$this->assertSame( array( $this->image_url( $image_id ) ), $images );
	}

	/**
	 * Test attached images are ordered by menu order.
	 *
	 * @covers ::opengraph_attached_image_ids
	 */
	public function test_attached_images_order() {
		$post_id   = $this->create_post();
		$first_id  = $this->create_image( $post_id );
		$second_id = $this->create_image( $post_id );

		wp_update_post(
			array(
				'ID'         => $first_id,
				'menu_order' => 2,
			)
		);
		wp_update_post(
			array(
				'ID'         => $second_id,
				'menu_order' => 1,
			)
		);

		$this->assertSame(
			array( $this->image_url( $second_id ), $this->image_url( $first_id ) ),
			$this->images_for( $post_id )
		);
	}

	/**
	 * Test the image of an attachment page.
	 *
	 * @covers ::opengraph_image_ids
	 */
	public function test_attachment_page() {
		$image_id = $this->create_image();
		$this->assertSame( array( $this->image_url( $image_id ) ), $this->images_for( $image_id ) );

		$file_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'document.pdf',
				'post_mime_type' => 'application/pdf',
			)
		);
		$this->assertSame( array(), $this->images_for( $file_id ) );
	}

	/**
	 * Test the site icon is used when a post has no image.
	 *
	 * @covers ::opengraph_fallback_image
	 */
	public function test_fallback_site_icon() {
		$post_id = $this->create_post( 'Just text.' );

		update_option( 'site_icon', $this->create_image() );
		$expected = get_site_icon_url( 512 );
		$images   = $this->images_for( $post_id );
		delete_option( 'site_icon' );

		$this->assertNotEmpty( $expected );
		$this->assertSame( array( $expected ), $images );
	}

	/**
	 * Test the custom logo is used when there is no site icon.
	 *
	 * @covers ::opengraph_fallback_image
	 */
	public function test_fallback_custom_logo() {
		$post_id = $this->create_post( 'Just text.' );
		$logo_id = $this->create_image();

		set_theme_mod( 'custom_logo', $logo_id );
		$images = $this->images_for( $post_id );
		remove_theme_mod( 'custom_logo' );

		$this->assertSame( array( $this->image_url( $logo_id ) ), $images );
	}

	/**
	 * Test the fallback is not used when there are images.
	 *
	 * @covers ::opengraph_fallback_image
	 */
	public function test_fallback_not_used_with_images() {
		$image_id = $this->create_image();
		$post_id  = $this->create_post( $this->image_block( $image_id ) );

		update_option( 'site_icon', $this->create_image() );
		$images = $this->images_for( $post_id );
		delete_option( 'site_icon' );

		$this->assertSame( array( $this->image_url( $image_id ) ), $images );
	}
}
