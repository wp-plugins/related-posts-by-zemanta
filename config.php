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

define('ZEM_RP_THUMBNAILS_NAME', 'zem_rp_thumbnail');
define('ZEM_RP_THUMBNAILS_PROP_NAME', 'zem_rp_thumbnail_prop');
define('ZEM_RP_THUMBNAILS_WIDTH', 150);
define('ZEM_RP_THUMBNAILS_HEIGHT', 150);
define('ZEM_RP_THUMBNAILS_DEFAULTS_COUNT', 31);


define('ZEM_RP_STATIC_THEMES_PATH', 'zem-css/');
define('ZEM_RP_STATIC_JSON_PATH', 'json/');

define("ZEM_RP_CTR_DASHBOARD_URL", "http://d.zemanta.com/");
define("ZEM_RP_STATIC_CTR_PAGEVIEW_FILE", "js/pageview.js");

define("ZEM_RP_STATIC_RECOMMENDATIONS_JS_FILE", "js/recommendations.js");
define("ZEM_RP_STATIC_RECOMMENDATIONS_CSS_FILE", "zem-css/recommendations.css");

define("ZEM_RP_STATIC_INFINITE_RECS_JS_FILE", "js/infiniterecs.js");
define("ZEM_RP_STATIC_PINTEREST_JS_FILE", "js/pinterest.js");

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

define("ZEM_RP_MAX_LABEL_LENGTH", 32);

global $zem_rp_options, $zem_rp_meta;
$zem_rp_options = false;
$zem_rp_meta = false;

function zem_rp_get_options() {
	global $zem_rp_options, $zem_rp_meta;
	if($zem_rp_options) {
		return $zem_rp_options;
	}

	$zem_rp_meta = get_option('zem_rp_meta', false);
	$zem_rp_options = get_option('zem_rp_options', false);

	if(!$zem_rp_meta || !$zem_rp_options || $zem_rp_meta['version'] !== ZEM_RP_VERSION) {
		zem_rp_upgrade();
		$zem_rp_meta = get_option('zem_rp_meta');
		$zem_rp_options = get_option('zem_rp_options');
	}

	$zem_rp_meta = new ArrayObject($zem_rp_meta);
	$zem_rp_options = new ArrayObject($zem_rp_options);

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

function zem_rp_related_posts_db_table_uninstall() {
	global $wpdb;

	$tags_table_name = $wpdb->prefix . "zem_rp_tags";

	$sql = "DROP TABLE " . $tags_table_name;

	$wpdb->query($sql);
}

function zem_rp_related_posts_db_table_install() {
	global $wpdb;

	$tags_table_name = $wpdb->prefix . "zem_rp_tags";
	$sql_tags = "CREATE TABLE $tags_table_name (
	  post_id mediumint(9),
	  post_date datetime NOT NULL,
	  label VARCHAR(" . ZEM_RP_MAX_LABEL_LENGTH . ") NOT NULL,
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
		'blog_tg' => rand(0, 1),
		'remote_recommendations' => false,
		'name' => '',
		'email' => '',
		'remote_notifications' => array(),
		'show_statistics' => false,
		'show_traffic_exchange' => false,
		'zemanta_username' => false,
		'classic_user' => strpos(get_bloginfo('language'), 'en') === 0 // Enable only if "any" english is the default language
	);

	$zem_rp_options = array(
		'related_posts_title'			=> __('Related Posts', 'zemanta_related_posts'),
		'max_related_posts'			=> 6,
		'exclude_categories'			=> '',
		'on_single_post'			=> true,
		'on_rss'				=> false,
		'max_related_post_age_in_days' => 0,

		'default_thumbnail_path'		=> false,
		'thumbnail_use_custom' => false,
		'thumbnail_custom_field' => '',
		'display_zemanta_linky' => false,

		'mobile' => array(
			'display_comment_count'			=> false,
			'display_publish_date'			=> false,
			'display_thumbnail'			=> true,
			'display_excerpt'			=> false,
			'excerpt_max_length'			=> 200,
			'theme_name' 				=> 'm-stream.css',
			'theme_custom_css'			=> ZEM_RP_DEFAULT_CUSTOM_CSS,
			'custom_theme_enabled' => false
		),
		'desktop' => array(
			'display_comment_count'			=> false,
			'display_publish_date'			=> false,
			'display_thumbnail'			=> true,
			'display_excerpt'			=> false,
			'excerpt_max_length'			=> 200,
			'theme_name' 				=> 'vertical.css',
			'theme_custom_css'			=> ZEM_RP_DEFAULT_CUSTOM_CSS,
			'custom_theme_enabled' => false
		),
	);

	update_option('zem_rp_meta', $zem_rp_meta);
	update_option('zem_rp_options', $zem_rp_options);

	zem_rp_register_blog();

	zem_rp_related_posts_db_table_install();

	zem_rp_process_latest_post_thumbnails();
}

function zem_is_classic() {
	$meta = zem_rp_get_meta();
	if (isset($meta['classic_user']) && $meta['classic_user']) {
		return true;
	}
	return false;
}

