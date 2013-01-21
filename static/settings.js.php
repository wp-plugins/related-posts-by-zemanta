<script type="text/javascript">
	function zem_rp_display_excerpt_onclick(){
		var zem_rp_display_excerpt = document.getElementById('zem_rp_display_excerpt');
		var zem_rp_excerpt_max_length_label = document.getElementById('zem_rp_excerpt_max_length_label');
		if(zem_rp_display_excerpt.checked){
			zem_rp_excerpt_max_length_label.style.display = '';
		} else {
			zem_rp_excerpt_max_length_label.style.display = 'none';
		}
	}
	function zem_rp_display_thumbnail_onclick(){
		var zem_rp_display_thumbnail = document.getElementById('zem_rp_display_thumbnail');
		var zem_rp_thumbnail_span = document.getElementById('zem_rp_thumbnail_span');
		if(zem_rp_display_thumbnail.checked){
			zem_rp_thumbnail_span.style.display = '';
			jQuery('#wp-rp-thumbnails-info').fadeOut();
			if (window.localStorage) {
				window.localStorage.zem_rp_thumbnails_info = "close";
			}
		} else {
			zem_rp_thumbnail_span.style.display = 'none';
		}
	}
</script>
