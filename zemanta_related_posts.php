<?php
/*
Plugin Name: Related Posts by Zemanta
Version: 1.3.2
Plugin URI: http://wordpress.org/extend/plugins/zemanta-related-posts/
Description: Quickly increase your readers' engagement with your posts by adding Related Posts in the footer of your content. Click on <a href="admin.php?page=zemanta-related-posts">Zemanta tab</a> to configure your settings.
Author: Zemanta Ltd.
Author URI: http://www.zemanta.com/
*/

define('ZEM_RP_VERSION', '1.3.1');

define('ZEM_RP_PLUGIN_FILE', plugin_basename(__FILE__));

include_once(dirname(__FILE__) . '/config.php');
include_once(dirname(__FILE__) . '/lib/stemmer.php');
include_once(dirname(__FILE__) . '/lib/mobile_detect.php');

include_once(dirname(__FILE__) . '/admin_notices.php');
include_once(dirname(__FILE__) . '/notifications.php');
include_once(dirname(__FILE__) . '/widget.php');
include_once(dirname(__FILE__) . '/thumbnailer.php');
include_once(dirname(__FILE__) . '/settings.php');
include_once(dirname(__FILE__) . '/recommendations.php');
include_once(dirname(__FILE__) . '/dashboard_widget.php');
include_once(dirname(__FILE__) . '/edit_related_posts.php');
include_once(dirname(__FILE__) . '/compatibility.php');

register_activation_hook(__FILE__, 'zem_rp_activate_hook');
register_deactivation_hook(__FILE__, 'zem_rp_deactivate_hook');

add_action('wp_head', 'zem_rp_head_resources');
add_action('wp_before_admin_bar_render', 'zem_rp_extend_adminbar');

function zem_rp_extend_adminbar() {
	global $wp_admin_bar;

	if(!is_super_admin() || !is_admin_bar_showing())
		return;

	$wp_admin_bar->add_menu(array(
		'id' => 'zem_rp_adminbar_menu',
		'title' => __('Zemanta', 'zemanta_related_posts'),
		'href' => admin_url('admin.php?page=zemanta-related-posts&ref=adminbar')
	));
}

global $zem_rp_output;
$zem_rp_output = array();
function zem_rp_add_related_posts_hook($content) {
	global $zem_rp_output, $post;
	$options = zem_rp_get_options();

	if ($post->post_type === 'post' && (($options["on_single_post"] && is_single()) || (is_feed() && $options["on_rss"]))) {
		if (!isset($zem_rp_output[$post->ID])) {
			$zem_rp_output[$post->ID] = zem_rp_get_related_posts();
		}
		$content = $content . $zem_rp_output[$post->ID];
	}

	return $content;
}
add_filter('the_content', 'zem_rp_add_related_posts_hook', 10);

global $zem_rp_is_phone;
function zem_rp_is_phone() {
	global $zem_rp_is_phone;

	if (!isset($zem_rp_is_phone)) {
		$detect = new ZemMobileDetect();
		$zem_rp_is_phone = $detect->isMobile() && !$detect->isTablet();
	}

	return $zem_rp_is_phone;
}

function zem_rp_get_platform_options() {
	$options = zem_rp_get_options();

	if (zem_rp_is_phone()) {
		return $options['mobile'];
	}
	return $options['desktop'];
}

