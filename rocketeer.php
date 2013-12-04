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
 * Version: 1.0.0
 * Author: Brady Vercher
 * Author URI: http://www.blazersix.com/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: https://github.com/bradyvercher/rocketeer
 * GitHub Branch: master
 */

/**
 * Main plugin class.
 *
 * @package Rocketeer
 * @author Brady Vercher <brady@blazersix.com>
 * @since 1.0.0
 */
class Rocketeer {
	/**
	 * The main Rocketeer instance.
	 *
	 * @access private
	 * @var Rocketeer
	 */
	private static $instance;

	/**
	 * Main plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Rocketeer
	 */
	public static function instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @access private
	 * @since 1.0.0
	 * @see Rocketeer::instance();
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load' ) );
	}

	/**
	 * Load the plugin if Jetpack is ready.
	 *
	 * @since 1.0.0
	 */
	public function load() {
		// Good luck lifting off...
		if ( ! class_exists( 'Jetpack' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'process_bulk_action' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 1000 );
		add_filter( 'wp_redirect', array( $this, 'intercept_redirects' ) );
		add_action( 'rocketeer_notices', array( $this, 'notices' ) );
	}

	/**
	 * Add the Rocketeer submenu to Jetpack.
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {
		global $menu;

		$hook = add_submenu_page(
			'jetpack',
			__( 'Jetpack Modules', 'rocketeer' ),
			__( 'Modules', 'rocketeer' ),
			'manage_options',
			'rocketeer',
			array( $this, 'display_screen' )
		);

		add_action( 'load-' . $hook, array( $this, 'setup_screen' ) );

		// Clear the Jetpack state so notices don't appear next time the main screen is viewed.
		$jetpack = Jetpack::init();
		add_action( 'load-' . $hook, array( $jetpack, 'admin_page_load' ) );
	}

	/**
	 * Set up the module list screen.
	 *
	 * Includes the required classes, styles, scripts and adds a contextual help tab.
	 *
	 * @since 1.0.0
	 */
	public function setup_screen() {
		// Include the WP_List_Table depedency if it hasn't been loaded.
		if( ! class_exists( 'WP_List_Table' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
		}

		require_once( dirname( __FILE__ ) . '/includes/class-rocketeer-modules-list-table.php' );

		wp_enqueue_style( 'rocketeer', plugin_dir_url( __FILE__ ) . 'assets/styles/rocketeer.css' );
		wp_enqueue_script( 'rocketeer', plugin_dir_url( __FILE__ ) . 'assets/scripts/rocketeer.js' );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
	}

	public function admin_head() {
		?>
		<style type="text/css">
		#icon-jetpack {
			background-image: url(<?php echo plugin_dir_url( JETPACK__PLUGIN_DIR . 'jetpack.php' ); ?>_inc/images/screen-icon.png);
		}
		</style>
		<?php
	}

	/**
	 * Disply the module list.
	 *
	 * @since 1.0.0
	 */
	public function display_screen() {
		$modules_list_table = new Rocketeer_Modules_List_Table();
		$modules_list_table->prepare_items();

		require_once( dirname( __FILE__ ) . '/views/list-modules.php' );
	}

	/**
	 * Process bulk actions.
	 *
	 * @since 1.0.0
	 * @todo Handle cases were errors occur.
	 */
	public function process_bulk_action() {
		$action = self::get_action();
		if ( ! in_array( $action, array( 'activate-modules', 'deactivate-modules' ) ) || empty( $_POST['checked'] ) ) {
			return;
		}

		check_admin_referer( 'bulk-modules' );

		switch ( $action ) {
			case 'activate-modules' :
				foreach ( $_POST['checked'] as $module ) {
					Jetpack::activate_module( $module, false );
				}
				wp_safe_redirect( Rocketeer::admin_url() );
				break;
			case 'deactivate-modules' :
				foreach ( $_POST['checked'] as $module ) {
					Jetpack::deactivate_module( $module );
				}
				wp_safe_redirect( Rocketeer::admin_url() );
				break;
		}
		exit;
	}

	/**
	 * Intercept Jetpack redirects and send back to the modules list page.
	 *
	 * If the 'redirect_to' arg is set to 'rocketeer', the redirect will be hijacked.
	 *
	 * @since 1.0.0
	 *
	 * @param string $location Default redirect location.
	 * @return string
	 */
	public function intercept_redirects( $location ) {
		if ( ! empty( $_REQUEST['redirect_to'] ) && 'rocketeer' == $_REQUEST['redirect_to'] ) {
			$action = self::get_action();
			$args = self::get_context_args();

			if ( 'activate' == $action || 'activate-modules' == $action ) {
				$args['notice'] = 'activated';
			} elseif ( 'deactivate' == $action || 'deactivate-modules' == $action ) {
				$args['notice'] = 'deactivated';
			}

			if ( ! empty( $_GET['module'] ) ) {
				$args['modules'] = $_GET['module'];
			} elseif ( ! empty( $_POST['checked'] ) ) {
				$args['modules'] = implode( ',', $_POST['checked'] );
			}

			$location = Rocketeer::admin_url( $args );
			$location = wp_sanitize_redirect( $location );
		}

		return $location;
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.0.0
	 */
	public function notices() {
		if ( empty( $_GET['notice'] ) || empty( $_GET['modules'] ) ) {
			return;
		}

		$slugs = explode( ',', $_GET['modules'] );
		foreach ( $slugs as $slug ) {
			$modules[ $slug ] = Jetpack::get_module( $slug );
		}

		$class = 'activated' == $_GET['notice'] ? 'updated' : 'error';
		echo '<div id="message" class="' . $class . '"><p>';

			printf( '<strong>%s:</strong> %s',
				'activated' == $_GET['notice'] ? __( 'Activated modules', 'rocketeer' ) : __( 'Deactivated modules', 'rocketeer' ),
				implode( ', ', wp_list_pluck( $modules, 'name' ) )
			);

		echo '</p></div>';
	}

	/**
	 * Get a URL to the Rocketeer screen.
	 *
	 * @since 1.0.0
	 * @uses add_query_arg()
	 *
	 * @param array $args List of args to add to the query string.
	 * @return string
	 */
	public static function admin_url( $args = array() ) {
		$args = array_merge( array( 'page' => 'rocketeer' ), $args );
		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Get the args from the query string that identify the current context.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function get_context_args() {
		return array_intersect_key( $_REQUEST, array_flip( array( 'context', 'order', 'orderby' ) ) );
	}

	/**
	 * Get the current action.
	 *
	 * @since 1.0.0
	 *
	 * @return string|bool
	 */
	public static function get_action() {
		if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
			return $_REQUEST['action'];
		}

		if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
			return $_REQUEST['action2'];
		}

		return false;
	}
}

/**
 * Only load in the admin.
 */
if ( is_admin() ) {
	Rocketeer::instance();
}
