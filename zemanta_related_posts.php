<?php
/*
Plugin Name: Related Posts by Zemanta
Version: 1.0
Plugin URI: http://wordpress.org/extend/plugins/zemanta-related-posts/
Description: Quickly increase your readers' engagement with your posts by adding Related Posts in the footer of your content.
Author: Zemanta Ltd.
Author URI: http://www.zemanta.com/
*/

define('ZEM_RP_VERSION', '1.0');

include_once(dirname(__FILE__) . '/config.php');
include_once(dirname(__FILE__) . '/lib/stemmer.php');

include_once(dirname(__FILE__) . '/admin_notices.php');
include_once(dirname(__FILE__) . '/notifications.php');
include_once(dirname(__FILE__) . '/widget.php');
include_once(dirname(__FILE__) . '/thumbnailer.php');
include_once(dirname(__FILE__) . '/settings.php');
include_once(dirname(__FILE__) . '/recommendations.php');
include_once(dirname(__FILE__) . '/dashboard_widget.php');
include_once(dirname(__FILE__) . '/compatibility.php');
include_once(dirname(__FILE__) . '/related_posts_widget.php');

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
add_filter('the_content', 'zem_rp_add_related_posts_hook', 99);

function zem_rp_append_posts(&$related_posts, $fetch_function_name) {
	$options = zem_rp_get_options();

	$limit = $options['max_related_posts'];

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

	zem_rp_append_posts($related_posts, 'zem_rp_fetch_related_posts_v2');
	zem_rp_append_posts($related_posts, 'zem_rp_fetch_related_posts');
	zem_rp_append_posts($related_posts, 'zem_rp_fetch_random_posts');

	if(function_exists('qtrans_postsFilter')) {
		$related_posts = qtrans_postsFilter($related_posts);
	}

	return array(
		"posts" => $related_posts,
		"title" => $title
	);
}

