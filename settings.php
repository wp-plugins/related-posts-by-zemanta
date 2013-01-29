<?php

/**
* Tooltips
**/

function zem_rp_display_tooltips() {
	$meta = zem_rp_get_meta();

	if ($meta['show_install_tooltip']) {
		$meta['show_install_tooltip'] = false;
		zem_rp_update_meta($meta);

		add_action('admin_enqueue_scripts', 'zem_rp_load_install_tooltip');
	}
}

function zem_rp_load_install_tooltip() {
    wp_enqueue_style('wp-pointer');
    wp_enqueue_script('wp-pointer');
    add_action('admin_print_footer_scripts', 'zem_rp_print_install_tooltip');
}

function zem_rp_print_install_tooltip() {
	$content = "<h3>Thanks for installing Related Posts by Zemanta!</h3><p>To experience the full power of Zemanta, go to settings and connect to Zemanta Dashboard!</p>";
	zem_rp_print_tooltip($content);
}

function zem_rp_print_tooltip($content) {
	?>
	<script type="text/javascript">
		jQuery(function ($) {
			var body = $(document.body),
				collapse = $('#collapse-menu'),
				target = $("#toplevel_page_zemanta-related-posts"),
				collapse_handler = function (e) {
					body.pointer('reposition');
				},
				options = {
					content: "<?php echo $content; ?>",
					position: {
						edge: 'left',
						align: 'center',
						of: target
					},
					open: function () {
						collapse.bind('click', collapse_handler);
					},
					close: function() {
						collapse.unbind('click', collapse_handler);
					}
				};

			if (target.length) {
				body.pointer(options).pointer('open');
			}
		});
	</script>
	<?php
}

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

	zem_rp_display_tooltips();
}

function zem_rp_settings_scripts() {
	wp_enqueue_script('zem_rp_themes_script', plugins_url('static/js/themes.js', __FILE__), array('jquery'));
	wp_enqueue_script("zem_rp_dashboard_script", plugins_url('static/js/dashboard.js', __FILE__), array('jquery') );
}
function zem_rp_settings_styles() {
	wp_enqueue_style("zem_rp_dashaboard_style", plugins_url("static/css/dashboard.css", __FILE__));
}

function zem_rp_register_blog() {
	$meta = zem_rp_get_meta();

	if($meta['blog_id']) return true;

	$req_options = array(
		'timeout' => 30
	);

	$response = wp_remote_get(ZEM_RP_CTR_DASHBOARD_URL . 'register/?blog_url=' . get_bloginfo('wpurl') . '&type=zem' .
			($meta['new_user'] ? '&new' : ''), $req_options);

	if (wp_remote_retrieve_response_code($response) == 200) {
		$body = wp_remote_retrieve_body($response);
		if ($body) {
			$doc = json_decode($body);

			if ($doc && $doc->status === 'ok') {
				$meta['blog_id'] = $doc->data->blog_id;
				$meta['auth_key'] = $doc->data->auth_key;
				$meta['new_user'] = false;
				zem_rp_update_meta($meta);

				return true;
			}
		}
	}

	return false;
}

function zem_rp_ajax_blogger_network_submit_callback() {
	$postdata = stripslashes_deep($_POST);

	$meta = zem_rp_get_meta();

	$meta['show_blogger_network_form'] = false;
	if(isset($postdata['join'])) {
		$meta['remote_recommendations'] = true;
	}
	else {
		$blog_id = $meta['blog_id'];
		$auth_key = $meta['auth_key'];
		$req_options = array(
			'timeout' => 5
		);
		$url = ZEM_RP_CTR_DASHBOARD_URL . "notifications/dismiss/?blog_id=$blog_id&auth_key=$auth_key&msg_id=blogger_network_form";
		$response = wp_remote_get($url, $req_options);
	}

	zem_rp_update_meta($meta);

	die('ok');
}
add_action('wp_ajax_blogger_network_submit', 'zem_rp_ajax_blogger_network_submit_callback');

