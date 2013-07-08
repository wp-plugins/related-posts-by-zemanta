<?php

/**
* Add settings link to installed plugins list
**/
function zem_rp_add_link_to_settings($links) {
	return array_merge( array(
		'<a href="' . admin_url('admin.php?page=zemanta-related-posts') . '">' . __('Settings', 'zemanta_related_posts') . '</a>',
	), $links);
}
add_filter('plugin_action_links_' . ZEM_RP_PLUGIN_FILE, 'zem_rp_add_link_to_settings', 10, 2);

/**
* Place menu icons at admin head
**/
add_action('admin_head', 'zem_rp_admin_head');
function zem_rp_admin_head() {
	$menu_icon = plugins_url('static/img/menu_icon.png', __FILE__);
	$menu_icon_retina = plugins_url('static/img/menu_icon_2x.png', __FILE__);
?>
<style type="text/css">
#toplevel_page_zemanta-related-posts .wp-menu-image {
	background: url('<?php echo $menu_icon; ?>') 7px 6px no-repeat;
}
@media only screen and (-webkit-min-device-pixel-ratio: 1.5) {
	#toplevel_page_zemanta-related-posts .wp-menu-image {
		background-image: url('<?php echo $menu_icon_retina; ?>');
		background-size: 16px 17px;
	}
}
</style>
<?php
}


/**
* Settings
**/

add_action('admin_menu', 'zem_rp_settings_admin_menu');

function zem_rp_settings_admin_menu() {
	if (!current_user_can('delete_users')) {
		return;
	}

	$title = __('Zemanta', 'zemanta_related_posts');
	$count = zem_rp_number_of_available_notifications();

	if($count) {
		$title .= ' <span class="update-plugins count-' . $count . '"><span class="plugin-count">' . $count . '</span></span>';
	}
	
	$page = add_menu_page(__('Related Posts by Zemanta', 'zemanta_related_posts'), $title, 
						'manage_options', 'zemanta-related-posts', 'zem_rp_settings_page', 'div');

	add_action('admin_print_styles-' . $page, 'zem_rp_settings_styles');
	add_action('admin_print_scripts-' . $page, 'zem_rp_settings_scripts');
}

function zem_rp_settings_scripts() {
	wp_enqueue_script('zem_rp_themes_script', plugins_url('static/js/themes.js', __FILE__), array('jquery'), ZEM_RP_VERSION);
	wp_enqueue_script("zem_rp_dashboard_script", plugins_url('static/js/dashboard.js', __FILE__), array('jquery'), ZEM_RP_VERSION);
}
function zem_rp_settings_styles() {
	wp_enqueue_style("zem_rp_dashaboard_style", plugins_url("static/css/dashboard.css", __FILE__), array(), ZEM_RP_VERSION);
}

function zem_rp_register_blog() {
	$meta = zem_rp_get_meta();

	if($meta['blog_id']) return true;

	$req_options = array(
		'timeout' => 30
	);

	$register_path = 'register/?blog_url=' . urlencode(get_bloginfo('wpurl')) . '&type=zem' . ($meta['new_user'] ? '&new' : '');

	$response = wp_remote_get(ZEM_RP_CTR_DASHBOARD_URL . $register_path, $req_options);

	if (wp_remote_retrieve_response_code($response) == 200) {
		$body = wp_remote_retrieve_body($response);
		if ($body) {
			$doc = json_decode($body);
			if ($doc) {
				if ($doc->status === 'ok') {
					$meta['blog_id'] = $doc->data->blog_id;
					$meta['auth_key'] = $doc->data->auth_key;
					$meta['new_user'] = false;
					zem_rp_update_meta($meta);
					return true;
				} else {
					return "Invalid status: " . $doc->status . ' Request: ' . $register_path;
				}
			} else {
				return "Empty doc. Request: " . $register_path;
			}
		} else {
			return "Empty response body. Request: " . $register_path;
		}
	} else {
		return $response->get_error_message() . "<br />Request: " . ZEM_RP_CTR_DASHBOARD_URL . $register_path;
	}

	return false;
}

