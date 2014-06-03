<?php

add_action('zem_rp_admin_notices', 'zem_rp_display_admin_notices');

// Show connect notice on dashboard and plugins pages
add_action( 'load-index.php', 'zem_rp_prepare_admin_connect_notice' );
add_action( 'load-plugins.php', 'zem_rp_prepare_admin_connect_notice' );

function zem_rp_add_admin_notice($type = 'updated', $message = '') {
	global $zem_rp_admin_notices;
	
	if (strtolower($type) == 'updated' && $message != '') {
		$zem_rp_admin_notices[] = array('updated', $message);
		return true;
	}
	
	if (strtolower($type) == 'error' && $message != '') {
		$zem_rp_admin_notices[] = array ('error', $message);
		return true;
	}
	
	return false;
}

function zem_rp_display_admin_notices() {
	global $zem_rp_admin_notices;

	foreach ((array) $zem_rp_admin_notices as $notice) {
		echo '<div id="message" class="' . $notice[0] . ' below-h2"><p>' . $notice[1] . '</p></div>';
	}
}

function zem_rp_prepare_admin_connect_notice() {
	$meta = zem_rp_get_meta();

	if (!$meta['zemanta_username']) {
		wp_register_style( 'zem_rp_connect_style', plugins_url('static/css/connect.css', __FILE__) );
		add_action( 'admin_notices', 'zem_rp_admin_connect_notice' );
	}
}

function zem_rp_admin_connect_notice() {
	if (!current_user_can('delete_users')) {
		return;
	}
	wp_enqueue_style( 'zem_rp_connect_style' );
	wp_enqueue_script( 'zem_rp_connect_js' );

	$register_url = get_admin_url(null, 'admin-ajax.php') . '?action=zem_rp_register_blog_and_login';
	$zem_admin_url = admin_url('admin.php?page=zemanta-related-posts#turn-on-rp');

	?>
	<div id="zem-rp-message" class="updated zem-rp-connect">
		<div id="zem-rp-dismiss">
			<a id="zem-rp-close-button"></a>
		</div>
		<div id="zem-rp-wrap-container">
			<div id="zem-rp-connect-wrap">
				<a id="zem-rp-login" target="_blank" href="<?php echo $register_url; ?>"><?php _e('Connect','zemanta_related_posts'); ?></a>
			</div>
			<div id="zem-rp-text-container">
				<h4><?php _e('Related Posts by Zemanta are almost ready,','zemanta_related_posts');?></h4>
				<h4><?php _e('now all you need to do is connect to our service.','zemanta_related_posts'); ?></h4>
			</div>
		</div>
		<div id="zem-rp-bottom-container">
			<p><?php _e('By turning on Related Posts you agree to ','zemanta_related_posts'); ?><a href="http://www.zemanta.com/blog/about/related-posts-terms-of-service/" target="_blank"><?php _e('terms of service.','zemanta_related_posts');?></a></p>
			<p><?php _e('You\'ll get Advanced Settings, Themes, Thumbnails and Analytics Dashboard. These features are provided by ','zemanta_related_posts');?><a target="_blank" href="http://www.zemanta.com">Zemanta</a> <?php _e('as a service.','zemanta_related_posts'); ?></p>
		</div>
	</div>
	<script type="text/javascript">
		jQuery(function ($) {
			$('#zem-rp-login').click(function () {
				setTimeout(function () {
					window.location.href = "<?php echo $zem_admin_url; ?>";
				}, 1);
			})
		});
	</script>
	<?php
}

