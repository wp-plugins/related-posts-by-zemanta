<?php
//
// Dashboard widget
//

add_action('wp_dashboard_setup', 'zem_rp_dashboard_setup');

function zem_rp_dashboard_setup() {
	if (!current_user_can('delete_users')) {
		return;
	}

	$options = zem_rp_get_options();
	$meta = zem_rp_get_meta();

	if ($meta['blog_id'] && $meta['auth_key']) {
		wp_add_dashboard_widget('zem_rp_dashboard_widget', 'Related Posts by Zemanta', 'zem_rp_display_dashboard_widget');
		add_action('admin_enqueue_scripts', 'zem_rp_dashboard_scripts');
	}
}

function zem_rp_display_dashboard_widget() {
	$options = zem_rp_get_options();
	$meta = zem_rp_get_meta();
?>
	<input type="hidden" id="zem_rp_dashboard_url" value="<?php esc_attr_e(ZEM_RP_CTR_DASHBOARD_URL); ?>" />
	<input type="hidden" id="zem_rp_static_base_url" value="<?php esc_attr_e(ZEM_RP_ZEMANTA_CONTENT_BASE_URL); ?>" />
	<input type="hidden" id="zem_rp_blog_id" value="<?php esc_attr_e($meta['blog_id']); ?>" />
	<input type="hidden" id="zem_rp_zemanta_username" value="<?php esc_attr_e($meta['zemanta_username']); ?>" />
	<input type="hidden" id="zem_rp_auth_key" value="<?php esc_attr_e($meta['auth_key']); ?>" />
	<?php if($meta['show_traffic_exchange']): ?>
	<input type="hidden" id="zem_rp_show_traffic_exchange_statistics" value="1" />
	<?php endif; ?>

	<div id="zem_rp_wrap" class="zem_rp_dashboard">
		<?php zem_rp_print_notifications(); ?>
		<div id="zem_rp_statistics_wrap"></div>
	</div>
<?php
}

function zem_rp_dashboard_scripts($hook) {
	if($hook === 'index.php') {
		wp_enqueue_script('zem_rp_dashboard_script', plugins_url('static/js/dashboard.js', __FILE__), array('jquery'));
		wp_enqueue_style('zem_rp_dashaboard_style', plugins_url('static/css/dashboard.css', __FILE__));
	}
}
