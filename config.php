<?php

define("ZEM_RP_DEFAULT_CUSTOM_CSS",
".related_post_title {
}
ul.related_post {
}
ul.related_post li {
}
ul.related_post li a {
}
ul.related_post li img {
}");

define('ZEM_RP_THUMBNAILS_WIDTH', 150);
define('ZEM_RP_THUMBNAILS_HEIGHT', 150);
define('ZEM_RP_THUMBNAILS_DEFAULTS_COUNT', 31);


define('ZEM_RP_STATIC_THEMES_PATH', 'css-text/');
define('ZEM_RP_STATIC_THEMES_THUMBS_PATH', 'css-img/');
define('ZEM_RP_STATIC_JSON_PATH', 'json/');

define("ZEM_RP_CTR_DASHBOARD_URL", "http://d.related-posts.com/");
define("ZEM_RP_CTR_REPORT_URL", "http://t.related-posts.com/pageview/?");
define("ZEM_RP_STATIC_CTR_PAGEVIEW_FILE", "js/pageview.js");

define("ZEM_RP_STATIC_RECOMMENDATIONS_JS_FILE", "js/recommendations.js");
define("ZEM_RP_STATIC_RECOMMENDATIONS_CSS_FILE", "css-img/recommendations.css");

define("ZEM_RP_ZEMANTA_DASHBOARD_URL", "http://prefs.zemanta.com/dash/");

define("ZEM_RP_STATIC_ZEM_RELATED_POSTS_JS_FILE", "js/related_posts.js");
define("ZEM_RP_STATIC_ZEM_RELATED_POSTS_CSS_FILE", "css/related_posts.css");

define("ZEM_RP_RECOMMENDATIONS_AUTO_TAGS_MAX_WORDS", 200);
define("ZEM_RP_RECOMMENDATIONS_AUTO_TAGS_MAX_TAGS", 15);

define("ZEM_RP_RECOMMENDATIONS_AUTO_TAGS_SCORE", 2);
define("ZEM_RP_RECOMMENDATIONS_TAGS_SCORE", 10);
define("ZEM_RP_RECOMMENDATIONS_CATEGORIES_SCORE", 5);

define("ZEM_RP_RECOMMENDATIONS_NUM_PREGENERATED_POSTS", 50);

define("ZEM_RP_THUMBNAILS_NUM_PREGENERATED_POSTS", 50);

global $zem_rp_options, $zem_rp_meta;
$zem_rp_options = false;
$zem_rp_meta = false;

function zem_rp_get_options() {
	global $zem_rp_options, $zem_rp_meta;
	if($zem_rp_options) {
		return $zem_rp_options;
	}

	$zem_rp_meta = get_option('zem_rp_meta', false);
	if(!$zem_rp_meta || $zem_rp_meta['version'] !== ZEM_RP_VERSION) {
		zem_rp_upgrade();
		$zem_rp_meta = get_option('zem_rp_meta');
	}
	$zem_rp_meta = new ArrayObject($zem_rp_meta);

	$zem_rp_options = new ArrayObject(get_option('zem_rp_options'));

	if ($zem_rp_meta['blog_id']) {
		define('ZEM_RP_ZEMANTA_CONTENT_BASE_URL', 'http://content.zemanta.com/static/');
	}

	return $zem_rp_options;
}

function zem_rp_get_meta() {
	global $zem_rp_meta;

	if (!$zem_rp_meta) {
		zem_rp_get_options();
	}

	return $zem_rp_meta;
}

function zem_rp_update_meta($new_meta) {
	global $zem_rp_meta;

	$new_meta = (array) $new_meta;

	$r = update_option('zem_rp_meta', $new_meta);

	if($r && $zem_rp_meta !== false) {
		$zem_rp_meta->exchangeArray($new_meta);
	}

	return $r;
}

function zem_rp_update_options($new_options) {
	global $zem_rp_options;

	$new_options = (array) $new_options;

	$r = update_option('zem_rp_options', $new_options);

	if($r && $zem_rp_options !== false) {
		$zem_rp_options->exchangeArray($new_options);
	}

	return $r;
}