function zem_rp_migrate_1_7() {
	global $wpdb;

	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_meta['version'] = '1.8';
	$zem_rp_meta['new_user'] = false;
	if (strpos(get_bloginfo('language'), 'en') === 0) {
		$zem_rp_meta['classic_user'] = true;
	}

	update_option('zem_rp_meta', $zem_rp_meta);
}

function zem_rp_migrate_1_6() {
	global $wpdb;

	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_meta['version'] = '1.7';
	$zem_rp_meta['new_user'] = false;

	update_option('zem_rp_meta', $zem_rp_meta);
}

function zem_rp_migrate_1_5() {
	global $wpdb;

	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_meta['version'] = '1.6';
	$zem_rp_meta['new_user'] = false;

	$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key IN ('_zem_rp_extracted_image_url', '_zem_rp_extracted_image_url_full')");

	update_option('zem_rp_meta', $zem_rp_meta);
}

function zem_rp_migrate_1_4() {
	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_meta['version'] = '1.5';
	$zem_rp_meta['new_user'] = false;
	update_option('zem_rp_meta', $zem_rp_meta);
}

function zem_rp_migrate_1_3_1() {
	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_meta['version'] = '1.4';
	$zem_rp_meta['new_user'] = false;
	update_option('zem_rp_meta', $zem_rp_meta);
}

function zem_rp_migrate_1_3() {
	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_options = get_option('zem_rp_options');

	$zem_rp_meta['version'] = '1.3.1';

	$zem_rp_options['display_zemanta_linky'] = false;

	update_option('zem_rp_options', $zem_rp_options);
	update_option('zem_rp_meta', $zem_rp_meta);
}

function zem_rp_migrate_1_2() {
	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_options = get_option('zem_rp_options');

	$zem_rp_meta['version'] = '1.3';

	if (!isset($zem_rp_meta['blog_tg'])) {
		$zem_rp_meta['blog_tg'] = rand(0, 1);
	}

	update_option('zem_rp_options', $zem_rp_options);
	update_option('zem_rp_meta', $zem_rp_meta);
}

function zem_rp_migrate_1_1() {
	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_options = get_option('zem_rp_options');

	$zem_rp_meta['version'] = '1.2';

	if (!isset($zem_rp_meta['zemanta_username'])) {
		$zem_rp_meta['zemanta_username'] = false;
	}

	$display_options = array(
		'display_comment_count'			=> $zem_rp_options['display_comment_count'],
		'display_publish_date'			=> $zem_rp_options['display_publish_date'],
		'display_thumbnail'			=> $zem_rp_options['display_thumbnail'],
		'display_excerpt'			=> $zem_rp_options['display_excerpt'],
		'excerpt_max_length'			=> $zem_rp_options['excerpt_max_length'],
		'theme_name' 				=> $zem_rp_options['theme_name'],
		'theme_custom_css'			=> $zem_rp_options['theme_custom_css'],
		'custom_theme_enabled' => $zem_rp_options['custom_theme_enabled']
	);

	$zem_rp_options['desktop'] = $display_options;
	$zem_rp_options['mobile'] = $display_options;

	if ($zem_rp_options['mobile']['theme_name'] !== 'plain.css') {
		$zem_rp_options['mobile']['theme_name'] = 'm-stream.css';
	}

	update_option('zem_rp_options', $zem_rp_options);
	$zem_rp_options = get_option('zem_rp_options');

	unset($zem_rp_options['traffic_exchange_enabled']);
	unset($zem_rp_options['promoted_content_enabled']);
	unset($zem_rp_options['ctr_dashboard_enabled']);

	unset($zem_rp_options['thumbnail_use_attached']);
	unset($zem_rp_options['thumbnail_display_title']);

	unset($zem_rp_options['display_comment_count']);
	unset($zem_rp_options['display_publish_date']);
	unset($zem_rp_options['display_thumbnail']);
	unset($zem_rp_options['display_excerpt']);
	unset($zem_rp_options['excerpt_max_length']);
	unset($zem_rp_options['theme_name']);
	unset($zem_rp_options['theme_custom_css']);
	unset($zem_rp_options['custom_theme_enabled']);

	unset($zem_rp_options['from_around_the_web']);

	update_option('zem_rp_options', $zem_rp_options);
	update_option('zem_rp_meta', $zem_rp_meta);
}

function zem_rp_migrate_1_0() {
	$zem_rp_meta = get_option('zem_rp_meta');
	$zem_rp_options = get_option('zem_rp_options');

	$zem_rp_meta['version'] = '1.1';

	if (!isset($zem_rp_meta['zemanta_username'])) {
		$zem_rp_meta['zemanta_username'] = false;
	}

	$zem_rp_options['max_related_post_age_in_days'] = 0;

	zem_rp_related_posts_db_table_uninstall();
	zem_rp_related_posts_db_table_install();

	update_option('zem_rp_options', $zem_rp_options);
	update_option('zem_rp_meta', $zem_rp_meta);
}
