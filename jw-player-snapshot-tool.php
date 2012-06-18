<?php
/*
Plugin Name: JW Player Snapshot Tool
Plugin URI: http://labs.sorsawo.com/wordpress/jw-player-snapshot-tool/
Description: JW Player Snapshot Tool is a small JW Player module to create video snapshot
Author: Sorsawo Dot Com
Version: 1.0.1
Author URI: http://www.sorsawo.com
*/

/*  Copyright 2012 Sorsawo Dot Com (email: info at sorsawo.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

function jw_player_snapshot_tool() {
	
}

/**
 * Function to save media snapshot (video) to database
 */
function jw_player_snapshot_tool_create_snapshot() {

	global $wpdb;
	
	if ( isset ($_GET['snap']) && current_user_can( 'manage_options' ) ) {
		
		$param = explode('__JWP__', $wpdb->prepare( $_GET['snap'] ) );

		$media_id = $param[0];
		$nonce = $param[1];
		$post_id = $param[2];
		
		if (wp_verify_nonce($nonce, 'jw_player_snapshot_tool')) {
			
			include( ABSPATH . 'wp-admin/includes/image.php' );
		
			$image = file_get_contents( "php://input" );
	
			$upload_dir = wp_upload_dir();
			$video_thumb_dir = $upload_dir['path'];
	
			$image_path = $upload_dir['path'] . '/snap-' . $media_id . '.jpg';
			
			$thumbnail_id = get_post_meta($media_id, 'jwplayermodule_thumbnail', true);

			if ($thumbnail_id > 0)
				wp_delete_attachment($thumbnail_id, true);
		
			
			$fp = fopen( $image_path, 'wb' );
			fwrite( $fp, $image );
			fclose( $fp );
	
			//simpan image path to database
			$snapshot_id = wp_insert_post( array(
					'post_title'		=> 'snap-' . $media_id,
					'post_name'			=> 'snap-' . $media_id,
					'post_content'		=> '',
					'post_type' 		=> 'attachment',
					'post_status'		=> 'inherit',
					'post_mime_type'	=> 'image/jpeg',
					'guid'				=> $upload_dir['url'] . '/snap-' . $media_id . '.jpg',
					'post_parent'		=> $post_id
			) );
			
			$attach_data = wp_generate_attachment_metadata( $snapshot_id, $image_path );
			wp_update_attachment_metadata( $snapshot_id,  $attach_data );
			update_post_meta( $media_id, 'jwplayermodule_thumbnail', $snapshot_id );
	
			echo $upload_dir['url'] . '/snap-' . $media_id . '.jpg';
			exit();
		}
	}
}
add_action( 'init', 'jw_player_snapshot_tool_create_snapshot' );

function jw_player_snapshot_tool_scripts($hook) {

	if ($hook == 'media-upload.php' || $hook == 'media-new.php' || $hook == 'media-upload-popup') {
		wp_enqueue_script( 'jw_player_snapshot', plugins_url( 'lib/swfobject.js', __FILE__ ), array( 'jquery' ));
	}
}

add_action( 'admin_enqueue_scripts', 'jw_player_snapshot_tool_scripts', 10, 1 );

function jw_player_snapshot_tool_tab($tabs) {

	$new_tab = array('jw_player_snapshot_tool' => __('Snapshot Tool', 'jw_player_snapshot_tool'));
	return array_merge($tabs, $new_tab);
}
add_filter('media_upload_tabs', 'jw_player_snapshot_tool_tab');

function jw_player_snapshot_tool_total_posts($filter = 'all', $post_id) {
	$args = array('post_type' => 'attachment', 'post_mime_type' => 'video', 'post_status' => null, 'numberposts' => -1);
	if ($filter === 'gallery')	$args['post_parent'] = $post_id;
	$videos = get_posts( $args );
	
	return count($videos);
}

