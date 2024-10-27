<?php
/**
 * Compatibility Class
 *
 * @file The UsersWP Model file
 * @package HMWP/Compatibility/UsersWP
 * @since 8.0
 */

defined( 'ABSPATH' ) || die( 'Cheatin\' uh?' );

class HMWP_Models_Compatibility_UsersWP extends HMWP_Models_Compatibility_Abstract {

	public function hookFrontend() {
		add_action('uwp_template_fields', array($this, 'addBruteForce'), 99, 1);
	}

	public function addBruteForce( $type ) {
		if ( HMWP_Classes_Tools::getOption( 'brute_use_math' ) ) { // math recaptcha
			echo HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->brute_math_form();
		}elseif ( HMWP_Classes_Tools::getOption( 'brute_use_captcha' ) ) { // recaptcha v2
			echo HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->brute_recaptcha_head();
			echo HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->brute_recaptcha_form();
		}elseif ( HMWP_Classes_Tools::getOption( 'brute_use_captcha_v3' ) ) { // recaptcha v3
			echo HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->brute_recaptcha_head_v3();
			echo HMWP_Classes_ObjController::getClass( 'HMWP_Models_Brute' )->brute_recaptcha_form_v3();
		}
	}

}
