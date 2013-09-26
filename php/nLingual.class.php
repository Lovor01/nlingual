<?php
/*
 * The nLingual class
 * Static use class for utilizing configuration options
 */

define('NL_REDIRECT_USING_PATH', 'NL_REDIRECT_USING_PATH');
define('NL_REDIRECT_USING_DOMAIN', 'NL_REDIRECT_USING_DOMAIN');
define('NL_REDIRECT_USING_ACCEPT', 'NL_REDIRECT_USING_ACCEPT');

class nLingual{
	protected static $options = array();
	protected static $sync_rules = array();
	protected static $languages = array();
	protected static $languages_by_id = array();
	protected static $languages_by_slug = array();
	protected static $post_types;
	protected static $separator;
	protected static $default;
	protected static $cache = array();
	protected static $current;
	protected static $current_cache;
	protected static $domains = array(
		'theme' => 'default',
		'plugin' => 'nLingual'
	);

	/*
	 * Utility function, make $lang the default if === true, the current if === null
	 *
	 * @param mixed &$lang The lang variable to process
	 */
	public static function _lang(&$lang){
		if($lang === null)
			$lang = self::$current;
		elseif($lang === true)
			$lang = self::$default;
	}

	/*
	 * Utility function, return the by_id or by_slug array based on the provided $lang
	 *
	 * @param mixed $lang The language id or slug to use and alter
	 * @return array $languages The proper languages array based on $lang
	 */
	public static function _languages(&$lang){
		$array = self::$languages_by_slug;
		if(is_numeric($lang)){
			$lang = intval($lang);
			$array = self::$languages_by_id;
		}

		return $array;
	}

	/*
	 * Utility function, get the translation_id to use for insert/replace/update queries
	 *
	 * @param int $id The post ID to find the existing translation_id for
	 * @return int $translation_id The id of the translation to use
	 */
	public static function _translation_group_id($id){
		global $wpdb;

		if(!($translation_id = $wpdb->get_var($wpdb->prepare("SELECT group_id FROM $wpdb->nL_translations WHERE post_id = %d", $id)))){
			// This will be new, so we have to get a new translation_id
			$translation_id = $wpdb->get_var("SELECT MAX(group_id) + 1 FROM $wpdb->nL_translations");
		}

		return $translation_id;
	}

