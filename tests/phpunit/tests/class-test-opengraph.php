<?php
/**
 * Test file for the OpenGraph plugin.
 *
 * @package opengraph
 */

/**
 * Test class for the OpenGraph metadata.
 */
class Test_Opengraph extends Opengraph_TestCase {

	/**
	 * Test the metadata of a singular post.
	 *
	 * @covers ::opengraph_metadata
	 * @covers ::opengraph_default_title
	 * @covers ::opengraph_default_type
	 * @covers ::opengraph_default_url
	 * @covers ::opengraph_default_description
	 * @covers ::opengraph_default_sitename
	 * @covers ::opengraph_default_locale
	 */
	public function test_singular_metadata() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Hello <b>World</b>',
				'post_excerpt' => 'A short excerpt.',
			)
		);

		$metadata = $this->metadata_for( get_permalink( $post_id ) );

		$this->assertSame( 'Hello World', $metadata['og:title'] );
		$this->assertSame( 'article', $metadata['og:type'] );
		$this->assertSame( get_permalink( $post_id ), $metadata['og:url'] );
		$this->assertSame( 'A short excerpt.', $metadata['og:description'] );
		$this->assertSame( get_bloginfo( 'name' ), $metadata['og:site_name'] );
		$this->assertSame( get_locale(), $metadata['og:locale'] );
	}

	/**
	 * Test the metadata of the front page.
	 *
	 * @covers ::opengraph_default_title
	 * @covers ::opengraph_default_type
	 * @covers ::opengraph_default_description
	 */
	public function test_front_page_metadata() {
		$metadata = $this->metadata_for( home_url( '/' ) );

		$this->assertSame( get_bloginfo( 'name' ), $metadata['og:title'] );
		$this->assertSame( 'website', $metadata['og:type'] );
		$this->assertSame( get_bloginfo( 'description' ), $metadata['og:description'] );
		$this->assertSame( array(), $metadata['og:image'] );
	}

	/**
	 * Test the metadata of a category archive.
	 *
	 * @covers ::opengraph_default_title
	 * @covers ::opengraph_default_description
	 */
	public function test_category_metadata() {
		$term_id = self::factory()->category->create(
			array(
				'name'        => 'News',
				'description' => 'All the news.',
			)
		);
		self::factory()->post->create( array( 'post_category' => array( $term_id ) ) );

		$metadata = $this->metadata_for( get_category_link( $term_id ) );

		$this->assertSame( 'News', $metadata['og:title'] );
		$this->assertSame( 'All the news.', $metadata['og:description'] );
	}

	/**
	 * Test the description is trimmed and stripped.
	 *
	 * @covers ::opengraph_default_description
	 * @covers ::opengraph_trim_text
	 */
	public function test_description_from_content() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<p>' . implode( ' ', array_fill( 0, 60, 'word' ) ) . '</p>[gallery]',
				'post_excerpt' => '',
			)
		);

		$metadata = $this->metadata_for( get_permalink( $post_id ) );

		$this->assertSame( implode( ' ', array_fill( 0, 55, 'word' ) ) . ' [...]', $metadata['og:description'] );
	}

	/**
	 * Test a password protected post exposes neither its content nor its images.
	 *
	 * @covers ::opengraph_default_description
	 * @covers ::opengraph_default_image
	 */
	public function test_password_protected_post() {
		$post_id = self::factory()->post->create(
			array(
				'post_content'  => 'Secret content',
				'post_password' => 'secret',
			)
		);
		set_post_thumbnail( $post_id, $this->create_image( $post_id ) );

		$metadata = $this->metadata_for( get_permalink( $post_id ) );

		$this->assertSame( 'This content is password protected.', $metadata['og:description'] );
		$this->assertSame( array(), $metadata['og:image'] );
	}

	/**
	 * Test the avatar is used on author pages.
	 *
	 * @covers ::opengraph_default_image
	 * @covers ::opengraph_default_type
	 * @covers ::opengraph_profile_metadata
	 */
	public function test_author_page() {
		$user_id = self::factory()->user->create(
			array(
				'first_name' => 'Jane',
				'last_name'  => 'Doe',
			)
		);
		self::factory()->post->create( array( 'post_author' => $user_id ) );

		$metadata = $this->metadata_for( get_author_posts_url( $user_id ) );

		$this->assertSame( 'profile', $metadata['og:type'] );
		$this->assertSame( array( get_avatar_url( $user_id, array( 'size' => 512 ) ) ), $metadata['og:image'] );
		$this->assertSame( 'Jane', $metadata['profile:first_name'] );
		$this->assertSame( 'Doe', $metadata['profile:last_name'] );
	}

	/**
	 * Test the Twitter card type depends on the images.
	 *
	 * @covers ::twitter_default_card
	 */
	public function test_twitter_card() {
		$metadata = $this->metadata_for( home_url( '/' ) );
		$this->assertSame( 'summary', $metadata['twitter:card'] );

		$post_id  = self::factory()->post->create();
		$metadata = $this->metadata_for( get_permalink( $post_id ) );
		$this->assertSame( 'summary', $metadata['twitter:card'] );

		set_post_thumbnail( $post_id, $this->create_image() );
		$metadata = $this->metadata_for( get_permalink( $post_id ) );
		$this->assertSame( 'summary_large_image', $metadata['twitter:card'] );
	}

	/**
	 * Test the property filters receive the metadata collected so far.
	 *
	 * @covers ::opengraph_metadata
	 */
	public function test_filters_receive_metadata() {
		$post_id  = self::factory()->post->create( array( 'post_title' => 'Hello' ) );
		$received = null;

		$callback = function ( $creator, $metadata ) use ( &$received ) {
			$received = $metadata;

			return $creator;
		};

		add_filter( 'twitter_creator', $callback, 10, 2 );
		$this->metadata_for( get_permalink( $post_id ) );
		remove_filter( 'twitter_creator', $callback, 10 );

		$this->assertSame( 'Hello', $received['og:title'] );
		$this->assertSame( 'summary', $received['twitter:card'] );
		$this->assertArrayNotHasKey( 'twitter:creator', $received );
	}

	/**
	 * Test the Twitter and Fediverse creators are normalized.
	 *
	 * @covers ::twitter_default_creator
	 * @covers ::fediverse_default_creator
	 * @covers ::opengraph_queried_author_meta
	 */
	public function test_creators() {
		$user_id = self::factory()->user->create();
		$post_id = self::factory()->post->create( array( 'post_author' => $user_id ) );

		update_user_meta( $user_id, 'twitter', 'http://twitter.com/#!/jane' );
		update_user_meta( $user_id, 'fediverse', '@jane@example.org' );

		$metadata = $this->metadata_for( get_permalink( $post_id ) );
		$this->assertSame( '@jane', $metadata['twitter:creator'] );
		$this->assertSame( 'jane@example.org', $metadata['fediverse:creator'] );

		update_user_meta( $user_id, 'twitter', 'jane' );
		update_user_meta( $user_id, 'fediverse', 'acct:jane@example.org' );

		$metadata = $this->metadata_for( get_permalink( $post_id ) );
		$this->assertSame( '@jane', $metadata['twitter:creator'] );
		$this->assertSame( 'jane@example.org', $metadata['fediverse:creator'] );

		$metadata = $this->metadata_for( home_url( '/' ) );
		$this->assertSame( '', $metadata['twitter:creator'] );
		$this->assertSame( array(), $metadata['fediverse:creator'] );
	}

	/**
	 * Test the article metadata.
	 *
	 * @covers ::opengraph_article_metadata
	 */
	public function test_article_metadata() {
		$post_id = self::factory()->post->create(
			array(
				'tags_input' => array( 'foo', 'bar' ),
			)
		);

		$metadata = $this->metadata_for( get_permalink( $post_id ) );

		$this->assertEqualSets( array( 'foo', 'bar' ), $metadata['article:tag'] );
		$this->assertSame( array( 'Uncategorized' ), $metadata['article:section'] );
		$this->assertSame( get_the_time( 'c', $post_id ), $metadata['article:published_time'] );
		$this->assertSame( array( get_author_posts_url( get_post( $post_id )->post_author ) ), $metadata['article:author'] );
	}

	/**
	 * Test the attached audio and video files.
	 *
	 * @covers ::opengraph_default_audio
	 * @covers ::opengraph_default_video
	 * @covers ::opengraph_attached_media_urls
	 */
	public function test_audio_and_video() {
		$post_id  = self::factory()->post->create();
		$audio_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'song.mp3',
				'post_parent'    => $post_id,
				'post_mime_type' => 'audio/mpeg',
			)
		);
		$video_id = self::factory()->attachment->create_object(
			array(
				'file'           => 'clip.mp4',
				'post_parent'    => $post_id,
				'post_mime_type' => 'video/mp4',
			)
		);

		$metadata = $this->metadata_for( get_permalink( $post_id ) );
		$this->assertSame( array( wp_get_attachment_url( $audio_id ) ), $metadata['og:audio'] );
		$this->assertSame( array( wp_get_attachment_url( $video_id ) ), $metadata['og:video'] );

		// Not on archives, even if the queried object ID matches a post ID.
		$metadata = $this->metadata_for( home_url( '/' ) );
		$this->assertSame( array(), $metadata['og:audio'] );
		$this->assertSame( array(), $metadata['og:video'] );
	}

	/**
	 * Test the meta tags output.
	 *
	 * @covers ::opengraph_meta_tags
	 */
	public function test_meta_tags() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'Hello' ) );
		$this->go_to( get_permalink( $post_id ) );

		$output = get_echo( 'opengraph_meta_tags' );

		$this->assertStringContainsString( '<meta property="og:title" name="og:title" content="Hello" />', $output );
		$this->assertStringContainsString( '<meta property="twitter:card" name="twitter:card" content="summary" />', $output );
		$this->assertStringNotContainsString( 'og:image', $output );
		$this->assertStringNotContainsString( 'og:determiner', $output );
	}

	/**
	 * Test the RDFa prefix is added to the language attributes.
	 *
	 * @covers ::opengraph_add_prefix
	 * @covers ::opengraph_additional_prefixes
	 */
	public function test_add_prefix() {
		$this->assertSame( 'lang="en" prefix="og: http://ogp.me/ns#"', opengraph_add_prefix( 'lang="en"' ) );
		$this->assertSame( 'prefix="og: http://ogp.me/ns# foo: bar"', opengraph_add_prefix( 'prefix="foo: bar"' ) );

		$this->go_to( get_permalink( self::factory()->post->create() ) );
		$this->assertSame( ' prefix="og: http://ogp.me/ns# article: http://ogp.me/ns/article#"', opengraph_add_prefix( '' ) );
	}
}
