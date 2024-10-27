<?php
/**
 * Events Log Model
 * Called to hook and log the users Events
 *
 * @file  The Events file
 * @package HMWP/EventsModel
 * @since 6.0.0
 */

defined( 'ABSPATH' ) || die( 'Cheatin\' uh?' );

class HMWP_Models_Log {

	/**
	 * An array containing a list of keys with their corresponding boolean values.
	 * These keys are allowed for various operations and are checked to determine
	 * permissible actions.
	 */
	public $allow_keys = array(
		'username'        => true,
		'role'            => true,
		'log'             => true,
		'ip'              => true,
		'referer'         => true,
		'post'            => true,
		'post_id'         => true,
		'post_ID'         => true,
		'doaction'        => true,
		'id'              => true,
		'ids'             => true,
		'user_id'         => true,
		'user'            => true,
		'users'           => true,
		'product_id'      => true,
		'post_type'       => true,
		'plugin'          => true,
		'new'             => true,
		'name'            => true,
		'slug'            => true,
		'stylesheet'      => true,
		'customize_theme' => true,
		'widget-id'       => true,
		'delete_widget'   => true,
		'menu-name'       => true,
	);

	/**
	 * An array containing a list of actions with their corresponding boolean values.
	 * These actions are allowed for various operations and are checked to determine
	 * permissible actions for users, posts, comments, plugins, file editing, themes, and widgets.
	 */
	public $allow_actions = array(
		//users
		'empty_username'         => true,
		'invalid_username'       => true,
		'incorrect_password'     => true,
		'invalid_email'          => true,
		'authentication_failed'  => true,
		'update'                 => true,
		'login'                  => true,
		'logout'                 => true,
		'block_ip'               => true,
		'createuser'             => true,
		//posts
		'trash'                  => true,
		'untrash'                => true,
		'edit'                   => true,
		'inline-save'            => true,
		'delete-post'            => true,
		'upload-attachment'      => true,
		'activate'               => true,
		'deactivate'             => true,
		//comments
		'dim-comment'            => true,
		'replyto-comment'        => true,
		//plugins
		'delete'                 => true,
		'delete-plugin'          => true,
		'install-plugin'         => true,
		'update-plugin'          => true,
		'dodelete'               => true,
		//file edit
		'edit-theme-plugin-file' => true,
		//theme
		'customize_save'         => true,
		//widgets
		'save-widget'            => true,
	);

	/**
	 * Log actions with specified values.
	 *
	 * @param  string|null  $action  The action to log.
	 * @param  array  $values  The values associated with the action.
	 *
	 * @return void
	 */
	public function hmwp_log_actions( $action = null, $values = array() ) {
		$posts = array();

		if ( isset( $action ) && $action <> '' ) {
			//remove unwanted actions
			$allow_actions = array_filter( $this->allow_actions );
			if ( in_array( $action, array_keys( $allow_actions ) ) ) {
				if ( ! empty( $values ) ) {
					$values = array_intersect_key( $values, $this->allow_keys );
				}
				if ( ! empty( $_GET ) ) {
					$posts = array_intersect_key( $_GET, $this->allow_keys );
				}
				if ( ! empty( $_POST ) ) {
					$posts = array_intersect_key( $_POST, $this->allow_keys );
				}

				//Try to get the name and the type for the current record
				$post_id = 0;
				if ( isset( $posts['id'] ) ) {
					$post_id = $posts['id'];
				}
				if ( isset( $posts['post'] ) ) {
					$post_id = $posts['post'];
				}
				if ( isset( $posts['post_ID'] ) ) {
					$post_id = $posts['post_ID'];
				}
				if ( isset( $posts['post_id'] ) ) {
					$post_id = $posts['post_id'];
				}
				if ( ! isset( $posts['username'] ) || $posts['username'] == '' ) {

					if ( ! function_exists( 'wp_get_current_user' ) ) {
						include_once ABSPATH . WPINC . '/pluggable.php';
					}

					$current_user = wp_get_current_user();
					if ( isset( $current_user->user_login ) ) {
						$posts['username'] = $current_user->user_login;
					}
				}

				if ( $post_id > 0 ) {
					if ( $record = @get_post( $post_id ) ) {
						$posts['name']      = $record->post_name;
						$posts['post_type'] = $record->post_type;
					}
				}

				/////////////////////////////////////////////////////
				/// Add referer and IP
				$data = array(
					'referer' => wp_get_raw_referer(),
					'ip'      => $_SERVER['REMOTE_ADDR'],
				);

				$data = array_merge( $data, (array) $values, $posts );

				//Log the block IP on the server
				$args = array(
					'action' => $action,
					'data'   => serialize( $data ),
				);

				HMWP_Classes_Tools::hmwp_remote_post( _HMWP_ACCOUNT_SITE_ . '/api/log', $args, array( 'timeout' => 5 ) );
			}
		}
	}

	/**
	 * Joins an associative array into a string with each key-value pair formatted as "key=value".
	 * If the value is an array, it will be formatted as "key[]=value1&key[]=value2".
	 *
	 * @param  array  $input  The input associative array to be joined.
	 *
	 * @return string|array Returns the joined string if the input array is not empty, otherwise returns an empty array.
	 */
	public function joinArray( $input ) {
		if ( ! empty( $input ) ) {
			return implode( ', ', array_map( function ( $v, $k ) {
					if ( is_array( $v ) ) {
						return $k . '[]=' . implode( '&' . $k . '[]=', $v );
					} else {
						return $k . '=' . $v;
					}
				}, $input, array_keys( $input ) ) );
		} else {
			return [];
		}
	}
}
