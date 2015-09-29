<?php
namespace nLingual;

/**
 * nLingual Backend Functionality
 *
 * @package nLingual
 *
 * @since 2.0.0
 */

class AJAX extends Functional {
	// =========================
	// ! Properties
	// =========================

	/**
	 * The name of the class.
	 *
	 * @since 2.0.0
	 *
	 * @access protected (static)
	 *
	 * @var string
	 */
	protected static $name;

	// =========================
	// ! Hook Registration
	// =========================

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public static function register_hooks() {
		// Translation creation
		static::add_action( 'wp_ajax_nl_new_translation', 'ajax_new_translation' );
	}

	// =========================
	// ! Translation Creation
	// =========================

	/**
	 * Create a clone of the requested post in the requested language.
	 *
	 * @since 2.0.0
	 *
	 * @uses Registry::languages() to validate the language requested.
	 * @uses Translator::clone_post() to create the cloned post.
	 */
	public static function ajax_new_translation() {
		// Fail if no post/lang id or title is passed
		if ( ! isset( $_REQUEST['post_id'] ) || ! isset( $_REQUEST['lang_id'] ) || ! isset( $_REQUEST['title'] ) ) {
			return;
		}

		// Fail if post does not exist
		$post = get_post( $_REQUEST['post_id'] );
		if ( ! $post ) {
			return;
		}

		// Fail if language does not exist
		$language = Registry::languages()->get( $_REQUEST['lang_id'] );
		if ( ! $language ) {
			return;
		}

		// Create the translated clone
		$translation = Translator::clone_post( $post, $language, $_REQUEST['title'], isset( $_REQUEST['custom_title'] ) && $_REQUEST['custom_title'] );

		// Fail if error creating translation
		if ( ! $translation ) {
			return;
		}

		// Return the details
		echo json_encode( array(
			'id'    => $translation->ID,
			'title' => $translation->post_title,
		) );
		wp_die();
	}
}

AJAX::init();