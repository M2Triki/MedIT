<?php
/**
 * Brute Force Protection
 * Called when the Brute Force Protection is activated
 *
 * @file  The Brute Force file
 * @package HMWP/BruteForce
 * @since 4.2.0
 */

defined( 'ABSPATH' ) || die( 'Cheatin\' uh?' );

/**
 * Class HMWP_Controllers_Brute
 *
 * Handles brute force protection mechanisms including login, registration, and lost password
 * attempts. Integrates various captcha methods to safeguard against automated attacks.
 */
class HMWP_Controllers_Brute extends HMWP_Classes_FrontController {

	public function __construct() {
		parent::__construct();

		// Listen the login process and check BF
		add_filter( 'authenticate', array( $this, 'hmwp_check_preauth' ), 99, 1 );

		// Once logged in, clear blocked IPs
		add_action( 'admin_init', array( $this, 'hmwp_update_trusted_headers' ), 99 );

		// Listen BF shortcode on forms
		add_shortcode( 'hmwp_bruteforce', array( $this, 'hmwp_bruteforce_shortcode' ) );

		//Check BF on comments
		if (HMWP_Classes_Tools::getOption('hmwp_bruteforce_comments')) {
			add_filter( 'preprocess_comment', array($this, 'hmwp_comments_validation') );
			add_filter( 'comment_form_default_fields', array($this, 'hmwp_comments_form_fields') );
		}

		// Check BF on register
		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_register' ) ) {
			add_filter( 'registration_errors', array( $this, 'hmwp_check_registration' ), 99, 3 );
		}