function zem_rp_ajax_load_articles_callback() {
	global $post;

	$getdata = stripslashes_deep($_GET);
	if (!isset($getdata['post_id'])) {
		die('error');
	}

	$post = get_post($getdata['post_id']);
	if(!$post) {
		die('error');
	}

	$from = (isset($getdata['from']) && is_numeric($getdata['from'])) ? intval($getdata['from']) : 0;
	$count = (isset($getdata['count']) && is_numeric($getdata['count'])) ? intval($getdata['count']) : 50;

	$image_size = isset($getdata['size']) ? $getdata['size'] : 'thumbnail';
	if(!($image_size == 'thumbnail' || $image_size == 'full')) {
		die('error');
	}

	$limit = $count + $from;

	$related_posts = array();

	zem_rp_append_posts($related_posts, 'zem_rp_fetch_related_posts_v2', $limit);
	zem_rp_append_posts($related_posts, 'zem_rp_fetch_related_posts', $limit);
	zem_rp_append_posts($related_posts, 'zem_rp_fetch_random_posts', $limit);

	if(function_exists('qtrans_postsFilter')) {
		$related_posts = qtrans_postsFilter($related_posts);
	}

	$response_list = array();

	foreach (array_slice($related_posts, $from) as $related_post) {
		array_push($response_list, array(
				'id' => $related_post->ID,
				'url' => get_permalink($related_post->ID),
				'title' => $related_post->post_title,
				'img' => zem_rp_get_post_thumbnail_img($related_post, $image_size)
			));
	}

	header('Content-Type: text/javascript');
	die(json_encode($response_list));
}
add_action('wp_ajax_zem_rp_load_articles', 'zem_rp_ajax_load_articles_callback');
add_action('wp_ajax_nopriv_zem_rp_load_articles', 'zem_rp_ajax_load_articles_callback');

function zem_rp_append_posts(&$related_posts, $fetch_function_name, $limit) {
	$options = zem_rp_get_options();

	$len = sizeof($related_posts);
	$num_missing_posts = $limit - $len;
	if ($num_missing_posts > 0) {
		$exclude_ids = array_map(create_function('$p', 'return $p->ID;'), $related_posts);

		$posts = call_user_func($fetch_function_name, $num_missing_posts, $exclude_ids);
		if ($posts) {
			$related_posts = array_merge($related_posts, $posts);
		}
	}
}

function zem_rp_fetch_posts_and_title() {
	$options = zem_rp_get_options();

	$limit = $options['max_related_posts'];
	$title = $options["related_posts_title"];

	$related_posts = array();

	zem_rp_append_posts($related_posts, 'zem_rp_fetch_related_posts_v2', $limit);
	zem_rp_append_posts($related_posts, 'zem_rp_fetch_related_posts', $limit);
	zem_rp_append_posts($related_posts, 'zem_rp_fetch_random_posts', $limit);

	if(function_exists('qtrans_postsFilter')) {
		$related_posts = qtrans_postsFilter($related_posts);
	}

	return array(
		"posts" => $related_posts,
		"title" => $title
	);
}

function zem_rp_get_next_post(&$related_posts, &$selected_related_posts, &$inserted_urls, &$special_urls, $default_post_type) {
	$post = false;

	while (!($post && $post->ID) && !(empty($related_posts) && empty($selected_related_posts))) {
		$post = array_shift($selected_related_posts);
		$post_type = $default_post_type;

		if ($post && $post->type) {
			$post_type = $post->type;
		}

		if (!$post || !$post->ID) {
			while (!empty($related_posts) && (!($post = array_shift($related_posts)) || isset($special_urls[get_permalink($post->ID)])));
		}
		if ($post && $post->ID) {
			$post_url = property_exists($post, 'post_url') ? $post->post_url : get_permalink($post->ID);
			if (isset($inserted_urls[$post_url])) {
				$post = false;
			} else {
				$post->type = $post_type;
			}
		}
	}

	if (!$post || !$post->ID) {
		return false;
	}

	$inserted_urls[$post_url] = true;

	return $post;
}

