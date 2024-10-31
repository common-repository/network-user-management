<?php
/*
Plugin Name: Network User Management
Plugin URI: N/A
Description: Synchronise users and user roles from main Blog, automatically add users to each site in your WordPress network (Multisite).
Author: Grávuj Miklós Henrich
Author URI: http://www.henrich.ro
Network: true
Version: 1.0
*/

define( 'WPNM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPNM_FORM_ACTION', str_replace( '%7E', '~', $_SERVER['REQUEST_URI'] ) );


// resolve orphan users
function add_orphan_user_to_all_blogs() {
	global $wpdb;
	$user_list = user_list();
	$blog_numbers = get_blog_count();
	foreach ( $user_list as $user ) {
		$blogs = get_blogs_of_user( $user );
		if ( count( $blogs ) < $blog_numbers ) {
			$role = 'subscriber';
			$role = $_REQUEST['role'];
			$blog_list = blog_list();
			foreach( $blog_list as $key => $blog ) {
				switch_to_blog( $blog->blog_id );
				if( $role != 'none' ) {
					add_user_to_blog( $blog->blog_id, $user, $role );
				}
				restore_current_blog();
			}
		}
	}
	return TRUE;
}

/**
 * Automatically adds the changed roles on all sites for a user
 */
function wpnm_edit_user_profile($userId) {
	$role = 'subscriber';
	$role = $_REQUEST['role'];
	$blog_list = blog_list();
	foreach( $blog_list as $key => $blog ) {
		switch_to_blog( $blog->blog_id );
		if( $role != 'none' ) {
			add_user_to_blog( $blog->blog_id, $userId, $role );
		}
		restore_current_blog();
	}
}
add_action( 'edit_user_profile_update', 'wpnm_edit_user_profile' );
add_action( 'user_register', 'wpnm_edit_user_profile' );
add_action( 'wpmu_activate_user', 'wpnm_edit_user_profile' );
add_action( 'wpmu_new_user', 'wpnm_edit_user_profile' );

function blog_list() {
	global $wpdb;
	$blog_list = $wpdb->get_results("SELECT blog_id FROM ". $wpdb->blogs ." 
			WHERE site_id = ". $wpdb->siteid ." AND archived = '0' AND spam = '0' AND deleted = '0' 
			ORDER BY registered ASC");
	return $blog_list;
}

function user_list() {
	global $wpdb;
	$user_list = $wpdb->get_col("SELECT ID FROM ". $wpdb->users ." WHERE user_status = 0");
	return $user_list;
}

function wpnm() {
	$user_list = user_list();
	$blog_numbers = get_blog_count();
	$i = 0;
	foreach ( $user_list as $user ) {
		$blogs = get_blogs_of_user( $user );
		$user_blogs = count( $blogs );
		if( $user_blogs < $blog_numbers ) {
			$i++;
		}		
	}
	$msg = array(
		'there'			=> 'There are',
		'not_added'		=> 'user(s) not added to all blogs.',
		'form'			=> '<form name="wpnm_form" method="post" action="'.WPNM_FORM_ACTION.'">
							<input type="hidden" name="wpnm" value="Y">
							'.wp_nonce_field('wpnm-nonce').'
							<input type="checkbox" name="add" value="Y" class="button-checkbox" /> Add all users to all blogs?
							<input type="submit" value="Add" class="button-primary" />
							</form>',
		'check'			=> 'Please check the checkbox above to proceed.',
		'updated'		=> 'All users are added to all blogs.',
		'error'			=> 'There was an error while trying to add all users to all blogs.',
		'note'			=> '<strong>Note:</strong> After checking the box below and click on Add button, all users will be added to all blogs with their roles synchronised.',
	);
	if ( $i != '0' ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( $_POST['wpnm'] == 'Y' && $_POST['add'] == 'Y' && wp_verify_nonce( $nonce, 'wpnm-nonce' ) ) {
			$added_orphans = add_orphan_user_to_all_blogs();
			if ( $added_orphans != FALSE ) {
				echo '<div class="updated"><p>'. $msg['updated'] .'</p></div>';
			} else {
				echo '<div class=""><p>Number of users which are not added to all blogs:</p></div>';
				echo '<div class="error"><p>'.$msg['error'].'</p></div>';
				echo $msg['form'];
			}
		} elseif ( $_POST['wpnm'] == 'Y' && $_POST['add'] != 'Y' && wp_verify_nonce( $nonce, 'wpnm-nonce' ) ) {
			echo '<div class="error"><p>'. $msg['there'] .' <strong>'. $i . '</strong> '. $msg['not_added'] .'</p></div>';
			echo '<div class="updated"><p>'.$msg['note'].'</p></div>';
			echo $msg['form'];
			echo '<div class="error"><p>'. $msg['check'] .'</p></div>';
		} else {
			echo '<div class="error"><p>'. $msg['there'] .' <strong>'. $i . '</strong> '. $msg['not_added'] .'</p></div>';
			echo '<div class="updated"><p>'.$msg['note'].'</p></div>';
			echo $msg['form'];
		}
	} else {
		echo '<div class="updated"><p>'. $msg['updated'] .'</p></div>';
	}
}

function wpnm_css() {
	wp_register_style( 'wpnm.css', WPNM_PLUGIN_URL . 'wpnm.css', array(), '1.0' );
	wp_enqueue_style( 'wpnm.css' );
}

function register_wpnm() {
	add_submenu_page(
		'users.php',
		'Network Users',
		'Network Users',
		'manage_network_options',
		'network-users',
		'wpnm_callback'
	); 
}

function wpnm_callback() {
	echo '<div class="wrap">';
	echo '<div class="icon32" id="icon-users"><br></div>';
	echo '<h2>Network Users</h2>';
	wpnm();
	echo '</div>';
}

add_action( 'admin_enqueue_scripts', 'wpnm_css' );
add_action( 'network_admin_menu', 'register_wpnm' );
?>