	/*
	 * Initialization method
	 * Loads options into local properties
	 *
	 * @uses self::get_option()
	 */
	public static function init(){
		global $wpdb, $table_prefix;

		// Create and register the translations table
		$wpdb->nL_translations = $table_prefix.'nL_translations';
		$wpdb->query("
		CREATE TABLE IF NOT EXISTS `$wpdb->nL_translations` (
			`group_id` bigint(20) NOT NULL,
			`lang_id` bigint(20) NOT NULL,
			`post_id` bigint(20) NOT NULL,
			UNIQUE KEY `post` (`post_id`),
			UNIQUE KEY `translation` (`group_id`, `lang_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1;
		");

		// Create and register the languages table
		$wpdb->nL_languages = $table_prefix.'nL_languages';
		$wpdb->query("
		CREATE TABLE IF NOT EXISTS `$wpdb->nL_languages` (
			`lang_id` bigint(20) NOT NULL AUTO_INCREMENT,
			`system_name` varchar(255) CHARACTER SET utf8 NOT NULL,
			`native_name` varchar(255) CHARACTER SET utf8 NOT NULL,
			`short_name` varchar(10) CHARACTER SET utf8 NOT NULL,
			`slug` char(2) NOT NULL,
			`iso` char(2) NOT NULL,
			`mo` varchar(100) NOT NULL,
			`list_order` int(11) NOT NULL,
			PRIMARY KEY (`lang_id`),
			UNIQUE KEY `slug` (`slug`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1;
		");

		// Load languages
		$languages = $wpdb->get_results("SELECT * FROM $wpdb->nL_languages ORDER BY list_order ASC", OBJECT);
		// Default to english if no langauges are set
		if(!$languages) $languages = array(
			(object) array(
				'lang_id'		=> 1,
				'system_name'	=> 'English',
				'native_name'	=> 'English',
				'short_name'	=> 'En',
				'slug'			=> 'en',
				'iso'			=> 'en',
				'mo'			=> 'english',
				'list_order'	=> 0,
			)
		);
		self::$languages = $languages;

		// Loop through the languages and create a lang_id and slug indexed version
		foreach($languages as $lang){
			self::$languages_by_id[$lang->lang_id] = $lang;
			self::$languages_by_slug[$lang->slug] = $lang;
		}

		// Load options
		self::$options = wp_parse_args((array) get_option('nLingual-options'), array(
			// Default language
			'default_lang' => $languages[0]->lang_id,

			// Redirection settings
			'method' => NL_REDIRECT_USING_ACCEPT,
			'get_var' => 'lang',
			'post_var' => 'lang',
			'skip_default_l10n' => false,

			// Supported post types
			'post_types' => array('page', 'post'),

			// Split settings
			'split_separator' => '//',

			// Auto localize...
			'l10n_dateformat' => true
		));

		// Load sync rules
		self::$sync_rules = (array) get_option('nLingual-sync_rules', array());

		// Load  post types, defualt language, and set current langauge
		self::$post_types = self::get_option('post_types');
		self::$default = self::lang_slug(self::get_option('default_lang'));
		self::$current = self::$default;

		// Load the text domain
		add_action('plugins_loaded', array('nLingual', 'onloaded'));
	}

	/*
	 * Hook to run when the plugin is loaded
	 * loads text domain for this plugin
	 */
	public static function onloaded(){
		load_plugin_textdomain('nLingual', false, plugins_url('lang/', NL_SELF));
	}

	/*
	 * Return the value of a particular option
	 *
	 * @param string $name The name of the option to retrieve
	 */
	public static function get_option($name){
		if(isset(self::$options[$name])){
			return self::$options[$name];
		}

		return null;
	}

	/*
	 * Return the rule(s) for a specific post type (and maybe type)
	 *
	 * @param string $post_type The slug of the post type to retrieve rules for
	 * @param string $rule_type The specific rule type to retrieve
	 * @return array $rules The request rules (empty array if nothing found)
	 */
	public static function sync_rules($post_type, $rule_type = null){
		if(isset(self::$sync_rules[$post_type])){
			if(isset(self::$sync_rules[$post_type][$rule_type])){
				return self::$sync_rules[$post_type][$rule_type];
			}else{
				return array();
			}
			return self::$sync_rules[$post_type];
		}else{
			return array();
		}
	}

	/*
	 * Return the languages array
	 */
	public static function languages(){
		return self::$languages;
	}

	/*
	 * Return the languages by id array
	 */
	public static function langs_by_id(){
		return self::$languages_by_id;
	}

	/*
	 * Return the languages by slug array
	 */
	public static function langs_by_slug(){
		return self::$languages_by_slug;
	}

	/*
	 * Return the post_types array
	 */
	public static function post_types(){
		return self::$post_types;
	}

	/*
	 * Return the default language
	 */
	public static function default_lang(){
		return self::$default;
	}

	/*
	 * Return the current language
	 */
	public static function current_lang(){
		return self::$current;
	}

	/*
	 * Test if the current langauge is the specified language
	 */
	public static function is_lang($lang){
		return self::$current == $lang;
	}

	/*
	 * Test if the current langauge is the default language
	 */
	public static function is_default(){
		return self::is_lang(self::$default);
	}

	/*
	 * Get the cached data for an object
	 *
	 * @param mixed $id The ID of cached object
	 * @param string $section The name of the section to cache under
	 */
	public static function cache_get($id, $section){
		return self::$cache[$section][$id];
	}

	/*
	 * Cache some data for an object
	 *
	 * @param mixed $id The ID of cached object
	 * @param mixed $data The data to cache for the object
	 * @param string $section The name of the section to cache under
	 */
	public static function cache_set($id, $data, $section){
		self::$cache[$section][$id] = $data;
	}

	/*
	 * Test if a language is registered
	 *
	 * @param string $lang The slug of the language
	 */
	public static function lang_exists($lang){
		$array = self::_languages($lang);
		return isset($array[$lang]);
	}

	/*
	 * Test if a post type is registered to use nLingual
	 *
	 * @param mixed $type The slug of the post_type (null/false/"" = post)
	 * @param bool $all Wether to match all or at least one (if $type is array)
	 */
	public static function post_type_supported($type = 'post', $all = true){
		if(!$type) $type = 'post';

		if(is_array($type)){
			$match = array_intersect($type, self::$post_types);
			return $all ? count($match) == count($type) : $match;
		}

		return in_array($type, self::$post_types);
	}

	/*
	 * Get the langauge property (or the full object) of a specified langauge
	 *
	 * @uses self::_lang()
	 * @uses self::lang_exists()
	 *
	 * @param string $field Optional The field to retrieve
	 * @param mixed $lang Optional The slug/id of the language to retrieve from
	 */
	public static function get_lang($field = null, $lang = null){
		self::_lang($lang);

		$array = self::_languages($lang);

		// Handle shorthand names for fields
		switch($field){
			case 'id': $field = 'lang_id'; break;
			case 'name': $field = 'system_name'; break;
			case 'native': $field = 'native_name'; break;
			case 'order': $field = 'list_order'; break;
		}

		if(!isset($array[$lang])) return false;
		if($field === true) return $array[$lang];
		return $array[$lang]->$field;
	}

	/*
	 * Get the lang_id based on the slug provided
	 *
	 * @uses self::get_lang()
	 *
	 * @param string $slug The slug of the langauge to fetch
	 */
	public static function lang_id($slug){
		return self::get_lang('lang_id', $slug);
	}

	/*
	 * Get the slug based on the lang_id provided
	 *
	 * @uses self::get_lang()
	 *
	 * @param int $lang_id The id of the langauge to fetch
	 */
	public static function lang_slug($lang_id){
		return self::get_lang('slug', $lang_id);
	}

	/*
	 * Set the current langauge
	 *
	 * @uses self::lang_exists()
	 *
	 * @param string $lang The language to set/switchto
	 * @param bool $lock Wether or not to lock the change
	 */
	public static function set_lang($lang, $lock = true){
		if(defined('NLINGUAL_LANG_SET')) return;
		if($lock) define('NLINGUAL_LANG_SET', true);

		if(self::lang_exists($lang))
			self::$current = self::$current_cache = $lang;
	}

	/*
	 * Switch to the specified language (does not affect loaded text domain)
	 */
	public static function switch_lang($lang){
		self::$current = $lang;
	}

	/*
	 * Restore the current language to what it was before
	 */
	public static function restore_lang(){
		self::$current = self::$current_cache;
	}

	/*
	 * Get the language of the post in question, according to the nL_translations table
	 *
	 * @uses self::_lang()
	 * @uses self::cache_get()
	 * @uses self::cache_set()
	 *
	 * @param mixed $id The ID or object of the post in question (defaults to current $post)
	 * @param string $default The default value to return should none be found
	 */
	public static function get_post_lang($id = null, $default = false){
		global $wpdb;

		if(is_null($id)){
			global $post;
			$id = $post->ID;
		}if(is_object($id)){
			$id = $id->ID;
		}

		// Check if it's cached, return if so
		if($lang = self::cache_get($id, 'translations')) return $lang;

		// Query the nL_translations table for the langauge of the post in question
		$lang_id = $wpdb->get_var($wpdb->prepare("SELECT lang_id FROM $wpdb->nL_translations WHERE post_id = %d", $id));
		$lang = self::lang_slug($lang_id);

		// If no language is found, make it the $default one
		if(!$lang){
			self::_lang($default);
			$lang = $default;
		}

		// Add it to the cache
		self::cache_set($id, $lang, 'translations');

		return $lang;
	}

	/*
	 * Set the language of the post in question for the nL_translations table
	 *
	 * @users self::_lang()
	 * @users self::_translation_group_id()
	 * @users self::cache_set()
	 *
	 * @param mixed $id The ID or object of the post in question (defaults to current $post)
	 * @param string $lang The language to set the post to (defaults to default language)
	 */
	public static function set_post_lang($id = null, $lang = null){
		global $wpdb;

		if(is_null($id)){
			global $post;
			$id = $post->ID;
		}if(is_object($id)){
			$id = $id->ID;
		}

		self::_lang($lang);

		// Run the REPLACE query
		$wpdb->replace(
			$wpdb->nL_translations,
			array(
				'group_id' => self::_translation_group_id($id),
				'lang_id' => self::lang_id($lang),
				'post_id' => $id
			),
			array('%d', '%s', '%d')
		);

		// Add/Update the cache of it, just in case
		self::cache_set($id, $lang);
	}

	/*
	 * Delete the langauge link for the post in question
	 *
	 * @param mixed $id The ID or object of the post in question
	 */
	public static function delete_translation($id){
		global $wpdb;

		return $wpdb->delete(
			$wpdb->nL_translations,
			array('post_id' => $id),
			array('%d')
		);
	}

	/*
	 * Test if a post is in the specified language
	 *
	 * @uses self::get_post_lang()
	 *
	 * @param mixed $id The ID or object of the post in question (defaults to current $post)
	 */
	public static function in_this_lang($id, $lang){
		return self::get_post_lang($id) == $lang;
	}

	/*
	 * Test if a post is in the default language
	 *
	 * @uses self::get_post_lang()
	 *
	 * @param mixed $id The ID or object of the post in question (defaults to current $post)
	 */
	public static function in_default_lang($id = null){
		return self::in_this_lang($id, self::$default);
	}

	/*
	 * Test if a post is in the current language
	 *
	 * @uses self::get_post_lang()
	 *
	 * @param mixed $id The ID or object of the post in question (defaults to current $post)
	 */
	public static function in_current_lang($id){
		return self::in_this_lang($id, self::$current);
	}

	/*
	 * Get the translation of the post in the provided language, via the nL_translations table
	 *
	 * @uses self::_lang()
	 *
	 * @param mixed $id The ID or object of the post in question (defaults to current $post)
	 * @param string $lang The slug of the language requested (defaults to current language)
	 * @param bool $return_self Wether or not to return the provided $id or just false should no original be found
	 */
	public static function get_translation($id, $lang = null, $return_self = true){
		global $wpdb;

		if(is_null($id)){
			global $post;
			$id = $post->ID;
		}if(is_object($id)){
			$id = $id->ID;
		}

		self::_lang($lang);

		// Get the language according to the translations table
		$translation = $wpdb->get_var($wpdb->prepare("
			SELECT
				t1.post_id
			FROM
				$wpdb->nL_translations AS t1
				LEFT JOIN
					$wpdb->nL_translations AS t2
					ON (t1.group_id = t2.group_id)
			WHERE
				t2.post_id = %d
				AND t1.lang_id = %s
		", $id, self::lang_id($lang)));

		// Return the translation's id if found
		if($translation) return $translation;

		// Otherwise, return the original id or false, depending on $return_self
		return $return_self ? $id : false;
	}

	/*
	 * Associate translations together in the nL_translations table
	 *
	 * @uses self::_translation_group_id()
	 *
	 * @param int $post_id The id of the post to use as an achor
	 * @param array $posts The ids of the other posts to link together (in lang => post_id format)
	 */
	public static function associate_posts($post_id, $posts){
		global $wpdb;

		$group_id = self::_translation_group_id($post_id);

		$query = "
			REPLACE INTO
				$wpdb->nL_translations
				(group_id, lang_id, post_id)
			VALUES
		";

		$values = array();
		foreach($posts as $lang => $id){
			if($id <= 0) continue; // Not an actual post
			$lang_id = self::lang_id($lang);
			$values[] = $wpdb->prepare("(%d,%s,%d)", $group_id, $lang_id, $id);
		}

		$query .=  implode(',', $values);

		$wpdb->query($query);
	}

	/*
	 * Return the IDs of all posts associated with this one, according to the nL_translations table
	 *
	 * @param int $post_id The id of the post
	 * @param bool $include_self Wether or not to include itself in the returned list
	 */
	public static function associated_posts($post_id, $include_self = false){
		global $wpdb;

		$query = "
			SELECT
				l.slug as language,
				t1.post_id
			FROM
				$wpdb->nL_translations AS t1
			LEFT JOIN
				$wpdb->nL_translations AS t2
				ON (t1.group_id = t2.group_id)
			LEFT JOIN
				$wpdb->nL_languages AS l
				ON (t1.lang_id = l.lang_id)
			WHERE
				t2.post_id = %1\$d
		";

		if(!$include_self){
			$query .= "AND t1.post_id != %1\$d";
		}

		$results = $wpdb->get_results($wpdb->prepare($query, $post_id));

		$posts = array();
		foreach($results as $row){
			$posts[$row->language] = $row->post_id;
		}

		return $posts;
	}

	/*
	 * Process a URL (the host and uri portions) and get the language, along with update host/uri
	 *
	 * @uses self::get_option()
	 * @uses self::lang_exists()
	 *
	 * @param string $host The host name
	 * @param string $uri The requested uri
	 * @return array $result An array of the resulting language, host name and requested uri
	 */
	public static function process_url($host, $uri){
		$lang = null;

		// Proceed based on method
		switch(self::get_option('method')){
			case NL_REDIRECT_USING_DOMAIN:
				// Check if a language slug is present and is an existing language
				if(preg_match('#^([a-z]{2})\.#i', $host, $match) && self::lang_exists($match[1])){
					$lang = $match[1];
					$host = substr($host, 3); // Recreate the hostname sans the language slug at the beginning
				}
				break;
			case NL_REDIRECT_USING_PATH:
				// Get the path of the home URL, with trailing slash
				$home = trailingslashit(parse_url(get_option('home'), PHP_URL_PATH));

				// Strip the home path from the beginning of the URI
				$uri = substr($uri, strlen($home)); // Now /en/... or /mysite/en/... will become en/...

				// Check if a language slug is present and is an existing language
				if(preg_match('#^([a-z]{2})(/.*)?$#i', $uri, $match) && self::lang_exists($match[1])){
					$lang = $match[1];
					$uri = $home.substr($uri, 3); // Recreate the url sans the language slug and slash after it
				}
				break;
		}

		if($lang){
			return array('lang' => $lang, 'host' => $host, 'uri' => $uri);
		}
	}

	/*
	 * Localize the URL with the supplied language
	 *
	 * @uses self::cache_get()
	 * @uses self::cache_set()
	 * @uses self::current_lang()
	 * @uses self::get_option()
	 * @uses self::process_url()
	 *
	 * @param string $url The URL to localize
	 * @param string $lang The language to localize with (default's to current language)
	 * @param bool $force_admin Wether or not to run it within the admin
	 * @param bool $relocalize Wether or not to relocalize the url if it already is
	 */
	public static function localize_url($url, $lang = null, $force_admin = false, $relocalize = false){
		if(defined('WP_ADMIN') && !$force_admin) return $url; // Don't bother in Admin mode

		if(is_null($lang)) $lang = self::current_lang();

		// Create an identifier for the url for caching
		$id = "[$lang]$url";

		// Check if this URL has been taken care of, return cached result
		if($cached = self::cache_get($id, 'url')){
			return $cached;
		}

		$home = trailingslashit(get_option('home'));

		// Only proceed if it's a proper absolute URL for within the site
		if(strpos($url, $home) !== false){
			// Parse and process the url
			$url_data = parse_url($url);
			$processed = self::process_url($url_data['host'], $url_data['path']);

			// If successfully processed and $relocalize is true,
			// update $url_data with the $processed info, and rebuild $url
			if($relocalize && $processed){
				$url_data['host'] = $processed['host'];
				$url_data['path'] = substr($processed['uri'], intval(strpos($processed['uri'], '?')));
				$url = sprintf('%s://%s%s', $url_data['scheme'], $url_data['host'], $url_data['path']);
			}

			// If processing failed (or $relocalize is true,
			// and if the URL is not a wp-admin one,
			// and if we're not in the default language (or if skil_default_l10n is disabled)
			// Go ahead and localize the URL
			if(
				(!$processed || $relocalize)
				&& strpos($url_data['path'], '/wp-admin/') === false
				&& ($lang != self::$default || !self::get_option('skip_default_l10n'))
			){
				switch(self::get_option('method')){
					case NL_REDIRECT_USING_DOMAIN:
						$url = sprintf('%s://%s.%s%s', $url_data['scheme'], $lang, $url_data['host'], $url_data['path']);
						break;
					case NL_REDIRECT_USING_PATH:
						$url = sprintf('%s://%s/%s%s', $url_data['scheme'], $url_data['host'], $lang, $url_data['path']);
						break;
					default:
						$url .= '?'.self::get_option('get_var').'='.$lang;
				}
			}
		}

		// Store the URL in the cache
		self::cache_set($id, $url, 'url');

		return $url;
	}

	/*
	 * Get the permalink of the specified post in the specified language
	 *
	 * @uses self::_lang()
	 * @uses self::get_translation()
	 * @uses self::localize_url()
	 *
	 * @param mixed $id The ID or object of the post in question (defaults to current $post)
	 * @param string $lang The slug of the language requested (defaults to current language)
	 * @param bool $echo Wether or not to echo the resulting $link
	 */
	public static function get_permalink($id = null, $lang = null, $echo = true){
		global $wpdb;

		self::_lang($lang);

		$link = get_permalink(self::get_translation($id, $lang));

		$link = self::localize_url($link, $lang);

		if($echo) echo $link;
		return $link;
	}

	/*
	 * Find the post with the provided path, and return the one for the provided language version
	 *
	 * @uses self::_lang()
	 * @uses self::get_permalink()
	 *
	 * @param string $path The path (in /parent/child/ or /page/ form) of the page to find
	 * @param string $post_type The slug of the post type it should be looking for (defaults to page)
	 * @param string $lang The slug of the language requested (defaults to current language)
	 * @param bool $echo Wether or not to echo the resulting $link
	 */
	public static function translate_link($path, $post_type = null, $lang = null, $echo = true){
		global $wpdb;

		if(!$post_type) $post_type = 'page';

		self::_lang($lang);

		$post = get_page_by_path(trim($path, '/'), OBJECT, $post_type);

		return self::get_permalink($post->ID, $lang, $echo);
	}

	/*
	 * Return or print a list of links to the current page in all available languages
	 *
	 * @uses self::get_permalink()
	 *
	 * @param bool $echo Wether or not to echo the imploded list of links
	 * @param string $prefix The text to preceded the link list with
	 * @param string $sep The text to separate each link with
	 */
	public static function lang_links($echo = false, $prefix = '', $sep = ' '){
		echo $prefix;
		$links = array();
		foreach(self::$languages as $lang){
			$links[] = sprintf('<a href="%s">%s</a>', self::get_permalink(get_queried_object()->ID, $lang['slug'], false), $lang['native']);
		}

		if($echo) echo $prefix.implode($sep, $links);
		return $links;
	}

	/*
	 * Split a string at the separator and return the part corresponding to the specified language
	 *
	 * @uses self::get_option()
	 *
	 * @param string $text The text to split
	 * @param string $lang The slug of the language requested (defaults to current language)
	 * @param string $sep The separator to use when splitting the string ($defaults to global separator)
	 * @param bool $force Wether or not to force the split when it normally would be skipped
	 */
	public static function split_langs($text, $lang = null, $sep = null, $force = false){
		self::_lang($lang);

		if(is_null($sep))
			$sep = self::get_option('separator');

		if(!$sep) return $text;

		if(is_admin() && !$force && did_action('admin_notices')) return $text;

		$langs = array_map(function($lang){
			return $lang->slug;
		}, self::$languages);

		$langn = array_search($lang, $langs);

		$sep = preg_quote($sep, '/');
		$text = preg_split("/\s*$sep\s*/", $text);

		if(isset($text[$langn])){
			$text = $text[$langn];
		}else{
			$text = $text[0];
		}

		return $text;
	}
}