<?php
/**
 * Rocketeer
 *
 * @since 1.0.0
 *
 * @package Rocketeer
 * @author Brady Vercher <brady@blazersix.com>
 * @license GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Rocketeer
 * Plugin URI: https://github.com/bradyvercher/rocketeer
 * Description: Commandeer your Jetpack, Rocketeer!
 * Version: 1.0.1
 * Author: Brady Vercher
 * Author URI: http://www.blazersix.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/bradyvercher/rocketeer
 * GitHub Branch: master
 */

/**
 * Only load in the admin.
 */
if ( is_admin() ) {
	include( dirname( __FILE__ ) . '/includes/class-rocketeer.php' );
	add_action( 'plugins_loaded', array( Rocketeer::instance(), 'load' ) );
}
