<?php
/**
 * nLingual Manager Funtionality
 *
 * @package nLingual
 * @subpackage Handlers
 *
 * @since 2.0.0
 */

namespace nLingual;

/**
 * The Management System
 *
 * Hooks into the backend to add the interfaces for
 * managing the configuration of nLingual.
 *
 * @package nLingual
 * @subpackage Handlers
 *
 * @internal Used by the System.
 *
 * @since 2.0.0
 */

class Manager extends Handler {
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

		// Settings & Pages
		static::add_action( 'admin_init', 'register_settings', 10, 0 );
		static::add_action( 'admin_menu', 'add_menu_pages', 10, 0 );

		// Custom settings saving
		static::add_action( 'admin_init', 'save_languages', 10, 0 );
	}

	// =========================
	// ! Utilities
	// =========================

	/**
	 * Sanitize the sync rules.
	 *
	 * @since 2.0.0
	 *
	 * @param array $rules The rules to be sanitized.
	 *
	 * @return array The sanitized rules.
	 */
	public static function sanitize_rules( $rules ) {
		// Loop through each object type
		foreach ( $rules as $object_type => $type_rules ) {
			// Loop through each object subtype
			foreach ( $type_rules as $subtype => $ruleset ) {
				// Loop through each rule set
				foreach ( $ruleset as $rule => $values ) {
					// If values is a string...
					if ( is_string( $values ) ) {
						// Split it by line
						$values = (array) preg_split( '/[\n\r]+/', trim( $values ), 0, PREG_SPLIT_NO_EMPTY );
						// Convert to TRUE if it contains * wildcard
						if ( in_array( '*', $values ) ) {
							$values = true;
						}

						$rules[ $object_type ][ $subtype ][ $rule ] = $values;
					}
				}
			}
		}

		return $rules;
	}

	// =========================
	// ! Settings Page Setup
	// =========================

	/**
	 * Register admin pages.
	 *
	 * @since 2.0.0
	 *
	 * @uses Manager::settings_page() for general options page output.
	 * @uses Manager::settings_page_languages() for language manager output.
	 * @uses Manager::settings_page_strings() for strings editor output.
	 * @uses Documenter::register_help_tabs() to register help tabs for all screens.
	 */
	public static function add_menu_pages() {
		// Main Options page
		$options_page_hook = add_utility_page(
			__( 'Translation Options' ), // page title
			_x( 'Translation', 'menu title' ), // menu title
			'manage_options', // capability
			'nlingual-options', // slug
			array( get_called_class(), 'settings_page' ), // callback
			'dashicons-translation' // icon
		);

		// Languages manager
		$languages_page_hook = add_submenu_page(
			'nlingual-options', // parent
			__( 'Manage Languages' ), // page title
			_x( 'Languages', 'menu title' ), // menu title
			'manage_options', // capability
			'nlingual-languages', // slug
			array( get_called_class(), 'settings_page_languages' ) // callback
		);

		// Localizable Objects manager
		$localizables_page_hook = add_submenu_page(
			'nlingual-options', // parent
			__( 'Manage Localizable Objects' ), // page title
			__( 'Localizables' ), // menu title
			'manage_options', // capability
			'nlingual-localizables', // slug
			array( get_called_class(), 'settings_page' ) // callback
		);

		// Sync/Clone Rules manager
		$sync_page_hook = add_submenu_page(
			'nlingual-options', // parent
			__( 'Post Synchronization' ), // page title
			__( 'Sync Options' ), // menu title
			'manage_options', // capability
			'nlingual-sync', // slug
			array( get_called_class(), 'settings_page' ) // callback
		);

		// Setup the help tabs for each page
		Documenter::register_help_tabs( array(
			$options_page_hook	    => 'options',
			$localizables_page_hook => 'localizables',
			$sync_page_hook		    => 'sync',
			$languages_page_hook    => 'languages',
		) );
	}

	// =========================
	// ! Settings Registration
	// =========================

	/**
	 * Register the settings/fields for the admin pages.
	 *
	 * @since 2.0.0
	 *
	 * @uses Settings::register() to register the settings.
	 * @uses Manager::setup_options_fields() to add fields to the main options page.
	 * @uses Manager::setup_localizables_fields() to add fields to the localizlables page.
	 * @uses Manager::setup_sync_fields() to add fields to the sync options page.
	 */
	public static function register_settings() {
		// Translation Options
		Settings::register( array(
			'default_language'       => 'intval',
			'show_all_languages'     => 'intval',
			'localize_date'          => 'intval',
			'skip_default_l10n'      => 'intval',
			'patch_wp_locale'        => 'intval',
			'backwards_compatible'   => 'intval',
			'query_var'              => null,
			'url_rewrite_method'     => null,
			'post_language_override' => 'intval',
			'redirection_permanent'  => 'intval',
		), 'options' );

		static::setup_options_fields();

		// Localizables Options
		Settings::register( array(
			'post_types'   => null,
			'taxonomies'   => null,
			'localizables' => null,
		), 'localizables' );

		static::setup_localizables_fields();

		// Sync Options
		Settings::register( array(
			'sync_rules'  => array( get_called_class(), 'sanitize_rules' ),
			'clone_rules' => array( get_called_class(), 'sanitize_rules' ),
		), 'sync' );

		static::setup_sync_fields();
	}

	/**
	 * Save languages from the manager.
	 *
	 * @since 2.0.0
	 *
	 * @global wpdb $wpdb The database abstraction class instance.
	 */
	public static function save_languages() {
		global $wpdb;

		// Abort if not saving for the language manager page
		if ( ! isset( $_POST['option_page'] ) || $_POST['option_page'] != 'nlingual-languages' ) {
			return;
		}

		// Fail if nonce does
		check_admin_referer( 'nlingual-languages-options' );
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'nlingual-languages-options' ) ) {
			cheatin();
		}

		// Get the languages
		$languages = $_POST['nlingual_languages'];

		// The fields to check
		$fields = array(
			'system_name' => '%s',
			'native_name' => '%s',
			'short_name'  => '%s',
			'locale_name' => '%s',
			'iso_code'    => '%s',
			'slug'        => '%s',
		);
		$formats = array_values( $fields );

		// Loop through languages and update/insert
		$i = 0;
		foreach ( $languages as $id => $language ) {
			// If delete option is present, go straight to deleting it
			if ( isset( $language['delete'] ) && $language['delete'] ) {
				if ( $id > 0 ) {
					$wpdb->delete( $wpdb->nl_languages, array( 'language_id' => $id ), array( '%d' ) );
				}
				continue;
			}

			// Ensure all fields are set
			foreach ( $fields as $field => $format ) {
				if ( ! isset( $language[ $field ] ) || empty( $language[ $field ] ) ) {
					add_settings_error(
						'nlingual-languages',
						'nl_language',
						__( 'One or more languages were incomplete and were not saved.' ),
						'error'
					);
					break;
				}

				$entry[ $field ] = $language[ $field ];
			}

			// Sanitize the slug
			$language['slug'] = sanitize_title( $language['slug'] );

			// Default active to 0
			$formats[] = '%d';
			$entry['active'] = 0;
			if ( isset( $language['active'] ) ) {
				$entry['active'] = $language['active'];
			}

			// Default text direction to ltr if not set or otherwise not ltr
			$formats[] = '%s';
			$entry['direction'] = 'ltr';
			if ( isset( $language['direction'] ) && $language['direction'] != 'ltr' ) {
				$entry['direction'] = 'rtl';
			}

			// Add list_order
			$formats[] = '%d';
			$entry['list_order'] = $i;
			$i++;

			if ( $id > 0 ) {
				// Assume existing language; update
				$wpdb->update( $wpdb->nl_languages, $entry, array( 'language_id' => $id ), $formats, array( '%d' ) );
			} else {
				// Assume new language; insert
				$wpdb->insert( $wpdb->nl_languages, $entry, $formats );
			}
		}

		// Check for setting errors; add an "updated" message if none are found
		if ( ! count( get_settings_errors() ) ) {
			add_settings_error( 'nlingual-languages', 'settings_updated', __( 'Languages saved.' ), 'updated' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30);

		// Return to settings page
		$redirect = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
		wp_redirect( $redirect );
		exit;
	}

	// =========================
	// ! Settings Fields Setup
	// =========================

	/**
	 * Fields for the Translations page.
	 *
	 * @since 2.0.0
	 *
	 * @uses Registry::language() to get the list of registered languages.
	 * @uses Languages::export() to export the language list to an array.
	 * @uses Settings::add_fields() to define the controls on the page.
	 */
	protected static function setup_options_fields() {
		// Build the previews for the URLs
		$domain = parse_url( home_url(), PHP_URL_HOST );

		// The default language URL samples
		$url_format = '<span class="nl-preview nl-url-preview nl-redirect-%s" data-included="%s" data-excluded="%s"></span>';
		$url_previews =
			sprintf( $url_format, 'get', "$domain/sample-page/?%v=%l", "$domain/sample-page/" ).
			sprintf( $url_format, 'path', "$domain/%l/sample-page/", "$domain/sample-page/" ).
			sprintf( $url_format, 'domain', "%l.$domain/sample-page/", "$domain/sample-page/" );

		// The redirection method previews
		$redirect_format = '<span class="nl-preview nl-redirect-preview nl-redirect-%s" data-format="%s"></span>';
		$redirect_previews =
			sprintf( $redirect_format, 'get', "$domain/?%v=%l" ).
			sprintf( $redirect_format, 'path', "$domain/%l/" ).
			sprintf( $redirect_format, 'domain', "%l.$domain" );

		// The post language override URL samples
		$override_format = '<span class="nl-preview nl-override-preview nl-redirect-%s" data-on="%s" data-off="%s"></span>';
		$override_previews =
			sprintf( $override_format, 'get',
				"$domain/french-page/?%v=en > $domain/french-page/?%v=fr",
				"$domain/french-page/?%v=en > $domain/english-page/?%v=en" ).
			sprintf( $override_format, 'path',
				"$domain/en/french-page/ > $domain/fr/french-page/",
				"$domain/en/french-page/ > $domain/en/english-page/" ).
			sprintf( $override_format, 'domain',
				"en.$domain/french-page/ > fr.$domain/french-page/" ,
				"en.$domain/french-page/ > en.$domain/english-page/" );

		// The general setting fields
		add_settings_section( 'default', null, null, 'nlingual-options' );
		Settings::add_fields( array(
			'default_language' => array(
				'title' => __( 'Default Language' ),
				'help'  => null,
				'type'  => 'select',
				'data'  => Registry::languages()->pluck( 'system_name' ),
			),
			'show_all_languages' => array(
				'title' => __( 'Show All Languages?' ),
				'help'  => __( 'Should objects of all languages be listed by default in the admin?' ),
				'type'  => 'checkbox',
			),
			'localize_date' => array(
				'title' => __( 'Localize date format?' ),
				'help'  => __( 'Run localization on the date format defined under General Settings. Useful if any languages you use require custom date formats.' ),
				'type'  => 'checkbox',
			),
			'patch_wp_locale' => array(
				'title' => __( 'Patch <code>WP_Locale</code>?' ),
				'help'  => __( 'Replaced the Date/Time localization system with one using your Theme’s translation files instead (front-end only).' ),
				'type'  => 'checkbox',
			),
		), 'options' );

		// The request/redirection setting fields
		add_settings_section( 'redirection', __( 'Request and Redirection Handling' ), null, 'nlingual-options' );
		Settings::add_fields( array(
			'query_var' => array(
				'title' => __( 'Query Variable' ),
				'help'  => __( 'The variable name to use for when requesting/filtering by language (recommended: "language")' ),
				'type'  => 'input',
			),
			'url_rewrite_method' => array(
				'title' => __( 'URL Scheme' ),
				'help'  => __( 'What style should be used for the translated URLs?' ) .
					'<br /> <span class="nl-previews">' . _f( 'Preview: %s', $redirect_previews ) . '</span>',
				'type'  => 'radiolist',
				'data'  => array(
					'get'    => __( 'HTTP query' ),
					'path'   => __( 'Path prefix' ),
					'domain' => __( 'Subdomain' ),
				),
			),
			'skip_default_l10n' => array(
				'title' => __( 'Skip Localization for Default Language?' ),
				'help'  => __( 'URLs for the default language will be unmodified.' ) .
					'<br /> <span class="nl-previews">' . _f( 'Preview: %s', $url_previews ) . '</span>',
				'type'  => 'checkbox',
			),
			'post_language_override' => array(
				'title' => __( 'Post Language Override' ),
				'help'  => __( 'Should the language of the requested post take precedence in the event of a language mismatch?' ) .
					'<br /> <span class="nl-previews">' . _f( 'Example: %s', $override_previews ) . '</span>',
				'type'  => 'checkbox',
			),
			'redirection_permanent' => array(
				'title' => __( 'Permanently Redirect URLs?' ),
				'help'  => __( 'Use "permanent" (HTTP 301) instead of "temporary" (HTTP 302) redirects?' ),
				'type'  => 'checkbox',
			),
		), 'options', 'redirection' );

		// If this is an upgraded install, offer the backwards compatability option
		if ( get_option( 'nlingual_upgraded' ) ) {
			Settings::add_fields( array(
				'backwards_compatible' => array(
					'title' => __( 'Backwards Compatability' ),
					'help'  => __( 'Include support for old template functions, and features like language splitting.' ),
					'type'  => 'checkbox',
				),
			), 'options' );
		}
	}

	/**
	 * Fields for the Localizables page.
	 *
	 * @since 2.0.0
	 *
	 * @uses Settings::add_fields() to define the controls on the page.
	 */
	protected static function setup_localizables_fields() {
		add_settings_section( 'default', null, null, 'nlingual-localizables' );

		/**
		 * Post Types
		 */

		// Build the list
		$post_types = array();
		foreach ( get_post_types( array(
			'show_ui' => true,
		), 'objects' ) as $post_type ) {
			// Automatically skip attachments
			if ( $post_type->name == 'attachment' ) {
				continue;
			}
			$post_types[ $post_type->name ] = $post_type->labels->name;
		}

		// Add if we have any
		if ( $post_types ) {
			$fields['post_types'] = array(
				'title' => __( 'Post Types' ),
				'help'  => __( 'What post types should support language and translations?' ),
				'type'  => 'checklist',
				'data'  => $post_types,
			);
		}

		/**
		 * Taxonomies
		 */

		// Build the list
		$taxonomies = array();
		foreach ( get_taxonomies( array(
			'show_ui' => true,
		), 'objects' ) as $taxonomy ) {
			// Automatically skip attachments
			if ( $taxonomy->name == 'attachment' ) {
				continue;
			}
			$taxonomies[ $taxonomy->name ] = $taxonomy->labels->name;
		}

		// Add if we have any
		if ( $taxonomies ) {
			$fields['taxonomies'] = array(
				'title' => __( 'Taxonomies' ),
				'help'  => __( 'What taxonomies should support name and description localization?' ),
				'type'  => 'checklist',
				'data'  => $taxonomies,
			);
		}

		/**
		 * Nav Menus
		 */

		// Get the original list
		$nav_locations = wp_cache_get( '_wp_registered_nav_menus', 'nlingual:vars' );

		// Add if we have any
		if ( $nav_locations ) {
			$fields['localizables[nav_menu_locations]'] = array(
				'title' => __( 'Menu Locations' ),
				'help'  => __( 'Should any navigation menus have versions for each language?' ),
				'type'  => 'checklist',
				'data'  => $nav_locations,
			);
		}

		/**
		 * Sidebars
		 */

		// Get the original list and convert to appropriate format
		$sidebars = wp_cache_get( 'wp_registered_sidebars', 'nlingual:vars' );
		foreach ( $sidebars as &$sidebar ) {
			$sidebar = $sidebar['name'];
		}

		// Add if we have any
		if ( $sidebars ) {
			$fields['localizables[sidebar_locations]'] = array(
				'title' => __( 'Sidebar Locations' ),
				'help'  => __( 'Should any widget areas have versions for each language?' ),
				'type'  => 'checklist',
				'data'  => $sidebars,
			);
		}

		// Add the fields
		Settings::add_fields( $fields, 'localizables' );
	}

	/**
	 * Fields for the Sync Options page.
	 *
	 * @since 2.0.0
	 *
	 * @uses Registry::get() to retrieve the enabled post types.
	 * @uses Registry::add_fields() to add the sycn/clone controls for the page.
	 */
	protected static function setup_sync_fields() {
		// Abort if no post types are registered
		$post_types = Registry::get( 'post_types' );
		if ( ! $post_types ) {
			return;
		}

		// Build the fields list based on the supported post types
		foreach ( $post_types as $post_type ) {
			$field = array(
				'title' => get_post_type_object( $post_type )->labels->name,
				'type'  => 'sync_settings',
				'data'  => $post_type,
			);
			$sync_fields[ "sync_rules[post_type][{$post_type}]" ] = $field;
			$clone_fields[ "clone_rules[post_type][{$post_type}]" ] = $field;
		}

		// The post synchronizing setting fields
		add_settings_section( 'default', null, null, 'nlingual-sync' );
		Settings::add_fields( $sync_fields, 'sync' );

		// The post cloning setting fields
		add_settings_section( 'cloning', __( 'New Translations' ), array( get_called_class(), 'settings_section_cloning' ), 'nlingual-sync' );
		Settings::add_fields( $clone_fields, 'sync', 'cloning' );
	}

	// =========================
	// ! Settings Page Output
	// =========================

	/**
	 * Output for generic settings page.
	 *
	 * @since 2.0.0
	 *
	 * @global $plugin_page The slug of the current admin page.
	 */
	public static function settings_page() {
		global $plugin_page;
?>
		<div class="wrap">
			<h2><?php echo get_admin_page_title(); ?></h2>
			<?php settings_errors(); ?>
			<form method="post" action="options.php" id="<?php echo $plugin_page; ?>-form">
				<?php settings_fields( $plugin_page ); ?>
				<?php do_settings_sections( $plugin_page ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Output for the language management page.
	 *
	 * @since 2.0.0
	 *
	 * @global $plugin_page The slug of the current admin page.
	 *
	 * @uses inc/presets.php For loading the preset languages.
	 */
	public static function settings_page_languages() {
		global $plugin_page;
		?>
		<div class="wrap">
			<h2><?php echo get_admin_page_title(); ?></h2>
			<?php settings_errors( $plugin_page ); ?>
			<form method="post" action="options.php" id="<?php echo $plugin_page; ?>-form">
				<?php settings_fields( $plugin_page ); ?>
				<div id="nl_language_controls">
					<select id="nl_language_preset">
						<option value=""><?php _e( 'Custom Language' ); ?></option>
					</select>
					<button type="button" id="nl_language_add" class="button"><?php _e( 'Add Language' ); ?></button>
				</div>
				<table id="nlingual_languages" class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col" class="nl-language-list_order"><?php _e( 'List Order' ); ?></th>
							<th scope="col" class="nl-language-system_name"><?php _e( 'System Name' ); ?></th>
							<th scope="col" class="nl-language-native_name"><?php _e( 'Native Name' ); ?></th>
							<th scope="col" class="nl-language-short_name"><?php _e( 'Short Name' ); ?></th>
							<th scope="col" class="nl-language-locale_name"><?php _e( 'Locale' ); ?></th>
							<th scope="col" class="nl-language-iso_code"><?php _e( 'ISO' ); ?></th>
							<th scope="col" class="nl-language-slug"><?php _e( 'Slug' ); ?></th>
							<th scope="col" class="nl-language-direction"><?php _e( 'Text Direction' ); ?></th>
							<th scope="col" class="nl-language-active"><?php _e( 'Active?' ); ?></th>
							<td class="nl-language-delete"><?php _e( 'Delete?' ); ?></td>
						</tr>
					</thead>
					<tbody id="nl_language_list">
					</tbody>
				</table>
				<script type="text/template" id="nl_language_row">
					<tr>
						<td class="nl-language-list_order">
							<i class="handle dashicons dashicons-sort"></i>
						</td>
						<td class="nl-language-system_name">
							<input type="text" name="nlingual_languages[%id%][system_name]" value="%system_name%" />
						</td>
						<td class="nl-language-native_name">
							<input type="text" name="nlingual_languages[%id%][native_name]" value="%native_name%" />
						</td>
						<td class="nl-language-short_name">
							<input type="text" name="nlingual_languages[%id%][short_name]" value="%short_name%" />
						</td>
						<td class="nl-language-locale_name">
							<input type="text" name="nlingual_languages[%id%][locale_name]" value="%locale_name%" />
						</td>
						<td class="nl-language-iso_code">
							<input type="text" name="nlingual_languages[%id%][iso_code]" value="%iso_code%" maxlength="2" />
						</td>
						<td class="nl-language-slug">
							<input type="text" name="nlingual_languages[%id%][slug]" value="%slug%" maxlength="100" />
						</td>
						<td class="nl-language-direction">
							<label title="Left to Right"><input type="radio" name="nlingual_languages[%id%][direction]" value="ltr" />LTR</label>
							<label title="Right to Left"><input type="radio" name="nlingual_languages[%id%][direction]" value="rtl" />RTL</label>
						</td>
						<td class="nl-language-active">
							<input type="checkbox" name="nlingual_languages[%id%][active]" value="1" />
						</td>
						<td scope="row" class="nl-language-delete">
							<input type="checkbox" name="nlingual_languages[%id%][delete]" value="1" />
						</td>
					</tr>
				</script>
				<script>
					<?php $presets = require( NL_PLUGIN_DIR . '/inc/presets-languages.php' ); ?>
					var NL_PRESETS = <?php echo json_encode( $presets );?>
				</script>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Output for the cloning options section.
	 *
	 * @since 2.0.0
	 */
	public static function settings_section_cloning() {
		?>
		<p><?php _e( 'When creating a new translation of an existing post (i.e. a clone), what details should be cloned?' ); ?></p>
		<?php
	}
}
