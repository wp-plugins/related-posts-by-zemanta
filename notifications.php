<?php
//
// Notifications system
//

add_action('zem_rp_load_notifications', 'zem_rp_load_remote_notifications');

function zem_rp_dismiss_notification($id) {
	$meta = zem_rp_get_meta();
	$messages_ref =& $meta['remote_notifications'];

	if(array_key_exists($id, $messages_ref)) {
		unset($messages_ref[$id]);
		zem_rp_update_meta($meta);

		$blog_id = $meta['blog_id'];
		$auth_key = $meta['auth_key'];
		$req_options = array(
			'timeout' => 5
		);
		$url = ZEM_RP_CTR_DASHBOARD_URL . "notifications/dismiss/?blog_id=$blog_id&auth_key=$auth_key&msg_id=$id";
		$response = wp_remote_get($url, $req_options);

		return true;
	}
	return false;
}

function zem_rp_number_of_available_notifications() {
	$meta = zem_rp_get_meta();
	
	return sizeof($meta['remote_notifications']);
}

function zem_rp_print_notifications() {
	$meta = zem_rp_get_meta();
	$messages = $meta['remote_notifications'];

	foreach($messages as $id => $text) {
		echo '<div class="zem_rp_notification">
			<a href="' . admin_url('admin-ajax.php?action=rp_dismiss_notification&id=' . $id . '&_wpnonce=' . wp_create_nonce("zem_rp_ajax_nonce")) . '" class="close">x</a>
			<p>' . $text . '</p>
		</div>';
	}
}

function zem_rp_schedule_notifications_cron() {
	if(!wp_next_scheduled('zem_rp_load_notifications')) {
		wp_schedule_event(time(), 'hourly', 'zem_rp_load_notifications');
	}
}

function zem_rp_unschedule_notifications_cron() {
	wp_clear_scheduled_hook('zem_rp_load_notifications');
}

// Notifications cron job hourly callback
function zem_rp_load_remote_notifications() {
	$meta = zem_rp_get_meta();
	$options = zem_rp_get_options();

	$blog_id = $meta['blog_id'];
	$auth_key = $meta['auth_key'];

	$req_options = array(
		'timeout' => 5
	);

	if(!$blog_id || !$auth_key || !$meta['zemanta_username']) return;

	// receive remote recommendations
	$url = ZEM_RP_CTR_DASHBOARD_URL . "notifications/?blog_id=$blog_id&auth_key=$auth_key";
	$response = wp_remote_get($url, $req_options);

	if (wp_remote_retrieve_response_code($response) == 200) {
		$body = wp_remote_retrieve_body($response);

		if ($body) {
			$json = json_decode($body);

			if ($json && isset($json->status) && $json->status === 'ok' && isset($json->data) && is_object($json->data)) 
			{
				$messages_ref =& $meta['remote_notifications'];
				$data = $json->data;

				if(isset($data->msgs) && is_array($data->msgs)) {
					// add new messages from server and update old ones
					foreach($data->msgs as $msg) {
						$messages_ref[$msg->msg_id] = $msg->text;
					}

					// sort messages by identifier
					ksort($messages_ref);
				}

				if(isset($data->delete_msgs) && is_array($data->delete_msgs)) {
					foreach($data->delete_msgs as $msg_id) {
						if(array_key_exists($msg_id, $messages_ref)) {
							unset($messages_ref[$msg_id]);
						}
					}
				}

				if(isset($data->turn_on_remote_recommendations) && $data->turn_on_remote_recommendations) {
					$meta['remote_recommendations'] = true;
				} else if(isset($data->turn_off_remote_recommendations) && $data->turn_off_remote_recommendations) {
					$meta['remote_recommendations'] = false;
				}

				if(isset($data->show_blogger_network_form) && $data->show_blogger_network_form) {
					$meta['show_blogger_network_form'] = true;
				} else if(isset($data->hide_blogger_network_form) && $data->hide_blogger_network_form) {
					$meta['show_blogger_network_form'] = false;
				}

				if(isset($data->show_traffic_exchange) && $data->show_traffic_exchange) {
					$meta['show_traffic_exchange'] = true;
				} else if(isset($data->hide_traffic_exchange) && $data->hide_traffic_exchange) {
					$meta['show_traffic_exchange'] = false;
				}

				zem_rp_update_meta($meta);
				zem_rp_update_options($options);
			}
		}
	}
}