function zem_rp_activate_hook() {
	zem_rp_get_options();
	zem_rp_schedule_notifications_cron();
}

function zem_rp_deactivate_hook() {
	zem_rp_unschedule_notifications_cron();
}

function zem_rp_upgrade() {
	$zem_rp_meta = get_option('zem_rp_meta', false);
	$version = false;

	if($zem_rp_meta) {
		$version = $zem_rp_meta['version'];
	} else {
		$zem_rp_old_options = get_option('zem_rp', false);
		if($zem_rp_old_options) {
			$version = '1.4';
		}
	}

	if($version) {
		if(version_compare($version, ZEM_RP_VERSION, '<')) {
			call_user_func('zem_rp_migrate_' . str_replace('.', '_', $version));
			zem_rp_upgrade();
		}
	} else {
		zem_rp_install();
	}
}

function zem_rp_related_posts_db_table_install() {
	global $wpdb;

	$tags_table_name = $wpdb->prefix . "zem_rp_tags";
	$sql_tags = "CREATE TABLE $tags_table_name (
	  post_id mediumint(9),
	  time timestamp DEFAULT CURRENT_TIMESTAMP,
	  label VARCHAR(32) NOT NULL,
	  weight float,
	  INDEX post_id (post_id),
	  INDEX label (label)
	 );";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql_tags);

	$latest_posts = get_posts(array('numberposts' => ZEM_RP_RECOMMENDATIONS_NUM_PREGENERATED_POSTS));
	foreach ($latest_posts as $post) {
		zem_rp_generate_tags($post);
	}
}

function zem_rp_install() {
	$zem_rp_meta = array(
		'blog_id' => false,
		'auth_key' => false,
		'zemanta_api_key' => false,
		'version' => ZEM_RP_VERSION,
		'first_version' => ZEM_RP_VERSION,
		'new_user' => true,
		'show_install_tooltip' => true,
		'remote_recommendations' => false,
		'name' => '',
		'email' => '',
		'show_blogger_network_form' => false,
		'remote_notifications' => array(),
		'show_statistics' => false,
		'show_traffic_exchange' => false,
		'zemanta_username' => false,
	);

	$zem_rp_options = array(
		'related_posts_title'			=> __('Related Posts', 'zemanta_related_posts'),
		'related_posts_title_tag'		=> 'h3',
		'display_excerpt'			=> false,
		'excerpt_max_length'			=> 200,
		'max_related_posts'			=> 5,
		'exclude_categories'			=> '',
		'on_single_post'			=> true,
		'on_rss'				=> false,
		'display_comment_count'			=> false,
		'display_publish_date'			=> false,
		'display_thumbnail'			=> false,
		'thumbnail_display_title'		=> true,
		'thumbnail_custom_field'		=> false,
		'thumbnail_use_attached'		=> true,
		'thumbnail_use_custom'			=> false,
		'default_thumbnail_path'		=> false,
		'theme_name' 				=> 'vertical-m.css',
		'theme_custom_css'			=> ZEM_RP_DEFAULT_CUSTOM_CSS,
		'ctr_dashboard_enabled'		=> false,
		'promoted_content_enabled'	=> false,
		'enable_themes'				=> false,
		'custom_theme_enabled' => false,
		'traffic_exchange_enabled' => false,
		'from_around_the_web' => false
	);

	update_option('zem_rp_meta', $zem_rp_meta);
	update_option('zem_rp_options', $zem_rp_options);

	zem_rp_related_posts_db_table_install();
}

/* function zem_rp_migrate_1_0() {
	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_options = get_option('zem_rp_options');

	$zem_rp_meta['version'] = '1.1';

	if(isset($zem_rp_options['show_RP_in_posts'])) {
		unset($zem_rp_options['show_RP_in_posts']);
	}

	unset($zem_rp_meta['show_turn_on_button']);
	unset($zem_rp_meta['turn_on_button_pressed']);

	$zem_rp_meta['zemanta_username'] = false;

	update_option('zem_rp_options', $zem_rp_options);
	update_option('zem_rp_meta', $zem_rp_meta);
} */
