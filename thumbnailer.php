<?php

function zem_rp_upload_default_thumbnail_file() {
	if (!empty($_FILES['zem_rp_default_thumbnail'])) {
		$file = $_FILES['zem_rp_default_thumbnail'];
		if(isset($file['error']) && $file['error'] === UPLOAD_ERR_NO_FILE) {
			return false;
		}

		$upload = wp_handle_upload($file, array('test_form' => false));
		if(isset($upload['error'])) {
			return new WP_Error('upload_error', $upload['error']);
		} else if(isset($upload['file'])) {
			$upload_dir = wp_upload_dir();

			if (function_exists('wp_get_image_editor')) { // WP 3.5+
				$image = wp_get_image_editor($upload['file']);

				$suffix = ZEM_RP_THUMBNAILS_WIDTH . 'x' . ZEM_RP_THUMBNAILS_HEIGHT;
				$resized_img_path = $image->generate_filename($suffix, $upload_dir['path'], 'jpg');

				$image->resize(ZEM_RP_THUMBNAILS_WIDTH, ZEM_RP_THUMBNAILS_HEIGHT, true);
				$image->save($resized_img_path, 'image/jpeg');

				return $upload_dir['url'] . '/' . urlencode(wp_basename($resized_img_path));
			} else {
				$path = image_resize($upload['file'], ZEM_RP_THUMBNAILS_WIDTH, ZEM_RP_THUMBNAILS_HEIGHT, true);
				if (!is_wp_error($path)) {
					return $upload_dir['url'] . '/' . wp_basename($path);
				} else if (array_key_exists('error_getting_dimensions', $path->errors)) {
					return $upload['url'];
				}
				return $path;
			}
		}
	}
	return false;
}

function zem_rp_get_default_thumbnail_url($seed = false, $size = 'thumbnail') {
	$options = zem_rp_get_options();
	$upload_dir = wp_upload_dir();

	if ($options['default_thumbnail_path']) {
		return $options['default_thumbnail_path'];
	} else {
		if ($seed) {
			$next_seed = rand();
			srand($seed);
		}
		$file = rand(0, ZEM_RP_THUMBNAILS_DEFAULTS_COUNT - 1) . '.jpg';
		if ($seed) {
			srand($next_seed);
		}
		return plugins_url('/static/thumbs/' . $file, __FILE__);
	}
}

function zem_rp_direct_filesystem_method() {
	return 'direct';
}

function zem_rp_save_and_resize_image($url, $upload_dir, $wp_filesystem) {
	$http_response = wp_remote_get($url, array('timeout' => 10));
	if(is_wp_error($http_response)) {
		return false;
	}
	$img_data = wp_remote_retrieve_body($http_response);

	$img_name = wp_unique_filename($upload_dir['path'], wp_basename(parse_url($url, PHP_URL_PATH)));
	$img_path = $upload_dir['path'] . '/' . $img_name;

	if(!$wp_filesystem->put_contents($img_path, $img_data, FS_CHMOD_FILE)) {
		return false;
	}

	if (function_exists('wp_get_image_editor')) { // WP 3.5+
		$image = wp_get_image_editor($img_path);

		$suffix = ZEM_RP_THUMBNAILS_WIDTH . 'x' . ZEM_RP_THUMBNAILS_HEIGHT;
		$resized_img_path = $image->generate_filename($suffix, $upload_dir['path'], 'jpg');

		$image->resize(ZEM_RP_THUMBNAILS_WIDTH, ZEM_RP_THUMBNAILS_HEIGHT, true);
		$image->save($resized_img_path, 'image/jpeg');
	} else {
		$resized_img_path = image_resize($img_path, ZEM_RP_THUMBNAILS_WIDTH, ZEM_RP_THUMBNAILS_HEIGHT, true);
		if (is_wp_error($resized_img_path) && array_key_exists('error_getting_dimensions', $resized_img_path->errors)) {
			$resized_img_path = $img_path;
		}
	}

	if(is_wp_error($resized_img_path)) {
		return false;
	}

	$thumbnail_img_url = $upload_dir['url'] . '/' . urlencode(wp_basename($resized_img_path));
	$full_img_url = $upload_dir['url'] . '/' . urlencode(wp_basename($img_path));

	return array(
			'thumbnail' => $thumbnail_img_url,
			'full' => $full_img_url
		);
}

function zem_rp_actually_extract_images_from_post_html($post, $upload_dir, $wp_filesystem) {
	$content = $post->post_content;
	preg_match_all('/<img (?:[^>]+ )?src="([^"]+)"/', $content, $matches);
	$urls = $matches[1];

	$imgs = false;

	if(count($urls) == 0) {
		return $imgs;
	}
	array_splice($urls, 10);

	foreach ($urls as $url) {
		$imgs = zem_rp_save_and_resize_image(html_entity_decode($url), $upload_dir, $wp_filesystem);
		if ($imgs) {
			break;
		}
	}

	return $imgs;
}