function zem_rp_ajax_dismiss_notification_callback() {
	check_ajax_referer('zem_rp_ajax_nonce');

	if(isset($_REQUEST['id'])) {
		zem_rp_dismiss_notification((int)$_REQUEST['id']);
	}
	if(isset($_REQUEST['noredirect'])) {
		die('ok');
	}
	wp_redirect(admin_url('admin.php?page=wordpress-related-posts'));
}

add_action('wp_ajax_rp_dismiss_notification', 'zem_rp_ajax_dismiss_notification_callback');

function zem_rp_is_zemanta_connected() {
	check_ajax_referer('zem_rp_ajax_nonce');

	$meta = zem_rp_get_meta();

	if(!$meta['blog_id']) die('no');

	$req_options = array(
		'timeout' => 30
	);
	$response = wp_remote_get(ZEM_RP_ZEMANTA_DASHBOARD_URL . 'get_username?blog_id=' . $meta['blog_id'] .
			'&auth_key=' . $meta['auth_key'], $req_options);

	if (wp_remote_retrieve_response_code($response) == 200) {
		$body = wp_remote_retrieve_body($response);
		if ($body) {
			$doc = json_decode($body);

			if($doc && $doc->username) {
				$meta['zemanta_username'] = $doc->username;
				$meta['show_statistics'] = true;
				zem_rp_update_meta($meta);

				$options = zem_rp_get_options();
				$options['mobile']['display_thumbnail'] = true;
				$options['desktop']['display_thumbnail'] = true;
				zem_rp_update_options($options);

				die('yes');
			}
		}
	}
	die('no');
}

add_action('wp_ajax_zem_rp_is_zemanta_connected', 'zem_rp_is_zemanta_connected');

function zem_rp_register_blog_and_login() {
	$register_blog_response = zem_rp_register_blog();
	if ($register_blog_response === true) {
		$meta = zem_rp_get_meta();

		$latest_post_url = '';
		$latest_posts = get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'order' => 'DESC'));
		if (count($latest_posts > 0)) {
			$latest_post = $latest_posts[0];
			$latest_post_url = get_permalink($latest_post->ID) . '#zem_rp_first';
		}

		wp_redirect(ZEM_RP_ZEMANTA_DASHBOARD_URL . 'connect/?blog_id=' . $meta['blog_id'] .
			'&auth_key=' . $meta['auth_key'] . '&rp_admin=' . urlencode(get_admin_url()) . '&rp_post=' . urlencode($latest_post_url)
			, 302);
		exit;
	} else {
		wp_remote_get('http://content.zemanta.com/static/stats.gif?error=register_blog&data=' . urlencode($register_blog_response));
		die('Something went wrong, please reload this site. Error: ' . $register_blog_response);
	}
}

add_action('wp_ajax_zem_rp_register_blog_and_login', 'zem_rp_register_blog_and_login');

function zem_rp_ajax_hide_show_statistics() {
	check_ajax_referer('zem_rp_ajax_nonce');

	$meta = zem_rp_get_meta();
	$postdata = stripslashes_deep($_POST);

	if(isset($postdata['show'])) {
		$meta['show_statistics'] = true;
	}
	if(isset($postdata['hide'])) {
		$meta['show_statistics'] = false;
	}

	zem_rp_update_meta($meta);

	die('ok');
}

add_action('wp_ajax_rp_show_hide_statistics', 'zem_rp_ajax_hide_show_statistics');

