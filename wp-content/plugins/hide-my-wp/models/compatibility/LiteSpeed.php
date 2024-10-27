<?php
/**
 * Compatibility Class
 *
 * @file The LiteSpeed Model file
 * @package HMWP/Compatibility/LiteSpeed
 */

defined( 'ABSPATH' ) || die( 'Cheatin\' uh?' );

class HMWP_Models_Compatibility_LiteSpeed extends HMWP_Models_Compatibility_Abstract {

	public function hookAdmin() {
		add_action( 'wp_initialize_site', function ( $site_id ) {
			HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' )->flushChanges();
		}, PHP_INT_MAX, 1 );

		add_action( 'create_term', function ( $term_id ) {
			add_action( 'admin_footer', function () {
				HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' )->flushChanges();
			} );
		}, PHP_INT_MAX, 1 );

		// Wait for the cache on litespeed servers and flush the changes
		add_action( 'hmwp_apply_permalink_changes', function () {
			add_action( 'admin_footer', function () {
				HMWP_Classes_ObjController::getClass( 'HMWP_Models_Rewrite' )->flushChanges();
			} );
		} );

		// Only if the litespeed plugin is installed
		if ( HMWP_Classes_Tools::isPluginActive( 'litespeed-cache/litespeed-cache.php' ) ) {
			add_action( 'hmwp_mappsettings_saved', array( $this, 'doMapping' ) );

			if ( ! HMWP_Classes_Tools::isWpengine() ) {
				add_action( 'hmwp_settings_saved', array( $this, 'doMapping' ) );
				add_action( 'hmwp_settings_saved', array( $this, 'doExclude' ) );
			}
		}

	}

	public function hookFrontend() {

		// Don't load plugin buffer if litespeed
		add_action( 'litespeed_initing', function () {
			if ( ! defined( 'LITESPEED_DISABLE_ALL' ) || ! defined( 'LITESPEED_GUEST_OPTM' ) ) {
				add_filter( 'hmwp_process_buffer', '__return_false' );
			}
		} );

		// Change the path withing litespeed buffer
		add_filter( 'litespeed_buffer_finalize', array( $this, 'findReplaceCache' ), PHP_INT_MAX );

		// Set priority load for compatibility
		add_filter( 'hmwp_priority_buffer', '__return_true' );
		add_filter( 'litespeed_comment', '__return_false' );

	}

	/**
	 * Adds the URL and text mappings for the wp-rocket plugin, particularly
	 * mapping specific paths and script branding for litespeed caching.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function doMapping() {

		// Add the URL mapping for wp-rocket plugin
		if ( HMWP_Classes_Tools::getDefault( 'hmwp_wp-content_url' ) <> HMWP_Classes_Tools::getOption( 'hmwp_wp-content_url' ) ) {
			$hmwp_url_mapping  = json_decode( HMWP_Classes_Tools::getOption( 'hmwp_url_mapping' ), true );
			$hmwp_text_mapping = json_decode( HMWP_Classes_Tools::getOption( 'hmwp_text_mapping' ), true );

			// Map the litespeed default cache path
			if ( HMWP_Classes_Tools::getDefault( 'hmwp_wp-content_url' ) <> HMWP_Classes_Tools::getOption( 'hmwp_wp-content_url' ) ) {
				if ( empty( $hmwp_url_mapping['from'] ) || ! in_array( '/' . HMWP_Classes_Tools::getOption( 'hmwp_wp-content_url' ) . '/litespeed/', $hmwp_url_mapping['from'] ) ) {
					$hmwp_url_mapping['from'][] = '/' . HMWP_Classes_Tools::getOption( 'hmwp_wp-content_url' ) . '/litespeed/';
					$hmwp_url_mapping['to'][]   = '/' . HMWP_Classes_Tools::getOption( 'hmwp_wp-content_url' ) . '/mycache/';
				}
			}

			// Map the litespeed script branding
			if ( empty( $hmwp_text_mapping['from'] ) || ! in_array( 'litespeed', $hmwp_text_mapping['from'] ) ) {
				$hmwp_text_mapping['from'][] = 'litespeed';
				$hmwp_text_mapping['to'][]   = 'text';
			}

			//Save Text Mapping and URL Mapping
			HMWP_Classes_ObjController::getClass( 'HMWP_Models_Settings' )->saveURLMapping( $hmwp_url_mapping['from'], $hmwp_url_mapping['to'] );
			HMWP_Classes_ObjController::getClass( 'HMWP_Models_Settings' )->saveTextMapping( $hmwp_text_mapping['from'], $hmwp_text_mapping['to'] );
		}

	}

	/**
	 * Excludes specific login URLs from LiteSpeed caching configuration based on
	 * the current and default hidden login URLs set by the Hide My WP plugin.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function doExclude() {
		if ( HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ) <> HMWP_Classes_Tools::getOption( 'hmwp_login_url' ) ) {

			$exlude = get_option( 'litespeed.conf.cache-exc' );

			// If there are already URLs in the exclude list
			if ( $exlude = json_decode( $exlude, true ) ) {
				// Add custom login in caching exclude list
				if ( ! in_array( '/' . HMWP_Classes_Tools::getOption( 'hmwp_login_url' ), $exlude ) ) {
					$exlude[] = '/' . HMWP_Classes_Tools::getOption( 'hmwp_login_url' );
				}
				// Add default login in caching exclude list
				if ( ! in_array( '/' . HMWP_Classes_Tools::getDefault( 'hmwp_login_url' ), $exlude ) ) {
					$exlude[] = '/' . HMWP_Classes_Tools::getDefault( 'hmwp_login_url' );
				}
			} else {
				$exlude   = array();
				$exlude[] = '/' . HMWP_Classes_Tools::getDefault( 'hmwp_login_url' );
				$exlude[] = '/' . HMWP_Classes_Tools::getOption( 'hmwp_login_url' );
			}

			update_option( 'litespeed.conf.cache-exc', wp_json_encode( $exlude ) );
		}

	}
}
