<?php

add_action('add_meta_boxes', 'zem_rp_add_meta_box');
add_action('save_post', 'zem_rp_update_zem_related_posts');

function zem_rp_get_zemanta_api_key() {
	$meta = zem_rp_get_meta();

	if ($meta['zemanta_api_key']) {
		return $meta['zemanta_api_key'];
	}

	$arguments = array(
		'format' => 'xml',
		'method' => 'zemanta.auth.create_user'
	);

	$response = wp_remote_post('http://api.zemanta.com/services/rest/0.0/', array('method' => 'POST', 'body' => $arguments));

	if (!is_wp_error($response)) {
		preg_match('/<status>(.+?)<\/status>/', $response['body'], $matches);

		if ($matches[1] == 'ok') {
			preg_match('/<apikey>(.+?)<\/apikey>/', $response['body'], $matches);

			$meta['zemanta_api_key'] = $matches[1];
			zem_rp_update_meta($meta);
		}
	}

	return $meta['zemanta_api_key'];
}

function zem_rp_add_meta_box() {
	$options = zem_rp_get_options();

	if (!$options['from_around_the_web']) {
		return;
	}

	wp_enqueue_script("zem_rp_zem_related_posts_script", ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_ZEM_RELATED_POSTS_JS_FILE, array('jquery') );
	wp_enqueue_style("zem_rp_zem_related_posts_style", ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_ZEM_RELATED_POSTS_CSS_FILE);
	add_meta_box('zem_rp_zem_related_posts_box', 'Related Posts From Around the Web', 'zem_rp_zem_related_posts_box', 'post', 'normal', 'high');
}

function zem_rp_zem_related_posts_box() {
	global $post;

	$options = zem_rp_get_options();

	$plugin_url = plugins_url('/static/', __FILE__);

	$zemanta_api_key = zem_rp_get_zemanta_api_key();

	$posts_json = '';
	$zem_related_posts = get_post_meta($post->ID, '_zem_rp_zem_related_posts');
	if (!empty($zem_related_posts)) {
		$zem_related_posts = $zem_related_posts[0];
		if (!empty($zem_related_posts)) {
			$posts_json = esc_attr(json_encode($zem_related_posts));
		}
	}

	echo '<div plugin_static_url="' . esc_attr($plugin_url) . '" max_articles="' . $options['max_related_posts'] . '" id="zem_rp_zem_related_posts_wrap"></div>';
	echo '<input type="hidden" name="zem_rp_zem_related_posts_input" id="zem_rp_zem_related_posts_input" value="' . $posts_json . '" />';
	echo '<input type="hidden" name="zem_rp_zemanta_api_key" id="zem_rp_zemanta_api_key" value="' . $zemanta_api_key . '" />';
}

function zem_rp_update_zem_related_posts($post_id) {
	$options = zem_rp_get_options();

	if (!$options['from_around_the_web']) {
		return;
	}

	if (!isset($_POST['zem_rp_zem_related_posts_input'])) {
		return;
	}

	global $wpdb;

	$articles_json = stripslashes($_POST['zem_rp_zem_related_posts_input']);
	if ($articles_json) {
		$articles = json_decode($articles_json);
	} else {
		$articles = '';
	}

	update_post_meta($post_id, '_zem_rp_zem_related_posts', $articles);
}