function jw_player_snapshot_tool_page() {
	
	media_upload_header();
	
	wp_enqueue_style( 'media' );
	
	$post_id = isset( $_REQUEST['post_id'] ) ? $_REQUEST['post_id'] : 0;
	$page_id = isset( $_REQUEST['page_id'] ) ? $_REQUEST['page_id'] : 1;
	$filter = isset( $_REQUEST['filter'] ) ? $_REQUEST['filter'] : 'gallery';

	$total_posts = jw_player_snapshot_tool_total_posts($filter, $post_id);
	
	$per_page = 5;
	$total_page = ceil($total_posts / $per_page);	
	
	$args = array('post_type' => 'attachment', 'post_mime_type' => 'video', 'post_status' => null, 'numberposts' => $per_page);
	
	if ($filter === 'gallery')	$args['post_parent'] = $post_id;
	
	if ($page_id > 1) {
		$offset = ($page_id - 1) * $per_page;
		$args['offset'] = $offset;
	}
	
	$videos = get_posts( $args );
	?>	
	<form id="filter" method="post" class="media-upload-form">
	
	<div class="tablenav">

		<div class='tablenav-pages'><?php echo paginate_links( array('base' => admin_url('media-upload.php?post_id=' . $post_id . '&tab=jw_player_snapshot_tool&filter=' . $filter . '%_%'), 'format' => '&page_id=%#%', 'total' => $total_page, 'current' => $page_id) ) ?></div>
	
		<div class="alignleft actions">
			<ul class="subsubsub">
				<li>Filter: <a href="<?php echo admin_url('media-upload.php?post_id=' . $post_id . '&tab=jw_player_snapshot_tool&filter=gallery') ?>">Gallery Only</a> | </li>
				<li><a href="<?php echo admin_url('media-upload.php?post_id=' . $post_id . '&tab=jw_player_snapshot_tool&filter=all') ?>">All Video</a></li>
			</ul>
		</div>
	
		<br class="clear" />
	</div>
	
	<table class="widefat">
		<thead>
		<tr>
			<th>Video Name</th>
			<th class="actions-head">&nbsp;</th>
		</tr></thead>
	</table>
	
	<div id="media-items">
	<?php foreach ($videos as $video) {
		$nonce = wp_create_nonce  ('jw_player_snapshot_tool');
		$video_thumbnail = wp_get_attachment_url( get_post_meta($video->ID, 'jwplayermodule_thumbnail', true) );
		$snap_script = add_query_arg( 'snap', $video->ID . '__JWP__' . $nonce. '__JWP__' . $post_id, home_url('/') );
	?>
	
	<div id='media-item-<?php echo $video->ID ?>' class='media-item child-of-222 preloaded'>
	<div class='progress hidden'><div class='bar'></div></div><div id='media-upload-error-<?php echo $video->ID ?>' class='hidden'></div><div class='filename hidden'></div>
	<input type='hidden' id='type-of-<?php echo $video->ID ?>' value='video' />
	
	<a class='toggle describe-toggle-on' href='#'>Show</a>
	<a class='toggle describe-toggle-off' href='#'>Hide</a>
	<div class='filename new'><span class='title'><?php echo substr($video->guid, strrpos($video->guid, '/')+1, strlen($video->guid)) ?></span></div>
	<table class='slidetoggle describe startclosed'>
		<thead class='media-item-info' id='media-head-<?php echo $video->ID ?>'>
		<tr valign='top'><td>
		<div id="my-player-<?php echo $video->ID ?>">Loading...</div>
		<script type="text/javascript">
		var so_<?php echo $video->ID ?> = new SWFObject("<?php echo plugins_url( 'lib/player.swf', __FILE__ ) ?>", "mpl", "400", "250", "9");
		so_<?php echo $video->ID ?>.addParam("allowscriptaccess", "always");
		so_<?php echo $video->ID ?>.addParam("allowfullscreen", "false");
		so_<?php echo $video->ID ?>.addVariable("image", "<?php echo $video_thumbnail ?>");
		so_<?php echo $video->ID ?>.addVariable("file", "<?php echo $video->guid ?>");
		so_<?php echo $video->ID ?>.addVariable("plugins", "snapshot-1");
		so_<?php echo $video->ID ?>.addVariable("snapshot.script", "<?php echo $snap_script ?>");
		so_<?php echo $video->ID ?>.addVariable("snapshot.data", "true");
		so_<?php echo $video->ID ?>.write("my-player-<?php echo $video->ID ?>");
		</script>
		</td></tr>
	</thead>
	</table>
	</div>
	<?php } ?>
	</div>
	<h3>Donate</h3>
	<p>If you like this plugin and find it useful, help keep this plugin free and actively developed by clicking the donate button.</p>
	</form>
	<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="TKZAETU6G687C">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
	
	<?php 
	add_query_arg( array('filter' => false, 'page_id' => false) );
}

function jw_player_snapshot_tool_iframe() {
	return wp_iframe( 'jw_player_snapshot_tool_page');
}
add_action('media_upload_jw_player_snapshot_tool', 'jw_player_snapshot_tool_iframe');