function zem_rp_ajax_dismiss_notification_callback() {	
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
	$meta = zem_rp_get_meta();

	if(!$meta['blog_id']) die('no');

	$req_options = array(
		'timeout' => 30
	);
	$response = wp_remote_get(ZEM_RP_ZEMANTA_DASHBOARD_URL . '/get_username?blog_id=' . $meta['blog_id'] .
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
				$options['display_thumbnail'] = true;
				$options['ctr_dashboard_enabled'] = true;
				$options['promoted_content_enabled'] = true;
				$options['enable_themes'] = true;
				zem_rp_update_options($options);

				die('yes');
			}
		}
	}
	die('no');
}

add_action('wp_ajax_zem_rp_is_zemanta_connected', 'zem_rp_is_zemanta_connected');

function zem_rp_register_blog_and_login() {
	if(zem_rp_register_blog()) {
		$meta = zem_rp_get_meta();

		$latest_post_url = '';
		$latest_posts = get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'order' => 'DESC'));
		if (count($latest_posts > 0)) {
			$latest_post = $latest_posts[0];
			$latest_post_url = get_permalink($latest_post->ID);
		}

		wp_redirect(ZEM_RP_ZEMANTA_DASHBOARD_URL . '?blog_id=' . $meta['blog_id'] .
			'&auth_key=' . $meta['auth_key'] . '&rp_admin=' . urlencode(get_admin_url()) . '&rp_post=' . urlencode($latest_post_url)
			, 302);
		exit;
	} else {
		die('something went wrong, please reload this site');
	}
}

add_action('wp_ajax_zem_rp_register_blog_and_login', 'zem_rp_register_blog_and_login');

