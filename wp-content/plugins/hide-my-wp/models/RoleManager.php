<?php
/**
 * RoleManager Model
 * Called to handle the RoleManager & Capabilities for the current user
 *
 * @file  The RoleManager Model file
 * @package HMWP/RoleManagerModel
 * @since 5.0.0
 */

class HMWP_Models_RoleManager {

	public $roles;

	public function __construct() {
		add_action( 'admin_init', array( $this, 'addHMWPCaps' ), PHP_INT_MAX );
	}

	/**
	 * Retrieve the HMWP capabilities for a specified role.
	 *
	 * @param  string  $role  The role for which to retrieve capabilities.
	 *
	 * @return array The HMWP capabilities for the specified role, or all capabilities if no role is specified.
	 */
	public function getHMWPCaps( $role = '' ) {
		$caps = array();

		$caps['hmwp_admin'] = array(
			'hmwp_manage_settings' => true,
		);

		$caps = array_filter( $caps );

		if ( isset( $caps[ $role ] ) ) {
			return $caps[ $role ];
		}

		return $caps;
	}

	/**
	 * Add HMWP capabilities to the administrator role
	 *
	 * @return void
	 */
	public function addHMWPCaps() {

		if ( function_exists( 'wp_roles' ) ) {
			$allroles = wp_roles()->get_names();
			if ( ! empty( $allroles ) ) {
				$allroles = array_keys( $allroles );
			}

			if ( ! empty( $allroles ) ) {
				foreach ( $allroles as $role ) {
					if ( $role == 'administrator' ) {
						$this->addHMWPCap( 'hmwp_admin', $role );
					}
				}
			}
		}
	}

	/**
	 * Remove HMWP caps for all roles
	 *
	 * @return void
	 */
	public function removeHMWPCaps() {
		if ( function_exists( 'wp_roles' ) ) {
			$allroles = wp_roles()->get_names();
			$caps     = $this->getHMWPCaps( 'hmwp_admin' );

			if ( ! empty( $allroles ) ) {
				$allroles = array_keys( $allroles );
			}

			if ( ! empty( $allroles ) && ! empty( $caps ) ) {
				foreach ( $allroles as $role ) {
					$this->removeCap( $role, $caps );
				}
			}
		}

	}

	/**
	 * Add the HMWP capabilities to a WordPress role
	 *
	 * @param string $hmwprole The HMWP role identifier
	 * @param string $wprole The WordPress role object
	 *
	 * @return void
	 */
	public function addHMWPCap( $hmwprole, $wprole ) {
		$hmwpcaps = $this->getHMWPCaps( $hmwprole );

		$this->addCap( $wprole, $hmwpcaps );
	}

	/**
	 * Add capabilities to a role
	 *
	 * @param  string  $name  The name of the role.
	 * @param  array  $capabilities  An associative array of capabilities to be added, where the key is the capability and the value is whether it is granted.
	 *
	 * @return void
	 */
	public function addCap( $name, $capabilities ) {
		$role = get_role( $name );

		if ( ! $role || ! method_exists( $role, 'add_cap' ) ) {
			return;
		}

		foreach ( $capabilities as $capability => $grant ) {
			if ( ! $role->has_cap( $capability ) ) {
				$role->add_cap( $capability, $grant );
			}
		}
	}

	/**
	 * Removes specified capabilities from a role.
	 *
	 * @param  string  $name  The name of the role from which capabilities will be removed.
	 * @param  array  $capabilities  An associative array of capabilities to remove.
	 *
	 * @return void
	 */
	public function removeCap( $name, $capabilities ) {
		$role = get_role( $name );

		if ( ! $role || ! method_exists( $role, 'remove_cap' ) ) {
			return;
		}

		foreach ( $capabilities as $capability => $grant ) {
			if ( $role->has_cap( $capability ) ) {
				$role->remove_cap( $capability );
			}
		}
	}


}