function zem_rp_cron_do_extract_images_from_post($post_id, $attachment_id) {
	$post_id = (int) $post_id;
	$attachment_id = (int) $attachment_id;
	$post = get_post($post_id);

	$upload_dir = wp_upload_dir();
	if($upload_dir['error'] !== false) {
		return false;
	}
	require_once(ABSPATH . 'wp-admin/includes/file.php');

	global $wp_filesystem;
	add_filter('filesystem_method', 'zem_rp_direct_filesystem_method');
	WP_Filesystem();

	if ($attachment_id) {
		$imgs = zem_rp_save_and_resize_image(wp_get_attachment_url($attachment_id), $upload_dir, $wp_filesystem);
	} else {
		$imgs = zem_rp_actually_extract_images_from_post_html($post, $upload_dir, $wp_filesystem);
	}

	remove_filter('filesystem_method', 'zem_rp_direct_filesystem_method');

	if($imgs) {
		update_post_meta($post_id, '_zem_rp_extracted_image_url', $imgs['thumbnail']);
		update_post_meta($post_id, '_zem_rp_extracted_image_url_full', $imgs['full']);
	}
}
add_action('zem_rp_cron_extract_images_from_post', 'zem_rp_cron_do_extract_images_from_post', 10, 2);

function zem_rp_extract_images_from_post($post, $attachment_id=null) {
	update_post_meta($post->ID, '_zem_rp_extracted_image_url', '');
	update_post_meta($post->ID, '_zem_rp_extracted_image_url_full', '');
	if(empty($post->post_content) && !$attachment_id) { return; }

	wp_schedule_single_event(time(), 'zem_rp_cron_extract_images_from_post', array($post->ID, $attachment_id));
}

function zem_rp_post_save_update_image($post_id) {
	$post = get_post($post_id);

	if(empty($post->post_content) || $post->post_status !== 'publish' || $post->post_type === 'page'  || $post->post_type === 'attachment' || $post->post_type === 'nav_menu_item') {
		return;
	}

	delete_post_meta($post->ID, '_zem_rp_extracted_image_url');
	delete_post_meta($post->ID, '_zem_rp_extracted_image_url_full');

	zem_rp_get_post_thumbnail_img($post);
}
add_action('save_post', 'zem_rp_post_save_update_image');

function zem_rp_get_img_tag($src, $alt) {
	return '<img src="'. esc_attr($src) . '" alt="' . esc_attr($alt) . '" />';
}

function zem_rp_check_image_size($size, $img_src) {
	if (is_array($size) && ($img_src[1] !== $size[0] || $img_src[2] !== $size[1])) {
		return false;
	}
	return true;
}

function zem_rp_get_attached_img_url($related_post, $size) {
	$image_id = null;

	if (has_post_thumbnail($related_post->ID)) {
		$image_id = get_post_thumbnail_id($related_post->ID);
	}

	if (!$image_id && function_exists('get_post_format_meta') && function_exists('img_html_to_post_id')) {
		// Image post format. Check wp-includes/media.php:get_the_post_format_image for the reference.
		$meta = get_post_format_meta($related_post->ID);
		if (!empty($meta['image'])) {
			if (is_numeric($meta['image'])) {
				$image_id = absint($meta['image']);
			} else {
				$image_id = img_html_to_post_id($meta['image']);
			}
		}
	}

	if ($image_id === null) {
		return null;
	}

	$img_src = wp_get_attachment_image_src($image_id, $size); //[0] => url, [1] => width, [2] => height

	if (!zem_rp_check_image_size($size, $img_src)) {
		zem_rp_extract_images_from_post($related_post, $image_id);
		return false;
	}

	return $img_src[0];
}

function zem_rp_get_post_thumbnail_img($related_post, $size = null, $force = false) {
	$options = zem_rp_get_options();
	$platform_options = zem_rp_get_platform_options();

	if (!$size || $size === 'thumbnail') {
		$size = array(ZEM_RP_THUMBNAILS_WIDTH, ZEM_RP_THUMBNAILS_HEIGHT);
	}

	if (!($platform_options["display_thumbnail"] || $force)) {
		return false;
	}

	$post_title = wptexturize($related_post->post_title);

	if (property_exists($related_post, 'thumbnail')) {
		return zem_rp_get_img_tag($related_post->thumbnail, $post_title);
	}

	if ($options['thumbnail_use_custom']) {
		$thumbnail_src = get_post_meta($related_post->ID, $options["thumbnail_custom_field"], true);

		if ($thumbnail_src) {
			return zem_rp_get_img_tag($thumbnail_src, $post_title);
		}
	}

	if($size == 'full') {
		$image_url = get_post_meta($related_post->ID, '_zem_rp_extracted_image_url_full', false);
	} else {
		$image_url = get_post_meta($related_post->ID, '_zem_rp_extracted_image_url', false);
	}

	if(!empty($image_url) && ($image_url[0] != '')) {
		return zem_rp_get_img_tag($image_url[0], $post_title);
	}

	$attached_img_url = zem_rp_get_attached_img_url($related_post, $size);
	if ($attached_img_url) {
		return zem_rp_get_img_tag($attached_img_url, $post_title);
	}

	if(empty($image_url) && $attached_img_url === null) {
		zem_rp_extract_images_from_post($related_post);
	}

	return zem_rp_get_img_tag(zem_rp_get_default_thumbnail_url($related_post->ID, $size), $post_title);
}

function zem_rp_process_latest_post_thumbnails() {
	$latest_posts = get_posts(array('numberposts' => ZEM_RP_THUMBNAILS_NUM_PREGENERATED_POSTS));
	foreach ($latest_posts as $post) {
		zem_rp_get_post_thumbnail_img($post);
	}
}