function zem_rp_generate_related_posts_list_items($related_posts) {
	$options = zem_rp_get_options();
	$output = "";
	$i = 0;

	$statistics_enabled = $options['ctr_dashboard_enabled'];

	foreach ($related_posts as $related_post ) {
		$data_attrs = '';
		$css_class = '';
		$rel = '';
		if ($statistics_enabled) {
			$data_attrs .= 'data-position="' . $i++ . '" data-poid="in-' . $related_post->ID . '" ';
		}
		if (property_exists($related_post, 'picked')) {
			if ($related_post->picked) {
				$css_class = 'zem_picked';
			} else {
				$rel = 'rel="nofollow" ';
			}
		}

		$output .= '<li ' . $data_attrs . ' class="' . $css_class . '">';

		$post_url = property_exists($related_post, 'post_url') ? $related_post->post_url : get_permalink($related_post->ID);
		$img = zem_rp_get_post_thumbnail_img($related_post);
		if ($img) {
			$output .=  '<a ' . $rel . 'href="' . $post_url . '" class="zem_rp_thumbnail">' . $img . '</a>';
		}

		if (!$options["display_thumbnail"] || ($options["display_thumbnail"] && ($options["thumbnail_display_title"] || !$img))) {
			if ($options["display_publish_date"]){
				$dateformat = get_option('date_format');
				$output .= mysql2date($dateformat, $related_post->post_date) . " -- ";
			}

			$output .= '<a href="' . $post_url . '" ' . $rel . 'class="zem_rp_title">' . wptexturize($related_post->post_title) . '</a>';

			if ($options["display_comment_count"] && property_exists($related_post, 'comment_count')){
				$output .=  " (" . $related_post->comment_count . ")";
			}

			if ($options["display_excerpt"]){
				$excerpt_max_length = $options["excerpt_max_length"];
				if($related_post->post_excerpt){
					$output .= '<br /><small>' . (mb_substr(strip_shortcodes(strip_tags($related_post->post_excerpt)), 0, $excerpt_max_length)) . '...</small>';
				} else {
					$output .= '<br /><small>' . (mb_substr(strip_shortcodes(strip_tags($related_post->post_content)), 0, $excerpt_max_length)) . '...</small>';
				}
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

	if ($post->post_type !== 'post') {
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
				header_remove();
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
	$statistics_enabled = false;
	$remote_recommendations = false;
	$output = '';

	if (is_single()) {
		$statistics_enabled = $options['ctr_dashboard_enabled'] && $meta['blog_id'] && $meta['auth_key'];
		$remote_recommendations = $meta['remote_recommendations'] && $statistics_enabled;
	}

	if ($statistics_enabled) {
		$tags = $wpdb->get_col("SELECT label FROM " . $wpdb->prefix . "zem_rp_tags WHERE post_id=$post->ID ORDER BY weight desc;", 0);
		if (!empty($tags)) {
			$post_tags = '[' . implode(', ', array_map(create_function('$v', 'return "\'" . urlencode(substr($v, strpos($v, \'_\') + 1)) . "\'";'), $tags)) . ']';
		} else {
			$post_tags = '[]';
		}

		$output .= "<script type=\"text/javascript\">\n" .
			"\twindow._zem_rp_blog_id = '" . esc_js($meta['blog_id']) . "';\n" .
			"\twindow._zem_rp_ajax_img_src_url = '" . esc_js(ZEM_RP_CTR_REPORT_URL) . "';\n" .
			"\twindow._zem_rp_post_id = '" . esc_js($post->ID) . "';\n" .
			"\twindow._zem_rp_thumbnails = " . ($options['display_thumbnail'] ? 'true' : 'false') . ";\n" .
			"\twindow._zem_rp_post_title = '" . urlencode($post->post_title) . "';\n" .
			"\twindow._zem_rp_post_tags = {$post_tags};\n" .
			"\twindow._zem_rp_static_base_url = '" . esc_js(ZEM_RP_ZEMANTA_CONTENT_BASE_URL) . "';\n" .
			"\twindow._zem_rp_promoted_content = " . ($options['promoted_content_enabled'] ? 'true' : 'false') . ";\n" .
			"\twindow._zem_rp_plugin_version = '" . ZEM_RP_VERSION . "';\n" .
			"\twindow._zem_rp_traffic_exchange = " . ($options['traffic_exchange_enabled'] ? 'true' : 'false') . ";\n" .
			(current_user_can('delete_users') ? "\twindow._zem_rp_admin_ajax_url = '" . admin_url('admin-ajax.php') . "';\n" : '') .
			"</script>\n";
	}

	if ($remote_recommendations) {
		$output .= '<script type="text/javascript" src="' . ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_RECOMMENDATIONS_JS_FILE . '?version=' . ZEM_RP_VERSION . '"></script>' . "\n";
		$output .= '<link rel="stylesheet" href="' . ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_RECOMMENDATIONS_CSS_FILE . '?version=' . ZEM_RP_VERSION . '" />' . "\n";
	}

	if($statistics_enabled) {
		$output .= '<script type="text/javascript" src="' . ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_CTR_PAGEVIEW_FILE . '?version=' . ZEM_RP_VERSION . '" async></script>' . "\n";
	}

	if ($options['enable_themes']) {
		if ($options["display_thumbnail"]) {
			$theme_url = ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_THEMES_THUMBS_PATH;
		} else {
			$theme_url = ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_THEMES_PATH;
		}

		$output .= '<link rel="stylesheet" href="' . $theme_url . $options['theme_name'] . '?version=' . ZEM_RP_VERSION . '" />' . "\n";
		if ($options['custom_theme_enabled']) {
			$output .= '<style type="text/css">' . "\n" . $options['theme_custom_css'] . "</style>\n";
		}
	}

	echo $output;
}

function zem_rp_get_zemanta_posts() {
	global $post;

	$zem_related_posts = get_post_meta($post->ID, '_zem_rp_zem_related_posts');
	if (empty($zem_related_posts)) {
		return false;
	}

	$zem_related_posts = $zem_related_posts[0];
	if (empty($zem_related_posts)) {
		return false;
	}

	$options = zem_rp_get_options();
	$limit = $options['max_related_posts'];

	return array_slice((array)$zem_related_posts, 0, $limit);
}

function zem_rp_get_related_posts() {
	if (zem_rp_should_exclude()) {
		return;
	}

	global $post;

	$options = zem_rp_get_options();
	$meta = zem_rp_get_meta();

	$statistics_enabled = $options['ctr_dashboard_enabled'] && $meta['blog_id'] && $meta['auth_key'];
	$remote_recommendations = is_single() && $meta['remote_recommendations'] && $statistics_enabled;

	$posts_and_title = zem_rp_fetch_posts_and_title();
	$related_posts = $posts_and_title['posts'];
	$zem_related_posts = $options['from_around_the_web'] ? zem_rp_get_zemanta_posts() : false;

	$related_posts_content = "";
	$title = $posts_and_title['title'];
	$zemanta_posts_content = "";
	$zemanta_title = "From Around the Web";

	$posts_footer = '<div class="zem_rp_footer">' .
			(current_user_can('delete_users') && $options['from_around_the_web']
				? '<a class="zem_rp_edit" href="' . get_edit_post_link($post->ID) .'#zem_rp_zem_related_posts_box">Edit Related Posts</a>'
				: '<a class="zem_rp_backlink" target="_blank" rel="nofollow" href="http://www.zemanta.com/?related-posts">Zemanta</a>'
			).
		'</div>';


	if (!$related_posts && !$zem_related_posts) {
		return;
	}

	$css_classes = 'related_post zem_rp';
	if (!$zem_related_posts) {
		$css_classes .= ' zem_web';
	}

	$css_classes_wrap = '';
	if ($options['enable_themes']) {
		$css_classes_wrap .= ' ' . str_replace(array('.css', '-'), array('', '_'), esc_attr('zem_rp_th_' . $options['theme_name']));
	}

	if ($related_posts) {
		$related_posts_lis = zem_rp_generate_related_posts_list_items($related_posts);
		$related_posts_ul = '<ul class="' . $css_classes . ' zem_int" style="visibility: ' . ($remote_recommendations && !$zem_related_posts ? 'hidden' : 'visible') . '">' . $related_posts_lis . '</ul>';

		$related_posts_content = $title ? '<h3 class="related_post_title">' . $title . '</h3>' : '';
		$related_posts_content .= $related_posts_ul;
	}

	if ($zem_related_posts) {
		$zemanta_posts_lis = zem_rp_generate_related_posts_list_items($zem_related_posts);
		$zemanta_posts_ul = '<ul class="' . $css_classes . ' zem_web" style="visibility: ' . ($remote_recommendations ? 'hidden' : 'visible') . '">' . $zemanta_posts_lis . '</ul>';

		$zemanta_posts_content = $zemanta_title ? '<h3 class="related_post_title">' . $zemanta_title . '</h3>' : '';
		$zemanta_posts_content .= $zemanta_posts_ul;
	}


	$output = '<div class="zem_rp_wrap ' . $css_classes_wrap . '">' .
				'<div class="zem_rp_content">' .
					$related_posts_content .
					$zemanta_posts_content .
					$posts_footer .
				'</div>' .
				($remote_recommendations ? '<script type="text/javascript">window._zem_rp_callback_widget_exists && window._zem_rp_callback_widget_exists();</script>' : '') .
			'</div>';

	return "\n" . $output . "\n";
}