function zem_rp_generate_related_posts_list_items($related_posts, $selected_related_posts) {
	$options = zem_rp_get_options();
	$platform_options = zem_rp_get_platform_options();
	$output = "";

	$limit = $options['max_related_posts'];

	$inserted_urls = array(); // Used to prevent duplicates
	$special_urls = array();

	foreach ($selected_related_posts as $post) {
		if (property_exists($post, 'post_url') && $post->post_url) {
			$special_urls[$post->post_url] = true;
		}
	}

	$default_post_type = empty($selected_related_posts) ? 'none' : 'empty';

	$image_size = ($platform_options['theme_name'] == 'pinterest.css') ? 'full' : 'thumbnail';
	for ($i = 0; $i < $limit; $i++) {
		$related_post = zem_rp_get_next_post($related_posts, $selected_related_posts, $inserted_urls, $special_urls, $default_post_type);
		if (!$related_post) {
			break;
		}

		if (property_exists($related_post, 'type')) {
			$post_type = $related_post->type;
		} else {
			$post_type = $default_post_type;
		}

		if (in_array($post_type, array('empty', 'none'))) {
			$post_id = 'in-' . $related_post->ID;
		} else {
			$post_id = 'ex-' . $related_post->ID;
		}

		$data_attrs = 'data-position="' . $i . '" data-poid="' . $post_id . '" data-post-type="' . $post_type . '"';

		$output .= '<li ' . $data_attrs . '>';

		$post_url = property_exists($related_post, 'post_url') ? $related_post->post_url : get_permalink($related_post->ID);

		$img = zem_rp_get_post_thumbnail_img($related_post, $image_size);
		if ($img) {
			$output .=  '<a href="' . $post_url . '" class="zem_rp_thumbnail">' . $img . '</a>';
		}

		if ($platform_options["display_publish_date"]){
			$dateformat = get_option('date_format');
			$output .= mysql2date($dateformat, $related_post->post_date) . " -- ";
		}

		$output .= '<a href="' . $post_url . '" class="zem_rp_title">' . wptexturize($related_post->post_title) . '</a>';

		if ($platform_options["display_comment_count"] && property_exists($related_post, 'comment_count')){
			$output .=  " (" . $related_post->comment_count . ")";
		}

		if ($platform_options["display_excerpt"]){
			$excerpt_max_length = $platform_options["excerpt_max_length"];
			$excerpt = '';

			if ($related_post->post_excerpt){
				$excerpt = strip_shortcodes(strip_tags($related_post->post_excerpt));
			}
			if (!$excerpt) {
				$excerpt = strip_shortcodes(strip_tags($related_post->post_content));
			}

			if ($excerpt) {
				if (strlen($excerpt) > $excerpt_max_length) {
					$excerpt = mb_substr($excerpt, 0, $excerpt_max_length - 3) . '...';
				}
				$output .= '<br /><small>' . $excerpt . '</small>';
			}
		}
		$output .=  '</li>';
	}

	return $output;
}

function zem_rp_should_exclude() {
	global $wpdb, $post;

	if (!$post || !$post->ID) {
		return true;
	}

	$options = zem_rp_get_options();

	if(!$options['exclude_categories']) { return false; }

	$q = 'SELECT COUNT(tt.term_id) FROM '. $wpdb->term_taxonomy.' tt, ' . $wpdb->term_relationships.' tr WHERE tt.taxonomy = \'category\' AND tt.term_taxonomy_id = tr.term_taxonomy_id AND tr.object_id = '. $post->ID . ' AND tt.term_id IN (' . $options['exclude_categories'] . ')';

	$result = $wpdb->get_col($q);

	$count = (int) $result[0];

	return $count > 0;
}

function zem_rp_ajax_blogger_network_blacklist_callback() {
	check_ajax_referer('zem_rp_ajax_nonce');
	if (!current_user_can('delete_users')) {
		die();
	}

	$sourcefeed = (int) $_GET['sourcefeed'];

	$meta = zem_rp_get_meta();

	$blog_id = $meta['blog_id'];
	$auth_key = $meta['auth_key'];
	$req_options = array(
		'timeout' => 5
	);
	$url = ZEM_RP_CTR_DASHBOARD_URL . "blacklist/?blog_id=$blog_id&auth_key=$auth_key&sfid=$sourcefeed";
	$response = wp_remote_get($url, $req_options);

	if (wp_remote_retrieve_response_code($response) == 200) {
		$body = wp_remote_retrieve_body($response);
		if ($body) {
			$doc = json_decode($body);
			if ($doc && $doc->status === 'ok') {
				header('Content-Type: text/javascript');
				echo "if(window['_zem_rp_blacklist_callback$sourcefeed']) window._zem_rp_blacklist_callback$sourcefeed();";
			}
		}
	}
	die();
}