		// Check BF on Lost Password
		if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_lostpassword' ) ) {
			add_filter( 'lostpassword_errors', array( $this, 'hmwp_check_lpassword' ), 99, 2 );
		}

		// If Brute force math recaptcha is active
		if ( HMWP_Classes_Tools::getOption( 'brute_use_math' ) ) {
			add_action( 'wp_login_failed', array( $this, 'hmwp_failed_attempt' ), 99 );
			add_action( 'login_form', array( $this->model, 'brute_math_form' ), 99 );

			if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_lostpassword' ) ) {
				add_filter( 'lostpassword_form', array( $this->model, 'brute_math_form' ), 99 );
			}

			if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_register' ) ) {
				add_action( 'register_form', array( $this->model, 'brute_math_form' ), 99 );
			}

		} elseif ( HMWP_Classes_Tools::getOption( 'brute_use_captcha' ) ) {
			add_action( 'wp_login_failed', array( $this, 'hmwp_failed_attempt' ), 99 );
			add_action( 'login_head', array( $this->model, 'brute_recaptcha_head' ), 99 );
			add_action( 'login_form', array( $this->model, 'brute_recaptcha_form' ), 99 );

			if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_lostpassword' ) ) {
				add_filter( 'lostpassword_form', array( $this->model, 'brute_recaptcha_form' ), 99 );
			}

			if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_register' ) ) {
				add_action( 'register_form', array( $this->model, 'brute_recaptcha_form' ), 99 );
			}

		} elseif ( HMWP_Classes_Tools::getOption( 'brute_use_captcha_v3' ) ) {
			add_action( 'wp_login_failed', array( $this, 'hmwp_failed_attempt' ), 99 );
			add_action( 'login_head', array( $this->model, 'brute_recaptcha_head_v3' ), 99 );
			add_action( 'login_form', array( $this->model, 'brute_recaptcha_form_v3' ), 99 );

			if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_lostpassword' ) ) {
				add_filter( 'lostpassword_form', array( $this->model, 'brute_recaptcha_form_v3' ), 99 );
			}

			if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_register' ) ) {
				add_action( 'register_form', array( $this->model, 'brute_recaptcha_form_v3' ), 99 );
			}

		}

	}

	/**
	 * Hook into the front-end initialization process.
	 *
	 * @return void
	 */
	public function hookFrontinit() {
		if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {

			// Load the Multilanguage
			HMWP_Classes_Tools::loadMultilanguage();

			// Check brute force
			$this->bruteBlockCheck();
		}
	}

	/**
	 * Checks if the current IP address is blocked by a brute force protection mechanism.
	 *
	 * @return void
	 */
	public function bruteBlockCheck() {

		// Check current IP Address
		$response = $this->model->brute_call( 'check_ip' );

		// If blocked, show block message
		if ( $response['status'] == 'blocked' ) {
			if ( ! $this->model->check_whitelisted_ip( $this->model->brute_get_ip() ) ) {
				wp_ob_end_flush_all();
				wp_die( HMWP_Classes_Tools::getOption( 'hmwp_brute_message' ), esc_html__( 'IP Blocked', 'hide-my-wp' ), array( 'response' => 403 ) );
			}
		}
	}

	/**
	 * Generates a shortcode for brute force protection mechanism in forms.
	 * The method integrates different protection mechanisms such as a math challenge or reCaptcha.
	 * It also ensures the script is not applied to logged-in users.
	 *
	 * @param  array  $atts  Shortcode attributes.
	 * @param  string  $content  Content enclosed within the shortcode.
	 *
	 * @return string|null The protection mechanism script for forms, or null if the user is logged in.
	 */
	public function hmwp_bruteforce_shortcode( $atts = array(), $content = '' ) {
		global $hmwp_bruteforce;

		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return;
		}

		$hmwp_bruteforce = true;

		if ( HMWP_Classes_Tools::getOption( 'brute_use_math' ) ) {
			$script = '
                <script>
                function reCaptchaSubmit(e) {
                    var form = this;
                    e.preventDefault();
    
                    var brute_num = document.getElementsByName("brute_num")[0];
                    if(typeof brute_num !== "undefined"){
                        var input = document.createElement("input");
                        input.type = "hidden";
                        input.name = "brute_num" ;
                        input.value = brute_num.value ;
                        form.appendChild(input);
                    }
                    
                    var brute_ck = document.getElementsByName("brute_ck")[0];
                    if(typeof brute_ck !== "undefined"){
                        var input = document.createElement("input");
                        input.type = "hidden";
                        input.name = "brute_ck" ;
                        input.value = brute_ck.value ;
                        form.appendChild(input);
                    }
                    
                    HTMLFormElement.prototype.submit.call(form);
                }
    
                if(document.getElementsByTagName("form").length > 0) {
                    var x = document.getElementsByTagName("form");
                    for (var i = 0; i < x.length; i++) {
                        x[i].addEventListener("submit", reCaptchaSubmit);
                    }
                }
            </script>';

			return $this->model->brute_math_form() . $script;
		} elseif ( HMWP_Classes_Tools::getOption( 'brute_use_captcha' ) ) {
			return $this->model->brute_recaptcha_head() . $this->model->brute_recaptcha_form();
		} elseif ( HMWP_Classes_Tools::getOption( 'brute_use_captcha_v3' ) ) {
			return $this->model->brute_recaptcha_head_v3() . $this->model->brute_recaptcha_form_v3();
		}

	}

	/**
	 * Handles various actions related to brute force protection and IP management.
	 *
	 * @return void
	 */
	public function action() {
		parent::action();

		switch ( HMWP_Classes_Tools::getValue( 'action' ) ) {

			case 'hmwp_brutesettings':
				HMWP_Classes_Tools::saveOptions( 'hmwp_bruteforce', HMWP_Classes_Tools::getValue( 'hmwp_bruteforce' ) );
				HMWP_Classes_Tools::saveOptions( 'hmwp_bruteforce_register', HMWP_Classes_Tools::getValue( 'hmwp_bruteforce_register' ) );
				HMWP_Classes_Tools::saveOptions( 'hmwp_bruteforce_lostpassword', HMWP_Classes_Tools::getValue( 'hmwp_bruteforce_lostpassword' ) );
				HMWP_Classes_Tools::saveOptions('hmwp_bruteforce_comments', HMWP_Classes_Tools::getValue('hmwp_bruteforce_comments'));
				HMWP_Classes_Tools::saveOptions( 'hmwp_bruteforce_username', HMWP_Classes_Tools::getValue( 'hmwp_bruteforce_username' ) );
				HMWP_Classes_Tools::saveOptions( 'hmwp_bruteforce_woocommerce', HMWP_Classes_Tools::getValue( 'hmwp_bruteforce_woocommerce' ) );

				//whitelist_ip
				$whitelist = HMWP_Classes_Tools::getValue( 'whitelist_ip', '', true );
				$ips       = explode( PHP_EOL, $whitelist );
				if ( ! empty( $ips ) ) {
					foreach ( $ips as &$ip ) {
						$ip = $this->model->clean_ip( $ip );
					}

					$ips = array_unique( $ips );
					HMWP_Classes_Tools::saveOptions( 'whitelist_ip', json_encode( $ips ) );
				}

				//banlist_ip
				$banlist = HMWP_Classes_Tools::getValue( 'banlist_ip', '', true );
				$ips     = explode( PHP_EOL, $banlist );
				foreach ( $ips as &$ip ) {
					$ip = $this->model->clean_ip( $ip );

					// If the IP is in a private or reserved range, keep looking
					if ( $ip == '127.0.0.1' || $ip == '::1' ) {
						HMWP_Classes_Error::setNotification( esc_html__( "Add only real IPs. No local ips allowed.", 'hide-my-wp' ) );
					}
				}
				if ( ! empty( $ips ) ) {
					$ips = array_unique( $ips );
					HMWP_Classes_Tools::saveOptions( 'banlist_ip', json_encode( $ips ) );
				}

				// Brute force math option
				HMWP_Classes_Tools::saveOptions( 'brute_use_math', HMWP_Classes_Tools::getValue( 'brute_use_math', 0 ) );
				if ( HMWP_Classes_Tools::getValue( 'hmwp_bruteforce', 0 ) ) {
					$attempts = HMWP_Classes_Tools::getValue( 'brute_max_attempts' );
					if ( (int) $attempts <= 0 ) {
						$attempts = 3;
						HMWP_Classes_Error::setNotification( esc_html__( 'You need to set a positive number of attempts.', 'hide-my-wp' ) );

					}
					HMWP_Classes_Tools::saveOptions( 'brute_max_attempts', (int) $attempts );

					$timeout = HMWP_Classes_Tools::getValue( 'brute_max_timeout' );
					if ( (int) $timeout <= 0 ) {
						$timeout = 3600;
						HMWP_Classes_Error::setNotification( esc_html__( 'You need to set a positive waiting time.', 'hide-my-wp' ) );

					}
					HMWP_Classes_Tools::saveOptions( 'hmwp_brute_message', HMWP_Classes_Tools::getValue( 'hmwp_brute_message', '', true ) );
					HMWP_Classes_Tools::saveOptions( 'brute_max_timeout', $timeout );
				}

				// For reCaptcha option
				HMWP_Classes_Tools::saveOptions( 'brute_use_captcha', HMWP_Classes_Tools::getValue( 'brute_use_captcha', 0 ) );
				if ( HMWP_Classes_Tools::getValue( 'brute_use_captcha', 0 ) ) {
					HMWP_Classes_Tools::saveOptions( 'brute_captcha_site_key', HMWP_Classes_Tools::getValue( 'brute_captcha_site_key', '' ) );
					HMWP_Classes_Tools::saveOptions( 'brute_captcha_secret_key', HMWP_Classes_Tools::getValue( 'brute_captcha_secret_key', '' ) );
					HMWP_Classes_Tools::saveOptions( 'brute_captcha_theme', HMWP_Classes_Tools::getValue( 'brute_captcha_theme', 'light' ) );
					HMWP_Classes_Tools::saveOptions( 'brute_captcha_language', HMWP_Classes_Tools::getValue( 'brute_captcha_language', '' ) );
				}

				HMWP_Classes_Tools::saveOptions( 'brute_use_captcha_v3', HMWP_Classes_Tools::getValue( 'brute_use_captcha_v3', 0 ) );
				if ( HMWP_Classes_Tools::getValue( 'brute_use_captcha_v3', 0 ) ) {
					HMWP_Classes_Tools::saveOptions( 'brute_captcha_site_key_v3', HMWP_Classes_Tools::getValue( 'brute_captcha_site_key_v3', '' ) );
					HMWP_Classes_Tools::saveOptions( 'brute_captcha_secret_key_v3', HMWP_Classes_Tools::getValue( 'brute_captcha_secret_key_v3', '' ) );
				}

				// Clear the cache if there are no errors
				if ( ! HMWP_Classes_Tools::getOption( 'error' ) ) {

					if ( ! HMWP_Classes_Tools::getOption( 'logout' ) ) {
						HMWP_Classes_Tools::saveOptionsBackup();
					}

					HMWP_Classes_Tools::emptyCache();
					HMWP_Classes_Error::setNotification( esc_html__( 'Saved' ), 'success' );
				}

				break;
			case 'hmwp_deleteip':

				// Delete a specific blocked IP address from database
				$transient = HMWP_Classes_Tools::getValue( 'transient', null );
				if ( isset( $transient ) ) {
					$this->model->delete_ip( $transient );
				}

				break;
			case 'hmwp_deleteallips':

				// Delete all blocked IP addresses from database
				$this->clearBlockedIPs();
				break;

			case 'hmwp_blockedips':

				// Return the list of blocked Ip addresses
				if ( HMWP_Classes_Tools::isAjax() ) {
					wp_send_json_success( $this->getBlockedIps() );
				}
				break;
		}
	}

	/**
	 * Retrieves and constructs an HTML table of blocked IP addresses and their details.
	 *
	 * @return string An HTML string representing a table with blocked IP addresses, including columns for IP address, number of failed attempts, hostname, and options for unlocking.
	 */
	public function getBlockedIps() {

		$data = '<table class="table table-striped" >';
		$ips  = $this->model->get_blocked_ips();
		$data .= "<tr>
                    <th>" . esc_html__( 'Cnt', 'hide-my-wp' ) . "</th>
                    <th>" . esc_html__( 'IP', 'hide-my-wp' ) . "</th>
                    <th>" . esc_html__( 'Fail Attempts', 'hide-my-wp' ) . "</th>
                    <th>" . esc_html__( 'Hostname', 'hide-my-wp' ) . "</th>
                    <th>" . esc_html__( 'Options', 'hide-my-wp' ) . "</th>
                 </tr>";
		if ( ! empty( $ips ) ) {
			$cnt = 1;
			foreach ( $ips as $transient => $ip ) {
				//increment fail attempt as it starts from 0
				$ip['attempts'] = (int) $ip['attempts'] + 1;

				$data .= "<tr>
                        <td>" . $cnt . "</td>
                        <td>{$ip['ip']}</td>
                        <td>{$ip['attempts']}</td>
                        <td>{$ip['host']}</td>
                        <td class='p-2'> <form method=\"POST\">
                                " . wp_nonce_field( 'hmwp_deleteip', 'hmwp_nonce', true, false ) . "
                                <input type=\"hidden\" name=\"action\" value=\"hmwp_deleteip\" />
                                <input type=\"hidden\" name=\"transient\" value=\"" . $transient . "\" />
                                <input type=\"submit\" class=\"btn rounded-0 btn-sm btn-light save no-p-v\" value=\"Unlock\" />
                            </form>
                        </td>
                     </tr>";
				$cnt ++;
			}
		} else {
			$data .= "<tr>
                        <td colspan='5'>" . _( 'No blacklisted ips' ) . "</td>
                     </tr>";
		}
		$data .= '</table>';

		return $data;
	}

	/**
	 * Checks the form BEFORE register so that bots don't get to go around the register form.
	 *
	 * @param $errors
	 * @param $sanitizedLogin
	 * @param $userEmail
	 *
	 * @return mixed
	 */
	function hmwp_check_registration( $errors, $sanitizedLogin, $userEmail ) {

		// Only in frontend for not logged users
		if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {

			$response = $this->model->brute_check_loginability();

			$error = false;

			if ( HMWP_Classes_Tools::getOption( 'brute_use_math' ) ) {

				$error = $this->model->brute_math_authenticate( $errors, $response );

			} elseif ( HMWP_Classes_Tools::getOption( 'brute_use_captcha' ) || HMWP_Classes_Tools::getOption( 'brute_use_captcha_v3' ) ) {

				$error = $this->model->brute_catpcha_authenticate( $errors, $response );

			}

			if ( is_wp_error( $error ) ) {
				return $error;
			} else {
				$this->model->brute_call( 'clear_ip' );
			}

		}

		return $errors;
	}

	/**
	 * Checks the user's password during the authentication process for non-logged-in users
	 * on the frontend and adds appropriate error messages if needed.
	 *
	 * @param  WP_Error  $errors  A WP_Error object containing any existing errors.
	 * @param  WP_User  $user  The WP_User object for the user being authenticated.
	 *
	 * @return WP_Error A WP_Error object containing any updated errors.
	 */
	function hmwp_check_lpassword( $errors, $user ) {

		//only in frontend for not logged users
		if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {

			$error = $this->hmwp_check_preauth( $user );

			if ( is_wp_error( $error ) ) {

				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( $error->get_error_message(), 'error' );
					add_filter( 'allow_password_reset', '__return_false' );
				}

				return $error;
			}
		}

		return $errors;
	}


	/**
	 * Performs a pre-authentication check to enhance security during the login process.
	 *
	 * @param  mixed  $user  A user object, WP_Error object, or string representing the username.
	 *
	 * @return mixed Modified user object or WP_Error object depending on the checks performed.
	 */
	function hmwp_check_preauth( $user = '' ) {

		if ( ! apply_filters( 'hmwp_preauth_check', true ) ) {
			return $user;
		}

		//If this is a whitelist IP
		if ( $this->model->check_whitelisted_ip( $this->model->brute_get_ip() ) ) {
			return $user;
		}

		if ( is_wp_error( $user ) ) {
			if ( method_exists( $user, 'get_error_codes' ) ) {
				$errors = $user->get_error_codes();

				if ( ! empty( $errors ) ) {
					foreach ( $errors as $error ) {
						if ( $error == 'empty_username' || $error == 'empty_password' ) {
							return $user;
						}

						if ( HMWP_Classes_Tools::getOption( 'hmwp_bruteforce_username' ) ) {
							if ( $error == 'invalid_username' ) {
								$ip = $this->model->brute_get_ip();
								$this->model->block_ip( $ip );
							}
						}
					}
				}
			}
		}

		$response = $this->model->brute_check_loginability();

		if ( is_wp_error( $user ) ) {

			//ignore whitelist ips
			if ( isset( $response['status'] ) && $response['status'] <> 'whitelist' ) {

				//initiate first attempt
				$attempts = ( isset( $response['attempts'] ) ? (int) $response['attempts'] : 0 );

				//show how many attempts remained
				$left = max( ( (int) HMWP_Classes_Tools::getOption( 'brute_max_attempts' ) - $attempts - 1 ), 0 );
				$user = new WP_Error( 'authentication_failed', sprintf( esc_html__( '%sERROR:%s Email or Password is incorrect. %s %d attempts left before lockout', 'hide-my-wp' ), '<strong>', '</strong>', '<br />', $left ) );
			}

		}

		if ( HMWP_Classes_Tools::getOption( 'brute_use_math' ) ) {

			$user = $this->model->brute_math_authenticate( $user, $response );

		} elseif ( HMWP_Classes_Tools::getOption( 'brute_use_captcha' ) || HMWP_Classes_Tools::getOption( 'brute_use_captcha_v3' ) ) {

			$user = $this->model->brute_catpcha_authenticate( $user, $response );

		}

		if ( ! is_wp_error( $user ) ) {
			$this->model->brute_call( 'clear_ip' );
		}

		return $user;
	}

	/**
	 * Records a failed attempt using the brute force protection mechanism.
	 *
	 * @return void
	 */
	function hmwp_failed_attempt() {
		$this->model->brute_call( 'failed_attempt' );
	}

	/**
	 * Updates the trusted IP header to enhance brute force protection.
	 *
	 * This function determines the appropriate trusted IP header by checking the current IP headers and
	 * sets the most reliable one, aiming to prevent IP spoofing and unauthorized access. It ensures only
	 * administrators can update the trusted header.
	 *
	 * @return void
	 */
	public function hmwp_update_trusted_headers() {
		$updated_recently = $this->model->get_transient( 'brute_headers_updated_recently' );

		// Check that current user is admin, so we prevent a lower level user from adding
		// a trusted header, allowing them to brute force an admin account
		if ( ! $updated_recently && current_user_can( 'update_plugins' ) ) {

			$this->model->set_transient( 'brute_headers_updated_recently', 1, DAY_IN_SECONDS );

			$headers        = $this->model->brute_get_headers();
			$trusted_header = 'REMOTE_ADDR';

			if ( count( $headers ) == 1 ) {
				$trusted_header = key( $headers );
			} elseif ( count( $headers ) > 1 ) {
				foreach ( $headers as $header => $ips ) {
					// Explode string into array
					$ips = explode( ', ', $ips );

					$ip_list_has_nonprivate_ip = false;
					foreach ( $ips as $ip ) {
						// Clean the ips
						$ip = $this->model->clean_ip( $ip );

						// If the IP is in a private or reserved range, return REMOTE_ADDR to help prevent spoofing
						if ( $ip == '127.0.0.1' || $ip == '::1' || $this->model->ip_is_private( $ip ) ) {
							continue;
						} else {
							$ip_list_has_nonprivate_ip = true;
							break;
						}
					}

					if ( ! $ip_list_has_nonprivate_ip ) {
						continue;
					}

					// IP is not local, we'll trust this header
					$trusted_header = $header;
					break;
				}
			}
			HMWP_Classes_Tools::saveOptions( 'trusted_ip_header', $trusted_header );
		}
	}

	/**
	 * Clear the block IP table
	 */
	public function clearBlockedIPs() {
		$ips = $this->model->get_blocked_ips();
		if ( ! empty( $ips ) ) {
			foreach ( $ips as $transient => $ip ) {
				$this->model->delete_ip( $transient );
			}
		}
	}

	/**
	 * Validate comments before being submitted in the frontend by not logged-in users
	 *
	 * @param  array  $commentdata  The data of the comment being submitted.
	 *
	 * @return array The validated/filtered comment data.
	 */
	public function hmwp_comments_validation( $commentdata ) {

		//only in frontend for not logged users
		$response = $this->model->brute_check_loginability();

		$error = $errors = false;

		if (HMWP_Classes_Tools::getOption('brute_use_math')) {

			$error = $this->model->brute_math_authenticate($errors, $response);

		} elseif (HMWP_Classes_Tools::getOption('brute_use_captcha') || HMWP_Classes_Tools::getOption('brute_use_captcha_v3')) {

			$error = $this->model->brute_catpcha_authenticate($errors, $response);

		}

		if (is_wp_error($error)) {
			$have_gettext = function_exists( '__' );
			$back_text = $have_gettext ? __( '&laquo; Back' ) : '&laquo; Back';
			wp_die( $error->get_error_message() .  "\n<p><a href='javascript:history.back()'>$back_text</a></p>" );
		}

		return $commentdata;
	}


	/**
	 * Modify the comment form fields to include anti-spam mechanisms based on the plugin settings.
	 *
	 * @param  array  $fields  Existing comment form fields.
	 *
	 * @return array Modified comment form fields.
	 */
	public function hmwp_comments_form_fields( $fields ) {

		$output = false;

		if (HMWP_Classes_Tools::getOption('brute_use_math')) {
			ob_start();
			$this->model->brute_math_form() ;
			$output = '<div class="comment-recaptcha" >'.ob_get_clean().'</p>';
		}elseif (HMWP_Classes_Tools::getOption('brute_use_captcha')) {
			ob_start();
			$this->model->brute_recaptcha_head() . $this->model->brute_recaptcha_form();
			$output = '<div class="comment-recaptcha" >'.ob_get_clean().'</p>';
		}elseif (HMWP_Classes_Tools::getOption('brute_use_captcha_v3')) {
			ob_start();
			$this->model->brute_recaptcha_head_v3() . $this->model->brute_recaptcha_form_v3();
			$output = ob_get_clean();
		}

		if($output){
			$fields['hmwp_recapcha'] = $output;
		}

		return $fields;
	}

}