function zem_rp_settings_page() {
	if (!current_user_can('delete_users')) {
		die('Sorry, you don\'t have permissions to access this page.');
	}

	$options = zem_rp_get_options();
	$meta = zem_rp_get_meta();

	$postdata = stripslashes_deep($_POST);

	// load notifications every time user goes to settings page
	zem_rp_load_remote_notifications();

	if(sizeof($_POST)) {
		if (!isset($_POST['_zem_rp_nonce']) || !wp_verify_nonce($_POST['_zem_rp_nonce'], 'zem_rp_settings') ) {
			die('Sorry, your nonce did not verify.');
		}

		$old_options = $options;
		$new_options = array(
			'on_single_post' => isset($postdata['zem_rp_on_single_post']),
			'max_related_posts' => (isset($postdata['zem_rp_max_related_posts']) && is_numeric(trim($postdata['zem_rp_max_related_posts']))) ? intval(trim($postdata['zem_rp_max_related_posts'])) : 5,
			'on_rss' => isset($postdata['zem_rp_on_rss']),
			'related_posts_title' => isset($postdata['zem_rp_related_posts_title']) ? trim($postdata['zem_rp_related_posts_title']) : '',
			'max_related_post_age_in_days' => (isset($postdata['zem_rp_max_related_post_age_in_days']) && is_numeric(trim($postdata['zem_rp_max_related_post_age_in_days']))) ? intval(trim($postdata['zem_rp_max_related_post_age_in_days'])) : 0,

			'thumbnail_use_custom' => isset($postdata['zem_rp_thumbnail_use_custom']),
			'thumbnail_custom_field' => isset($postdata['zem_rp_thumbnail_custom_field']) ? trim($postdata['zem_rp_thumbnail_custom_field']) : '',
			'display_zemanta_linky' => isset($postdata['zem_rp_display_zemanta_linky']),

			'mobile' => array(
				'display_thumbnail' => isset($postdata['zem_rp_mobile_display_thumbnail']),
				'display_comment_count' => isset($postdata['zem_rp_mobile_display_comment_count']),
				'display_publish_date' => isset($postdata['zem_rp_mobile_display_publish_date']),
				'display_excerpt' => isset($postdata['zem_rp_mobile_display_excerpt']),
				'excerpt_max_length' => (isset($postdata['zem_rp_mobile_excerpt_max_length']) && is_numeric(trim($postdata['zem_rp_mobile_excerpt_max_length']))) ? intval(trim($postdata['zem_rp_mobile_excerpt_max_length'])) : 200,
				'custom_theme_enabled' => isset($postdata['zem_rp_mobile_custom_theme_enabled'])
			),
			'desktop' => array(
				'display_thumbnail' => isset($postdata['zem_rp_desktop_display_thumbnail']),
				'display_comment_count' => isset($postdata['zem_rp_desktop_display_comment_count']),
				'display_publish_date' => isset($postdata['zem_rp_desktop_display_publish_date']),
				'display_excerpt' => isset($postdata['zem_rp_desktop_display_excerpt']),
				'excerpt_max_length' => (isset($postdata['zem_rp_desktop_excerpt_max_length']) && is_numeric(trim($postdata['zem_rp_desktop_excerpt_max_length']))) ? intval(trim($postdata['zem_rp_desktop_excerpt_max_length'])) : 200,
				'custom_theme_enabled' => isset($postdata['zem_rp_desktop_custom_theme_enabled'])
			)
		);

		if(!isset($postdata['zem_rp_exclude_categories'])) {
			$new_options['exclude_categories'] = '';
		} else if(is_array($postdata['zem_rp_exclude_categories'])) {
			$new_options['exclude_categories'] = join(',', $postdata['zem_rp_exclude_categories']);
		} else {
			$new_options['exclude_categories'] = trim($postdata['zem_rp_exclude_categories']);
		}

		foreach (array('mobile', 'desktop') as $platform) {
			if(isset($postdata['zem_rp_' . $platform . '_theme_name'])) {		// If this isn't set, maybe the AJAX didn't load...
				$new_options[$platform]['theme_name'] = trim($postdata['zem_rp_' . $platform . '_theme_name']);

				if(isset($postdata['zem_rp_' . $platform . '_theme_custom_css'])) {
					$new_options[$platform]['theme_custom_css'] = $postdata['zem_rp_' . $platform . '_theme_custom_css'];
				} else {
					$new_options[$platform]['theme_custom_css'] = '';
				}
			} else {
				$new_options[$platform]['theme_name'] = $old_options[$platform]['theme_name'];
				$new_options[$platform]['theme_custom_css'] = $old_options[$platform]['theme_custom_css'];
			}
		}

		$default_thumbnail_path = zem_rp_upload_default_thumbnail_file();

		if($default_thumbnail_path === false) { // no file uploaded
			if(isset($postdata['zem_rp_default_thumbnail_remove'])) {
				$new_options['default_thumbnail_path'] = false;
			} else {
				$new_options['default_thumbnail_path'] = $old_options['default_thumbnail_path'];
			}
		} else if(is_wp_error($default_thumbnail_path)) { // error while upload
			$new_options['default_thumbnail_path'] = $old_options['default_thumbnail_path'];
			zem_rp_add_admin_notice('error', $default_thumbnail_path->get_error_message());
		} else { // file successfully uploaded
			$new_options['default_thumbnail_path'] = $default_thumbnail_path;
		}

		if (((array) $old_options) != $new_options) {
			if(!zem_rp_update_options($new_options)) {
				zem_rp_add_admin_notice('error', __('Failed to save settings.', 'zemanta_related_posts'));
			} else {
				zem_rp_add_admin_notice('updated', __('Settings saved.', 'zemanta_related_posts'));
			}
		} else {
			// I should duplicate success message here
			zem_rp_add_admin_notice('updated', __('Settings saved.', 'zemanta_related_posts'));
		}
	}
?>

	<div class="wrap" id="zem_rp_wrap">
		<input type="hidden" id="zem_rp_ajax_nonce" value="<?php echo wp_create_nonce("zem_rp_ajax_nonce"); ?>" />

		<input type="hidden" id="zem_rp_json_url" value="<?php esc_attr_e(ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_JSON_PATH); ?>" />
		<input type="hidden" id="zem_rp_version" value="<?php esc_attr_e(ZEM_RP_VERSION); ?>" />
		<input type="hidden" id="zem_rp_dashboard_url" value="<?php esc_attr_e(ZEM_RP_CTR_DASHBOARD_URL); ?>" />
		<input type="hidden" id="zem_rp_static_base_url" value="<?php esc_attr_e(ZEM_RP_ZEMANTA_CONTENT_BASE_URL); ?>" />

		<?php if ($meta['blog_id']):?>
		<input type="hidden" id="zem_rp_blog_id" value="<?php esc_attr_e($meta['blog_id']); ?>" />
		<input type="hidden" id="zem_rp_auth_key" value="<?php esc_attr_e($meta['auth_key']); ?>" />
		<input type="hidden" id="zem_rp_zemanta_username" value="<?php esc_attr_e($meta['zemanta_username']); ?>" />
		<?php endif; ?>

		<?php if($meta['show_traffic_exchange']): ?>
		<input type="hidden" id="zem_rp_show_traffic_exchange_statistics" value="1" />
		<?php endif; ?>

		<div class="header">
			<div class="support">
				<h4><?php _e("Awesome support", 'zemanta_related_posts'); ?></h4>
				<p>
					<?php _e("If you have any questions please contact us at",'zemanta_related_posts');?> <a target="_blank" href="mailto:support+relatedposts@zemanta.com"><?php _e("support", 'zemanta_related_posts');?></a>.
				</p>
			</div>
			<h2 class="title"><?php _e("Related Posts by ",'zemanta_related_posts');?><a href="http://www.zemanta.com">Zemanta</a></h2>
		</div>

		<?php zem_rp_print_notifications(); ?>

	<?php
	if(!$meta['zemanta_username']):
	/*
		Plugin assumes each site can be connected only to one user and doesn't display connect button to already connected users.
		To resolve the issue of multiple users per site we'll have to display connect button to everyone.
	*/
	?>

	<div id="zem_rp_login_div">
		<div id="zem-rp-message" class="zem-rp-connect">
			<div id="zem-rp-dismiss">
				<a id="zem-rp-close-button"></a>
			</div>
			<div id="zem-rp-wrap-container">
				<div id="zem-rp-connect-wrap">
					<a id="zem-rp-login" href="<?php echo get_admin_url(null, 'admin-ajax.php') . '?action=zem_rp_register_blog_and_login'; ?>" target="_blank">Connect</a>
				</div>
				<div id="zem-rp-text-container">
					<h4>Related Posts by Zemanta are almost ready,</h4>
					<h4>now all you need to do is connect to our service.</h4>
				</div>
			</div>
			<div id="zem-rp-bottom-container">
				<p>By turning on Related Posts you agree to <a href="http://www.zemanta.com/rp-tos" target="_blank">terms of service.</a></p>
				<p>You'll get Advanced Settings, Themes, Thumbnails and Analytics Dashboard. These features are provided by <a target="_blank" href="http://www.zemanta.com">Zemanta</a> as a service.</p>
			</div>
		</div>
		<img src="<?php echo plugins_url("static/img/connectimg.jpg", __FILE__); ?>" />
	</div>

	<?php else: ?>

		<form method="post" enctype="multipart/form-data" action="" id="zem_rp_settings_form">
			<?php wp_nonce_field('zem_rp_settings', '_zem_rp_nonce') ?>

			<div id="zem_rp_statistics_holder">
				<div id="zem_rp_statistics_collapsible" block="statistics" class="collapsible<?php if(!$meta['show_statistics']) { echo " collapsed"; } ?>">
					<a href="#" class="collapse-handle">Collapse</a>
					<h2><?php _e('Statistics', 'zemanta_related_posts'); ?></h2>
					<div class="container" <?php if(!$meta['show_statistics']) { echo ' style="display: none;" '; } ?>>
						<div id="zem_rp_statistics_wrap">
							<div class="message unavailable"><?php _e("Statistics currently unavailable",'zemanta_related_posts'); ?></div>
						</div>
					</div>
				</div>
			</div>

			<div id="zem_rp_dashboard"><a href="<?php echo ZEM_RP_ZEMANTA_DASHBOARD_URL . 'open/?blog_id=' . $meta['blog_id']; ?>" target="_blank">Open dashboard</a></div>

			<div>
				<h2><?php _e("Settings",'zemanta_related_posts');?></h2>

				<?php do_action('zem_rp_admin_notices'); ?>

				<div class="container">
					<h3><?php _e("Basic Settings",'zemanta_related_posts');?></h3>

					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e('Related Posts Title:', 'zemanta_related_posts'); ?></th>
							<td>
							  <input name="zem_rp_related_posts_title" type="text" id="zem_rp_related_posts_title" value="<?php esc_attr_e($options['related_posts_title']); ?>" class="regular-text" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Number of Posts:', 'zemanta_related_posts');?></th>
							<td>
							  <input name="zem_rp_max_related_posts" type="number" step="1" id="zem_rp_max_related_posts" class="small-text" min="1" value="<?php esc_attr_e($options['max_related_posts']); ?>" />
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"></th>
							<td><label>
								<?php _e('Only show posts from the last', 'zemanta_related_posts');?>&nbsp;
								<select name="zem_rp_max_related_post_age_in_days" id="zem_rp_max_related_post_age_in_days">
									<option value="0" <?php selected($options['max_related_post_age_in_days'], 0); ?>>Unlimited</option>
									<option value="30" <?php selected($options['max_related_post_age_in_days'], 30); ?>>1</option>
									<option value="91" <?php selected($options['max_related_post_age_in_days'], 91); ?>>3</option>
									<option value="365" <?php selected($options['max_related_post_age_in_days'], 365); ?>>12</option>
								</select> &nbsp;months.
							</label></td>
						</tr>
					</table>

					<h3>Theme Settings</h3>
					<div id="zem_rp_theme_options_wrap">
						<?php foreach (array('desktop', 'mobile') as $platform): ?>
						<?php $titles = array('desktop' => 'Desktop/Tablet', 'mobile' => 'Mobile Phones'); ?>
						<input type="hidden" id="zem_rp_<?php echo $platform; ?>_theme_selected" value="<?php esc_attr_e($options[$platform]['theme_name']); ?>" />
						<table class="form-table zem_rp_settings_table">
							<tr id="zem_rp_<?php echo $platform; ?>_theme_options_wrap">
								<td>
									<h4><?php _e($titles[$platform], 'zemanta_related_posts'); ?></h4>
									<div id="zem_rp_<?php echo $platform; ?>_theme_area" style="display: none;">
										<div class="theme-list"></div>
										<div class="theme-screenshot"></div>
										<div class="theme-extra-options">
											<label class="zem_rp_settings_button">
												<input type="checkbox" id="zem_rp_<?php echo $platform; ?>_custom_theme_enabled" name="zem_rp_<?php echo $platform; ?>_custom_theme_enabled" value="yes"<?php checked($options[$platform]['custom_theme_enabled']); ?> />
												Customize
											</label>
										</div>
									</div>
								</td>
							</tr>
							<tr id="zem_rp_<?php echo $platform; ?>_theme_custom_css_wrap" style="display: none; ">
								<td>
									<label>
										<input name="zem_rp_<?php echo $platform; ?>_display_thumbnail" type="checkbox" id="zem_rp_<?php echo $platform; ?>_display_thumbnail" value="yes"<?php checked($options[$platform]["display_thumbnail"]); ?> onclick="zem_rp_display_thumbnail_onclick();" />
										<?php _e("Display Thumbnails For Related Posts",'zemanta_related_posts');?>
									</label><br />
									<label>
										<input name="zem_rp_<?php echo $platform; ?>_display_comment_count" type="checkbox" id="zem_rp_<?php echo $platform; ?>_display_comment_count" value="yes" <?php checked($options[$platform]["display_comment_count"]); ?>>
										<?php _e("Display Number of Comments",'zemanta_related_posts');?>
									</label><br />
									<label>
										<input name="zem_rp_<?php echo $platform; ?>_display_publish_date" type="checkbox" id="zem_rp_<?php echo $platform; ?>_display_publish_date" value="yes" <?php checked($options[$platform]["display_publish_date"]); ?>>
										<?php _e("Display Publish Date",'zemanta_related_posts');?>
									</label><br />
									<label>
										<input name="zem_rp_<?php echo $platform; ?>_display_excerpt" type="checkbox" id="zem_rp_<?php echo $platform; ?>_display_excerpt" value="yes"<?php checked($options[$platform]["display_excerpt"]); ?> onclick="zem_rp_display_excerpt_onclick();" >
										<?php _e("Display Post Excerpt",'zemanta_related_posts');?>
									</label>
									<label id="zem_rp_<?php echo $platform; ?>_excerpt_max_length_label">
										<input name="zem_rp_<?php echo $platform; ?>_excerpt_max_length" type="text" id="zem_rp_<?php echo $platform; ?>_excerpt_max_length" class="small-text" value="<?php esc_attr_e($options[$platform]["excerpt_max_length"]); ?>" /> <span class="description"><?php _e('Maximum Number of Characters.', 'zemanta_related_posts'); ?></span>
									</label>
									<br/>
									<h4>Custom CSS</h4>
									<textarea style="width: 300px; height: 215px; background: #EEE;" name="zem_rp_<?php echo $platform; ?>_theme_custom_css" class="custom-css"><?php echo htmlspecialchars($options[$platform]['theme_custom_css'], ENT_QUOTES); ?></textarea>
								</td>
							</tr>
							<tr>
								<td>
									
								</td>
							</tr>
						</table>
						<?php endforeach; ?>
					</div>

					<table class="form-table">
						<tbody>
							<tr valign="top">
								<td>
									<label>
										<?php _e('For posts without images, a default image will be shown.<br/>
										You can upload your own default image here','zemanta_related_posts');?>
										<input type="file" name="zem_rp_default_thumbnail" />
									</label>
									<?php if($options['default_thumbnail_path']) : ?>
										<span style="display: inline-block; vertical-align: top; *display: inline; zoom: 1;">
											<img style="padding: 3px; border: 1px solid #DFDFDF; border-radius: 3px;" valign="top" width="80" height="80" src="<?php esc_attr_e(zem_rp_get_default_thumbnail_url()); ?>" alt="selected thumbnail" />
											<br />
											<label>
												<input type="checkbox" name="zem_rp_default_thumbnail_remove" value="yes" />
												<?php _e("Remove selected",'zemanta_related_posts');?>
											</label>
										</span>
									<?php endif; ?>


									<?php
									global $wpdb;

									$custom_fields = $wpdb->get_col( "SELECT meta_key FROM $wpdb->postmeta GROUP BY meta_key HAVING meta_key NOT LIKE '\_%' ORDER BY LOWER(meta_key)" );
									if($custom_fields) :
									?>
									<br />
									<br />
									<label><input name="zem_rp_thumbnail_use_custom" type="checkbox" value="yes" <?php checked($options['thumbnail_use_custom']); ?>> Use custom field for thumbnails</label>
									<select name="zem_rp_thumbnail_custom_field" id="zem_rp_thumbnail_custom_field"  class="postform">
									<?php foreach ( $custom_fields as $custom_field ) : ?>
										<option value="<?php esc_attr_e($custom_field); ?>"<?php selected($options["thumbnail_custom_field"], $custom_field); ?>><?php esc_html_e($custom_field);?></option>
									<?php endforeach; ?>
									</select>
									<br />
									<?php endif; ?>
								</td>
							</tr>
						</tbody>
					</table>

					<h3><?php _e("Other Settings:",'zemanta_related_posts'); ?></h3>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php _e('Exclude these Categories:', 'zemanta_related_posts'); ?></th>
							<td>
								<div class="excluded-categories">
									<?php
									$exclude = explode(',', $options['exclude_categories']);
									$args = array(
										'orderby' => 'name',
										'order' => 'ASC',
										'hide_empty' => false
										);

									foreach (get_categories($args) as $category) :
									?>
									<label>
										<input name="zem_rp_exclude_categories[]" type="checkbox" id="zem_rp_exclude_categories" value="<?php esc_attr_e($category->cat_ID); ?>"<?php checked(in_array($category->cat_ID, $exclude)); ?> />
										<?php esc_html_e($category->cat_name); ?>
										<br />
									</label>
									<?php endforeach; ?>
								</div>
							</td>
						</tr>
						<tr valign="top">
							<td colspan="2">

								<br />
								<label>
									<input name="zem_rp_on_single_post" type="checkbox" id="zem_rp_on_single_post" value="yes" <?php checked($options['on_single_post']); ?>>
									<?php _e("Auto Insert Related Posts",'zemanta_related_posts');?>
								</label>
								(or add <pre style="display: inline">&lt;?php zemanta_related_posts()?&gt;</pre> to your single post template)
								<br />
								<label>
									<input name="zem_rp_display_zemanta_linky" type="checkbox" id="zem_rp_display_zemanta_linky" value="yes" <?php checked($options['display_zemanta_linky']); ?> />
									<?php _e("Support us (show our logo)",'wp_related_posts');?>
								</label>
								<br />
								<label>
									<input name="zem_rp_on_rss" type="checkbox" id="zem_rp_on_rss" value="yes"<?php checked($options['on_rss']); ?>>
									<?php _e("Display Related Posts in Feed",'zemanta_related_posts');?>
								</label>
								<br />
							</td>
						</tr>
					</table>
					<p class="submit"><input type="submit" value="<?php _e('Save changes', 'zemanta_related_posts'); ?>" class="button-primary" /></p>

				</form>
	<?php endif; ?>
			</div>
		</div>
	</div>
<?php }