add_action('wp_ajax_rp_blogger_network_blacklist', 'zem_rp_ajax_blogger_network_blacklist_callback');

function zem_rp_head_resources() {
	global $post, $wpdb;

	if (zem_rp_should_exclude()) {
		return;
	}

	$meta = zem_rp_get_meta();
	$options = zem_rp_get_options();
	$platform_options = zem_rp_get_platform_options();
	$statistics_enabled = false;
	$remote_recommendations = false;
	$output = '';

	if (is_single()) {
		$statistics_enabled = $meta['blog_id'] && $meta['zemanta_username'];
		$remote_recommendations = $statistics_enabled && $meta['remote_recommendations'];
	}

	if ($statistics_enabled) {
		$tags = $wpdb->get_col("SELECT DISTINCT(label) FROM " . $wpdb->prefix . "zem_rp_tags WHERE post_id=$post->ID ORDER BY weight desc;", 0);
		if (!empty($tags)) {
			$post_tags = '[' . implode(', ', array_map(create_function('$v', 'return "\'" . urlencode(substr($v, strpos($v, \'_\') + 1)) . "\'";'), $tags)) . ']';
		} else {
			$post_tags = '[]';
		}

		$output .= "<script type=\"text/javascript\">\n" .
			"\twindow._zem_rp_blog_id = '" . esc_js($meta['blog_id']) . "';\n" .
			"\twindow._zem_rp_ajax_img_src_url = '" . esc_js(ZEM_RP_CTR_REPORT_URL) . "';\n" .
			"\twindow._zem_rp_post_id = '" . esc_js($post->ID) . "';\n" .
			"\twindow._zem_rp_thumbnails = " . ($platform_options['display_thumbnail'] ? 'true' : 'false') . ";\n" .
			"\twindow._zem_rp_post_title = '" . urlencode($post->post_title) . "';\n" .
			"\twindow._zem_rp_post_tags = {$post_tags};\n" .
			"\twindow._zem_rp_static_base_url = '" . esc_js(ZEM_RP_ZEMANTA_CONTENT_BASE_URL) . "';\n" .
			"\twindow._zem_rp_wp_ajax_url = '" . admin_url('admin-ajax.php') . "';\n" .
			"\twindow._zem_rp_plugin_version = '" . ZEM_RP_VERSION . "';\n" .
			"\twindow._zem_rp_num_rel_posts = '" . $options['max_related_posts'] . "';\n" .
			"\twindow._zem_rp_remote_recommendations = " . ($remote_recommendations ? 'true' : 'false') . ";\n" .
			(current_user_can('edit_posts') ?
				"\twindow._zem_rp_admin_ajax_url = '" . admin_url('admin-ajax.php') . "';\n" .
				"\twindow._zem_rp_plugin_static_base_url = '" . esc_js(plugins_url('static/' , __FILE__)) . "';\n" .
				"\twindow._zem_rp_ajax_nonce = '" . wp_create_nonce("zem_rp_ajax_nonce") . "';\n"
			: '') .
			"</script>\n";
	}

	if ($remote_recommendations) {
		$output .= '<script type="text/javascript" src="' . ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_RECOMMENDATIONS_JS_FILE . '?version=' . ZEM_RP_VERSION . '"></script>' . "\n";
		$output .= '<link rel="stylesheet" href="' . ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_RECOMMENDATIONS_CSS_FILE . '?version=' . ZEM_RP_VERSION . '" />' . "\n";
	}

	if($statistics_enabled) {
		$output .= '<script type="text/javascript" src="' . ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_CTR_PAGEVIEW_FILE . '?version=' . ZEM_RP_VERSION . '" async></script>' . "\n";
	}

	$theme_url = ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_THEMES_PATH;

	$output .= '<link rel="stylesheet" href="' . $theme_url . $platform_options['theme_name'] . '?version=' . ZEM_RP_VERSION . '" />' . "\n";
	if ($platform_options['custom_theme_enabled']) {
		$output .= '<style type="text/css">' . "\n" . $platform_options['theme_custom_css'] . "</style>\n";
	}

	if (current_user_can('edit_posts')) {
		wp_enqueue_style('zem_rp_edit_related_posts_css', ZEM_RP_ZEMANTA_CONTENT_BASE_URL . 'zem-css/edit_related_posts.css');
		wp_enqueue_script('zem_rp_edit_related_posts_js', ZEM_RP_ZEMANTA_CONTENT_BASE_URL . 'js/edit_related_posts.js', array('jquery'));
	}

	if($platform_options['theme_name'] === 'm-stream.css') {
		wp_enqueue_script('zem_rp_infiniterecs', ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_INFINITE_RECS_JS_FILE, array('jquery'));
	}

	if($platform_options['theme_name'] === 'pinterest.css') {
		wp_enqueue_script('zem_rp_pinterest', ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_PINTEREST_JS_FILE, array('jquery'));
	}

	echo $output;
}