function zem_rp_ajax_hide_show_statistics() {
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
	$title_tags = array('h2', 'h3', 'h4', 'p', 'div');

	$options = zem_rp_get_options();
	$meta = zem_rp_get_meta();

	$postdata = stripslashes_deep($_POST);

	// load notifications every time user goes to settings page
	zem_rp_load_remote_notifications();

	if(sizeof($_POST))
	{
		$old_options = $options;
		$new_options = array(
			'on_single_post' => isset($postdata['zem_rp_on_single_post']),
			'display_comment_count' => isset($postdata['zem_rp_display_comment_count']),
			'display_publish_date' => isset($postdata['zem_rp_display_publish_date']),
			'display_excerpt' => isset($postdata['zem_rp_display_excerpt']),
			'excerpt_max_length' => (isset($postdata['zem_rp_excerpt_max_length']) && is_numeric(trim($postdata['zem_rp_excerpt_max_length']))) ? intval(trim($postdata['zem_rp_excerpt_max_length'])) : 200,
			'max_related_posts' => (isset($postdata['zem_rp_max_related_posts']) && is_numeric(trim($postdata['zem_rp_max_related_posts']))) ? intval(trim($postdata['zem_rp_max_related_posts'])) : 5,
			'on_rss' => isset($postdata['zem_rp_on_rss']),
			'display_thumbnail' => isset($postdata['zem_rp_display_thumbnail']),
			'thumbnail_custom_field' => isset($postdata['zem_rp_thumbnail_custom_field']) ? trim($postdata['zem_rp_thumbnail_custom_field']) : false,
			'thumbnail_display_title' => isset($postdata['zem_rp_thumbnail_display_title']),
			'related_posts_title' => isset($postdata['zem_rp_related_posts_title']) ? trim($postdata['zem_rp_related_posts_title']) : '',
			'related_posts_title_tag' => isset($postdata['zem_rp_related_posts_title_tag']) ? $postdata['zem_rp_related_posts_title_tag'] : 'h3',
			'thumbnail_use_attached' => isset($postdata['zem_rp_thumbnail_use_attached']),
			'thumbnail_use_custom' => isset($postdata['zem_rp_thumbnail_use_custom']) && $postdata['zem_rp_thumbnail_use_custom'] === 'yes',
			'enable_themes' => isset($postdata['zem_rp_enable_themes']),
			'custom_theme_enabled' => isset($postdata['zem_rp_custom_theme_enabled']),
			'from_around_the_web' => isset($postdata['zem_rp_from_around_the_web']),
			'max_related_post_age_in_days' => (isset($postdata['zem_rp_max_related_post_age_in_days']) && is_numeric(trim($postdata['zem_rp_max_related_post_age_in_days']))) ? intval(trim($postdata['zem_rp_max_related_post_age_in_days'])) : 0
		);

		if(!isset($postdata['zem_rp_exclude_categories'])) {
			$new_options['exclude_categories'] = '';
		} else if(is_array($postdata['zem_rp_exclude_categories'])) {
			$new_options['exclude_categories'] = join(',', $postdata['zem_rp_exclude_categories']);
		} else {
			$new_options['exclude_categories'] = trim($postdata['zem_rp_exclude_categories']);
		}

		$preprocess_thumbnails = $new_options['display_thumbnail'] && $new_options['thumbnail_use_attached'] && (!$old_options['display_thumbnail'] || !$old_options['thumbnail_use_attached']);

		if(isset($postdata['zem_rp_theme_name'])) {		// If this isn't set, maybe the AJAX didn't load...
			$new_options['theme_name'] = trim($postdata['zem_rp_theme_name']);

			if(isset($postdata['zem_rp_theme_custom_css'])) {
				$new_options['theme_custom_css'] = $postdata['zem_rp_theme_custom_css'];
			} else {
				$new_options['theme_custom_css'] = '';
			}
		} else {
			$new_options['theme_name'] = $old_options['theme_name'];
			$new_options['theme_custom_css'] = $old_options['theme_custom_css'];
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

		$new_options['ctr_dashboard_enabled'] = $old_options['ctr_dashboard_enabled'];
		$new_options['promoted_content_enabled'] = $old_options['promoted_content_enabled'];
		$new_options['traffic_exchange_enabled'] = $old_options['traffic_exchange_enabled'];

		if (((array) $old_options) != $new_options) {
			if(!zem_rp_update_options($new_options)) {
				zem_rp_add_admin_notice('error', __('Failed to save settings.', 'zemanta_related_posts'));
			} else {
				zem_rp_add_admin_notice('updated', __('Settings saved.', 'zemanta_related_posts'));
			}

			if($preprocess_thumbnails) {
				zem_rp_process_latest_post_thumbnails();
			}
		} else {
			// I should duplicate success message here
			zem_rp_add_admin_notice('updated', __('Settings saved.', 'zemanta_related_posts'));
		}
	}
?>

	<div class="wrap" id="zem_rp_wrap">
	<?php
		$related_posts_title_tag = $options['related_posts_title_tag'];
		$theme_name = $options['theme_name'];
		$theme_custom_css = $options['theme_custom_css'];

		include(dirname(__FILE__) . '/static/settings.js.php');
	?>

		<input type="hidden" id="zem_rp_json_url" value="<?php esc_attr_e(ZEM_RP_ZEMANTA_CONTENT_BASE_URL . ZEM_RP_STATIC_JSON_PATH); ?>" />
		<input type="hidden" id="zem_rp_version" value="<?php esc_attr_e(ZEM_RP_VERSION); ?>" />
		<input type="hidden" id="zem_rp_theme_selected" value="<?php esc_attr_e($theme_name); ?>" />
		<input type="hidden" id="zem_rp_dashboard_url" value="<?php esc_attr_e(ZEM_RP_CTR_DASHBOARD_URL); ?>" />
		<input type="hidden" id="zem_rp_static_base_url" value="<?php esc_attr_e(ZEM_RP_ZEMANTA_CONTENT_BASE_URL); ?>" />

		<?php if ($meta['blog_id']):?>
		<input type="hidden" id="zem_rp_blog_id" value="<?php esc_attr_e($meta['blog_id']); ?>" />
		<input type="hidden" id="zem_rp_auth_key" value="<?php esc_attr_e($meta['auth_key']); ?>" />
		<?php endif; ?>

		<?php if($options['ctr_dashboard_enabled']): ?>
		<input type="hidden" id="zem_rp_ctr_dashboard_enabled" value="true" />
		<?php endif; ?>

		<?php if($meta['show_traffic_exchange'] && $options['traffic_exchange_enabled']): ?>
		<input type="hidden" id="zem_rp_show_traffic_exchange_statistics" value="1" />
		<?php endif; ?>

		<div class="header">
			<div class="support">
				<h4><?php _e("Awesome support", 'zemanta_related_posts'); ?></h4>
				<p>
					<?php _e("If you have any questions please contact us at",'zemanta_related_posts');?> <a target="_blank" href="mailto:support+2013@zemanta.com"><?php _e("support", 'zemanta_related_posts');?></a>.
				</p>
			</div>
			<h2 class="title"><?php _e("Related Posts by Zemanta",'zemanta_related_posts');?></h2>
		</div>

		<?php zem_rp_print_notifications(); ?>

	<?php if($meta['zemanta_username'] === false): ?>

	<div id="zem_rp_login_div">
		<p>We are almost ready. All you need to do is connect to our powerful servers. </p>
		<a id="zem_rp_login" href="<?php echo get_admin_url(null, 'admin-ajax.php') . '?action=zem_rp_register_blog_and_login'; ?>" target="_blank">Connect</a>
	</div>

	<script type="text/javascript">
jQuery(function($) {
	var interval;

	var check_if_connected = function() {
		jQuery.post(ajaxurl, { action: 'zem_rp_is_zemanta_connected'}, function(data) {
			if(data === 'yes') {
				clearInterval(interval);
				window.location.reload();
			}
		});
	}

	$('#zem_rp_login').click(function() {
		interval = setInterval(check_if_connected, 4000);	// 4 seconds
		setTimeout(check_if_connected, 300);
	});
});
	</script>

	<?php else: ?>

		<?php if ($meta['show_blogger_network_form'] and $meta['blog_id']): ?>
		<form action="https://docs.google.com/a/zemanta.com/spreadsheet/formResponse?formkey=dDEyTlhraEd0dnRwVVFMX19LRW8wbWc6MQ&amp;ifq" method="POST" class="zem_rp_message_form" id="zem_rp_blogger_network_form" target="zem_rp_blogger_network_hidden_iframe">
			<input type="hidden" name="pageNumber" value="0" />
			<input type="hidden" name="backupCache" />
			<input type="hidden" name="entry.2.single" value="<?php echo get_bloginfo('wpurl'); ?>" />
			<input type="hidden" name="entry.3.single" value="<?php echo $meta['blog_id']; ?>" />
			<a href="#" class="dismiss"><img width="12" src="<?php echo plugins_url("static/img/close.png", __FILE__); ?>" /></a>
			<h2>Blogger networks</h2>
			<p>Easily link out to similar bloggers to exchange traffic with them. One click out, one click in.</p>
			<table class="form-table"><tbody>
				<tr valign="top">
					<th scope="row"><label for="zem_rp_blogger_network_kind">I want to exchange traffic with</label></th>
					<td width="1%">
						<select name="entry.0.group" id="zem_rp_blogger_network_kind">
							<option value="Automotive" />Automotive bloggers</option>
							<option value="Beauty &amp; Style" />Beauty &amp; Style bloggers</option>
							<option value="Business" />Business bloggers</option>
							<option value="Consumer Tech" />Consumer Tech bloggers</option>
							<option value="Enterprise Tech" />Enterprise Tech bloggers</option>
							<option value="Entertainment" />Entertainment bloggers</option>
							<option value="Family &amp; Parenting" />Family &amp; Parenting bloggers</option>
							<option value="Food &amp; Drink" />Food &amp; Drink bloggers</option>
							<option value="Graphic Arts" />Graphic Arts bloggers</option>
							<option value="Healthy Living" />Healthy Living bloggers</option>
							<option value="Home &amp; Shelter" />Home &amp; Shelter bloggers</option>
							<option value="Lifestyle &amp; Hobby" />Lifestyle &amp; Hobby bloggers</option>
							<option value="Men's Lifestyle" />Men's Lifestyle bloggers</option>
							<option value="Personal Finance" />Personal Finance bloggers</option>
							<option value="Women's Lifestyle" />Women's Lifestyle bloggers</option>
						</select>
					</td>
					<td rowspan="2" valign="middle"><div id="zem_rp_blogger_network_thankyou" class="thankyou"><img src="<?php echo plugins_url("static/img/check.png", __FILE__); ?>" width="30" height="22" />Thanks for showing interest.</div></td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="zem_rp_blogger_network_email">My email is:</label></th>
					<td><input type="email" name="entry.1.single" value="" id="zem_rp_blogger_network_email" required="required" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"></th>
					<td><input type="submit" name="submit" value="Submit" class="submit" id="zem_rp_blogger_network_submit" /></td>
			</tbody></table>
			<script type="text/javascript">
jQuery(function($) {
	var submit = $('#zem_rp_blogger_network_submit');
	$('#zem_rp_blogger_network_form')
		.submit(function(event) {
			submit.addClass('disabled');
			setTimeout(function() { submit.attr('disabled', true); }, 0);
			$('#zem_rp_blogger_network_hidden_iframe').load(function() {
				submit.attr('disabled', false).removeClass('disabled');
				$('#zem_rp_blogger_network_thankyou').fadeIn('slow');
				$.post(ajaxurl, {action: 'blogger_network_submit', 'join': true});
			});
		})
		.find('a.dismiss').click(function () {
			$.post(ajaxurl, {action: 'blogger_network_submit'});
			$('#zem_rp_blogger_network_form').slideUp();
		});
});
			</script>
		</form>
		<iframe id="zem_rp_blogger_network_hidden_iframe" name="zem_rp_blogger_network_hidden_iframe" style="display: none"></iframe>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" action="" id="zem_rp_settings_form">
			<?php if ($options['ctr_dashboard_enabled']): ?>
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
			<?php endif; ?>


			<div id="zem_rp_dashboard"><a href="<?php echo ZEM_RP_ZEMANTA_DASHBOARD_URL; ?>" target="_blank">Open dashboard</a></div>

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
					<table class="form-table">
						<tr id="zem_rp_theme_options_wrap">
							<th scope="row">Select Theme:</th>
							<td>
								<label>
									<input name="zem_rp_enable_themes" type="checkbox" id="zem_rp_enable_themes" value="yes"<?php checked($options["enable_themes"]); ?> />
									<?php _e("Enable Themes",'zemanta_related_posts'); ?>
								</label>
								<div id="zem_rp_theme_area" style="display: none;">
									<div class="theme-list"></div>
									<div class="theme-screenshot"></div>
									<div class="theme-extra-options">
										<label>
											<input type="checkbox" id="zem_rp_custom_theme_enabled" name="zem_rp_custom_theme_enabled" value="yes"<?php checked($options['custom_theme_enabled']); ?> />
											Customize
										</label>
									</div>
								</div>
							</td>
						</tr>
						<tr id="zem_rp_theme_custom_css_wrap" style="display: none; ">
							<th scope="row"></th>
							<td>
								<p class="notice">To override default rules please add "!important" after every declaration.</p>
								<textarea style="width: 300px; height: 215px;" name="zem_rp_theme_custom_css" class="custom-css"><?php echo htmlspecialchars($theme_custom_css, ENT_QUOTES); ?></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php _e("Thumbnail Options:",'zemanta_related_posts'); ?></th>
							<td>
								<label>
									<input name="zem_rp_display_thumbnail" type="checkbox" id="zem_rp_display_thumbnail" value="yes"<?php checked($options["display_thumbnail"]); ?> onclick="zem_rp_display_thumbnail_onclick();" />
									<?php _e("Display Thumbnails For Related Posts",'zemanta_related_posts');?>
								</label>
								<br />
								<span id="zem_rp_thumbnail_span" style="<?php echo $options["display_thumbnail"] ? '' : 'display:none;'; ?>">
								<label>
									<input name="zem_rp_thumbnail_display_title" type="checkbox" id="zem_rp_thumbnail_display_title" value="yes"<?php checked($options["thumbnail_display_title"]); ?> />
									<?php _e('Display Post Titles', 'zemanta_related_posts');?>
								</label>
								<br />

								<?php
								global $wpdb;

								$custom_fields = $wpdb->get_col( "SELECT meta_key FROM $wpdb->postmeta GROUP BY meta_key HAVING meta_key NOT LIKE '\_%' ORDER BY LOWER(meta_key)" );

								if($custom_fields) :
								?>
								<label><input name="zem_rp_thumbnail_use_custom" type="radio" value="no" <?php checked(!$options['thumbnail_use_custom']); ?>> Use featured image</label>&nbsp;&nbsp;&nbsp;&nbsp;
								<label><input name="zem_rp_thumbnail_use_custom" type="radio" value="yes" <?php checked($options['thumbnail_use_custom']); ?>> Use custom field</label>

								<select name="zem_rp_thumbnail_custom_field" id="zem_rp_thumbnail_custom_field"  class="postform">
								
								<?php foreach ( $custom_fields as $custom_field ) : ?>
									<option value="<?php esc_attr_e($custom_field); ?>"<?php selected($options["thumbnail_custom_field"], $custom_field); ?>><?php esc_html_e($custom_field);?></option>
								<?php endforeach; ?>

								</select>
								<br />
								<?php endif; ?>

								<label>
									<input name="zem_rp_thumbnail_use_attached" type="checkbox" value="yes" <?php checked($options["thumbnail_use_attached"]); ?>>
									<?php _e("If featured image is missing, show an image from the post",'zemanta_related_posts');?>
								</label>
								<br />


								<br />
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
								</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php _e("Display Options:",'zemanta_related_posts'); ?></th>
							<td>
								<label>
									<input name="zem_rp_display_comment_count" type="checkbox" id="zem_rp_display_comment_count" value="yes" <?php checked($options["display_comment_count"]); ?>>
									<?php _e("Display Number of Comments",'zemanta_related_posts');?>
								</label><br />
								<label>
									<input name="zem_rp_display_publish_date" type="checkbox" id="zem_rp_display_publish_date" value="yes" <?php checked($options["display_publish_date"]); ?>>
									<?php _e("Display Publish Date",'zemanta_related_posts');?>
								</label><br />
								<label>
									<input name="zem_rp_display_excerpt" type="checkbox" id="zem_rp_display_excerpt" value="yes"<?php checked($options["display_excerpt"]); ?> onclick="zem_rp_display_excerpt_onclick();" >
									<?php _e("Display Post Excerpt",'zemanta_related_posts');?>
								</label>
								<label id="zem_rp_excerpt_max_length_label"<?php echo $options["display_excerpt"] ? '' : ' style="display: none;"'; ?>>
									<input name="zem_rp_excerpt_max_length" type="text" id="zem_rp_excerpt_max_length" class="small-text" value="<?php esc_attr_e($options["excerpt_max_length"]); ?>" /> <span class="description"><?php _e('Maximum Number of Characters.', 'zemanta_related_posts'); ?></span>
								</label><br/>
								<label for="zem_rp_related_posts_title_tag">
									<?php _e('Related Posts Title Tag', 'zemanta_related_posts'); ?>
									<select name="zem_rp_related_posts_title_tag" id="zem_rp_related_posts_title_tag" class="postform">
									<?php
									foreach ($title_tags as $tag) :
									?>
										<option value="<?php esc_attr_e($tag); ?>"<?php selected($related_posts_title_tag, $tag); ?>>&lt;<?php esc_html_e($tag); ?>&gt;</option>
									<?php endforeach; ?>
									</select>
								</label>
							</td>
						</tr>
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
								<label>
									<input name="zem_rp_on_single_post" type="checkbox" id="zem_rp_on_single_post" value="yes" <?php checked($options['on_single_post']); ?>>
									<?php _e("Auto Insert Related Posts",'zemanta_related_posts');?>
								</label>
								(or add <pre style="display: inline">&lt;?php zemanta_related_posts()?&gt;</pre> to your single post template)
								<br />
								<label>
									<input name="zem_rp_on_rss" type="checkbox" id="zem_rp_on_rss" value="yes"<?php checked($options['on_rss']); ?>>
									<?php _e("Display Related Posts in Feed",'zemanta_related_posts');?>
								</label>
								<br />
								<label>
									<input name="zem_rp_from_around_the_web" type="checkbox" id="zem_rp_from_around_the_web" value="yes"<?php checked($options['from_around_the_web']); ?>>
									<?php _e("Enable Related Posts from around the Web",'zemanta_related_posts');?>
								</label>
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
