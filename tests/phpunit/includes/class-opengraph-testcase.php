<?php
/**
 * Base test case for the OpenGraph plugin.
 *
 * @package opengraph
 */

/**
 * Base test case with helpers for the OpenGraph plugin.
 */
abstract class Opengraph_TestCase extends WP_UnitTestCase {

	/**
	 * Create an image attachment.
	 *
	 * @param int $parent_id The post to attach the image to.
	 *
	 * @return int The attachment ID.
	 */
	protected function create_image( $parent_id = 0 ) {
		return self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg', $parent_id );
	}

	/**
	 * Get the Open Graph metadata for a URL.
	 *
	 * @param string $url The URL to go to.
	 *
	 * @return array The metadata.
	 */
	protected function metadata_for( $url ) {
		$this->go_to( $url );

		return opengraph_metadata();
	}

	/**
	 * Get the `og:image` list of a post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array The image URLs.
	 */
	protected function images_for( $post_id ) {
		$metadata = $this->metadata_for( get_permalink( $post_id ) );

		return $metadata['og:image'];
	}

	/**
	 * Get the URL the plugin emits for an attachment.
	 *
	 * @param int $attachment_id The attachment ID.
	 *
	 * @return string The URL.
	 */
	protected function image_url( $attachment_id ) {
		return wp_get_attachment_image_url( $attachment_id, 'large' );
	}
}
