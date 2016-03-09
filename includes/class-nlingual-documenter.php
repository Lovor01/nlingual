<?php
/**
 * nLingual Documentation Utility
 *
 * @package nLingual
 * @subpackage Helpers
 *
 * @since 2.0.0
 */

namespace nLingual;

/**
 * The Documenter System
 *
 * Handles printing out the help screen tabs/sidebar for
 * documenting different parts of the admin interface.
 *
 * @internal Used by the Manager and Localizer.
 *
 * @since 2.0.0
 */

class Documenter extends Handler {
	/**
	 * A directory of all help tabs available.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	protected static $directory = array(
		'options' => array(
			'tabs' => array(
				'overview',
				'management',
				'requests',
			),
			'sidebar' => true,
		),
		'localizables' => array(
			'tabs' => array(
				'overview',
			),
			'sidebar' => true,
		),
		'synchronizer' => array(
			'tabs' => array(
				'overview',
				'cloning',
			),
			'sidebar' => true,
		),
		'languages' => array(
			'tabs' => array(
				'overview',
			),
			'sidebar' => false,
		),
		'post-translation' => array(
			'tabs' => array(
				'translation',
			),
		),
		'posts-translation' => array(
			'tabs' => array(
				'translation',
			),
		),
		'localizer' => array(
			'tabs' => array(
				'localize-this',
			),
		),
	);

	/**
	 * An index of screens registered for help tabs.
	 *
	 * @since 2.0.0
	 *
	 * @var array
	 */
	protected static $registered_screens = array();

	// =========================
	// ! Dynamic Properties
	// =========================
	/**
	 * Get a reference list for names of post fields.
	 *
	 * @since 2.0.0
	 *
	 * @return array The list of names, localized.
	 */
	public static function post_field_names() {
		return array(
			'post_author'    => _x( 'Author',          'post field', 'nlingual' ),
			'post_date'      => _x( 'Date',            'post field', 'nlingual' ),
			'post_status'    => _x( 'Status',          'post field', 'nlingual' ),
			'post_parent'    => _x( 'Parent',          'post field', 'nlingual' ),
			'menu_order'     => _x( 'Menu Order',      'post field', 'nlingual' ),
			'post_password'  => _x( 'Password',        'post field', 'nlingual' ),
			'comment_status' => _x( 'Comment Status',  'post field', 'nlingual' ),
			'ping_status'    => _x( 'Pingback Status', 'post field', 'nlingual' ),
		);
	}

	// =========================
	// ! Hook Registration
	// =========================

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public static function register_hooks() {
		// Don't do anything if not in the backend
		if ( ! is_backend() ) {
			return;
		}

		static::add_action( 'admin_head', 'setup_help_tabs', 10, 0 );
	}

	// =========================
	// ! Help Tab Registration
	// =========================

	/**
	 * Register a help tab for a screen.
	 *
	 * @since 2.0.0
	 *
	 * @uses Documenter::$registered_screens to store the screen and tab IDs.
	 *
	 * @param string $screen The screen ID to add the tab to.
	 * @param string $tab    The tab ID to add to the screen.
	 */
	public static function register_help_tab( $screen, $tab ) {
		static::$registered_screens[ $screen ] = $tab;
	}

	/**
	 * Register help tabs for multiple screens.
	 *
	 * @since 2.0.0
	 *
	 * @uses Documenter::register_help_tab() to register each screen/tab.
	 *
	 * @param string $screens An array of screen=>tab IDs to register.
	 */
	public static function register_help_tabs( $screens ) {
		foreach ( $screens as $screen => $tab ) {
			static::register_help_tab( $screen, $tab );
		}
	}

	// =========================
	// ! Help Tab Content
	// =========================

	/**
	 * Load the HTML for the specified help tab.
	 *
	 * @since 2.0.0
	 *
	 * @param string $tab     The ID of the tab to get.
	 * @param string $section Optional. The section the tab belongs to.
	 *
	 * @return string The HTML of the help tab.
	 */
	public static function get_tab_content( $tab, $section = null ) {
		// Sanitize JUST in case...
		$tab = sanitize_file_name( $tab );
		$section = sanitize_file_name( $section );

		// Build the path to the doc file
		$path = NL_PLUGIN_DIR . '/documentation';

		// If a section is specified, add to the path
		if ( ! is_null( $section ) ) {
			$path .= '/' . $section;
		}

		// Add the actual tab filename
		$path .= '/' . $tab . '.php';

		// Fail if the file does not exist
		if ( ! file_exists( $path ) ) {
			return null;
		}

		// Get the contents of the file
		ob_start();
		include( $path );
		$content = ob_get_clean();

		// Run it through wpautop and return it
		return wpautop( $content );
	}

	// =========================
	// ! Help Tab Output
	// =========================

	/**
	 * Setup the help tabs for the current screen.
	 *
	 * A specific tab set ID can be specified, otherwise, it will
	 * search for a tab set registered already for the screen.
	 *
	 * @since 2.0.0
	 *
	 * @uses Documenter::$registered_screens to get the tab set ID.
	 * @uses Documenter::$directory to retrieve the help tab settings.
	 * @uses Documenter::get_tab_content() to get the HTML for the tab.
	 *
	 * @param string $help_id Optional. The ID of the tabset to setup.
	 */
	public static function setup_help_tabs( $help_id = null ) {
		// Get the screen object
		$screen = get_current_screen();

		// If no help tab ID is passed, see if one is registered for the screen.
		if ( is_null( $help_id ) ) {
			// Abort if no help tab is registered for this screen
			if ( ! isset( static::$registered_screens[ $screen->id ] ) ) {
				return;
			}

			// Get the help tabset
			$help_id = static::$registered_screens[ $screen->id ];
		}

		// Fail if no matching help tab exists
		if ( ! isset( static::$directory[ $help_id ] ) ) {
			return;
		}

		// Get the help info for this page
		$help = static::$directory[ $help_id ];

		// Build the list of titles for each tab
		$tab_titles = array(
			'overview'      => __( 'Overview',                         'nlingual' ),
			'management'    => __( 'Translated Content Management',    'nlingual' ),
			'requests'      => __( 'Request and Redirection Handling', 'nlingual' ),
			'cloning'       => __( 'Cloning Rules',                    'nlingual' ),
			'translation'   => __( 'Languages & Translations',         'nlingual' ),
			'localize-this' => __( 'Localize This',                    'nlingual' ),
		);

		// Add each tab defined
		foreach ( $help['tabs'] as $tab ) {
			$content = static::get_tab_content( $tab, $help_id );

			// Only add if there's content
			if ( $content ) {
				$screen->add_help_tab( array(
					'id' => "nlingual-{$help_id}-{$tab}",
					'title' => $tab_titles[ $tab ],
					'content' => $content,
				) );
			}
		}

		// Add sidebar if enabled
		if ( $help['sidebar'] ) {
			$content = static::get_tab_content( 'sidebar', $help_id );

			// Only add if there's content
			if ( $content ) {
				$screen->set_help_sidebar( $content );
			}
		}
	}
}