function zem_rp_get_selected_posts() {
	global $post;

	$selected_related_posts = get_post_meta($post->ID, '_zem_rp_selected_related_posts');
	if (empty($selected_related_posts)) {
		return array();
	}

	$selected_related_posts = $selected_related_posts[0];
	if (empty($selected_related_posts)) {
		return array();
	}

	$options = zem_rp_get_options();
	$limit = $options['max_related_posts'];

	return array_slice((array)$selected_related_posts, 0, $limit);
}

global $zem_rp_is_first_widget;
$zem_rp_is_first_widget = true;
function zem_rp_get_related_posts() {
	if (zem_rp_should_exclude()) {
		return;
	}

	global $post, $zem_rp_is_first_widget;

	$options = zem_rp_get_options();
	$platform_options = zem_rp_get_platform_options();
	$meta = zem_rp_get_meta();

	$statistics_enabled = $meta['blog_id'] && $meta['zemanta_username'];
	$remote_recommendations = $statistics_enabled && is_single() && $meta['remote_recommendations'];

	$posts_and_title = zem_rp_fetch_posts_and_title();
	$related_posts = $posts_and_title['posts'];
	$title = $posts_and_title['title'];

	$selected_related_posts = zem_rp_get_selected_posts();

	$related_posts_content = "";

	if (!$related_posts) {
		return;
	}

	$posts_footer = '';
	if ($options['display_zemanta_linky']) {
		$posts_footer = '<div class="zem_rp_footer">' .
					'<a class="zem_rp_backlink" target="_blank" rel="nofollow" href="http://www.zemanta.com/?related-posts">Zemanta</a>'.
			'</div>';
	}

	$css_classes = 'related_post zem_rp';
	$css_classes_wrap = str_replace(array('.css', '-'), array('', '_'), esc_attr('zem_rp_th_' . $platform_options['theme_name']));

	if ($related_posts) {
		$related_posts_lis = zem_rp_generate_related_posts_list_items($related_posts, $selected_related_posts);
		$related_posts_ul = '<ul class="' . $css_classes . '" style="visibility: ' . ($remote_recommendations ? 'hidden' : 'visible') . '">' . $related_posts_lis . '</ul>';

		$related_posts_content = $title ? '<h3 class="related_post_title">' . $title . '</h3>' : '';
		$related_posts_content .= $related_posts_ul;
	}

	$first_id_attr = '';
	if ($zem_rp_is_first_widget) {
		$zem_rp_is_first_widget = false;
		$first_id_attr = 'id="zem_rp_first"';
	}

	$output = '<div class="zem_rp_wrap ' . $css_classes_wrap . '" ' . $first_id_attr . '>' .
				'<div class="zem_rp_content">' .
					$related_posts_content .
					$posts_footer .
				'</div>' .
				($remote_recommendations ? '<script type="text/javascript">window._zem_rp_callback_widget_exists ? window._zem_rp_callback_widget_exists() : false;</script>' : '') .
			'</div>';

	return "\n" . $output . "\n";
}

function zemanta_related_posts() {
	echo zem_rp_get_related_posts();
}
