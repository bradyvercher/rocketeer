<?php
/**
 * Modules list table class.
 *
 * @package Rocketeer
 * @since 1.0.0
 */
class Rocketeer_Modules_List_Table extends WP_List_Table {
	/**
	 * List of module data.
	 *
	 * @access private
	 * @var array
	 */
	private $modules = array();

	/**
	 * The current view.
	 *
	 * @access private
	 * @var string
	 */
	private $context = 'available';

	/**
	 * The order of the current view.
	 *
	 * @access private
	 * @var string
	 */
	private $order = 'ASC';

	/**
	 * Column the current view is sorted by.
	 *
	 * @access private
	 * @var string
	 */
	private $orderby = 'name';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @uses Rocketeer_Modules_List_Table::$context
	 * @uses Rocketeer_Modules_List_Table::$order
	 * @uses Rocketeer_Modules_List_Table::$orderby
	 *
	 * @param array $args An associative array with information about the current table.
	 */
	public function __construct( $args = array() ) {
		parent::__construct( array(
			'plural'   => 'modules',
			'singular' => 'module',
			'screen'   => isset( $args['screen'] ) ? $args['screen'] : null,
		) );

		$this->context = empty( $_GET['context'] ) ? 'available' : $_GET['context'];
		$this->order   = ( empty( $_REQUEST['order'] ) || 'asc' == strtolower( $_REQUEST['order'] ) ) ? 'ASC' : 'DESC';
		$this->orderby = 'name';
		if ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) {
			$this->orderby = $_REQUEST['orderby'];
		}
	}

	/**
	 * Get a list of CSS classes for the <table> tag
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_table_classes() {
		return array( 'widefat', $this->_args['plural'], 'plugins' );
	}

	/**
	 * Prepares the list of modules for displaying.
	 *
	 * @since 1.0.0
	 * @uses Rocketeer_Modules_List_Table::$modules
	 * @uses Rocketeer_Modules_List_Table::$items
	 */
	public function prepare_items() {
		// Set up column headers.
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		// Get the modules.
		$available_modules = Jetpack::get_available_modules();
		$active_modules = Jetpack::get_active_modules();

		foreach ( $available_modules as $slug ) {
			$this->modules[ $slug ] = Jetpack::get_module( $slug );
		}

		// Sort the modules.
		uasort( $this->modules, array( $this, '_order_callback' ) );

		// Filter into various contexts.
		$items['all']       = array_keys( $this->modules );
		$items['available'] = array_keys( $this->modules );
		$items['active']    = array_keys( array_intersect_key( $this->modules, array_flip( $active_modules ) ) ); // Sorts active modules by name.
		$items['inactive']  = array_diff( array_keys( $this->modules ), $active_modules );
		$items['free']      = array_keys( wp_list_filter( $this->modules, array( 'free' => true ) ) );
		$items['premium']   = array_keys( wp_list_filter( $this->modules, array( 'free' => false ) ) );

		// Show all modules first if Jetpack is connected or isn't in development mode.
		if ( Jetpack::is_active() || ! Jetpack::is_development_mode() ) {
			$this->context = 'all';
			unset( $items['available'] );
		}

		// Only modules that don't require a connection are available if Jetpack isn't connected or is in development mode.
		if ( ! Jetpack::is_active() && Jetpack::is_development_mode() ) {
			$items['available']           = array_keys( wp_list_filter( $this->modules, array( 'requires_connection' => false ) ) );
			$items['requires_connection'] = array_keys( wp_list_filter( $this->modules, array( 'requires_connection' => true ) ) );
		}

		$this->items = $items;

		$total = count( $items[ $this->context ] );
		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $total,
		) );
	}

	/**
	 * Get an associative array of views available on this table.
	 *
	 * @since 1.0.0
	 * @uses Rocketeer_Modules_List_Table::$items
	 * @uses Rocketeer_Modules_List_Table::$context
	 *
	 * @return array
	 */
	public function get_views() {
		$links = array();

		foreach ( $this->items as $context => $items ) {
			if ( ! $count = count( $items ) ) {
				continue;
			}

			switch ( $context ) {
				case 'available':
					$text = _nx( 'Available <span class="count">(%s)</span>', 'Available <span class="count">(%s)</span>', $count, 'modules', 'rocketeer' );
					break;
				case 'active':
					$text = _nx( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', $count, 'modules', 'rocketeer' );
					break;
				case 'inactive':
					$text = _nx( 'Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>', $count, 'modules', 'rocketeer' );
					break;
				case 'requires_connection':
					$text = _nx( 'Requires Connection <span class="count">(%s)</span>', 'Requires Connection <span class="count">(%s)</span>', $count, 'modules', 'rocketeer' );
					break;
				case 'free':
					$text = _nx( 'Free <span class="count">(%s)</span>', 'Free <span class="count">(%s)</span>', $count, 'modules', 'rocketeer' );
					break;
				case 'premium':
					$text = _nx( 'Premium <span class="count">(%s)</span>', 'Premium <span class="count">(%s)</span>', $count, 'modules', 'rocketeer' );
					break;
				case 'all':
					$text = _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $count, 'modules', 'rocketeer' );
					break;
			}

			$context_url = Rocketeer::admin_url( array( 'context' => $context ) );

			$links[ $context ] = sprintf( '<a href="%s" %s>%s</a>',
				esc_url( $context_url ),
				( $context == $this->context ) ? ' class="current"' : '',
				sprintf( $text, number_format_i18n( $count ) )
			);
		}

		return $links;
	}

	/**
	 * Get an associative array of bulk actions available on this table.
	 *
	 * @since 1.0.0
	 * @uses Rocketeer_Modules_List_Table::$context
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		$actions = array();

		if ( 'active' != $this->context ) {
			$actions['activate-modules'] = $this->screen->in_admin( 'network' ) ? __( 'Network Activate', 'rocketeer' ) : __( 'Activate', 'rocketeer' );
		}

		if ( 'inactive' != $this->context ) {
			$actions['deactivate-modules'] = $this->screen->in_admin( 'network' ) ? __( 'Network Deactivate', 'rocketeer' ) : __( 'Deactivate', 'rocketeer' );
		}

		return $actions;
	}

	/**
	 * Display the bulk actions dropdown.
	 *
	 * @since 1.0.0
	 */
	public function bulk_actions() {
		if ( ! current_user_can( 'manage_options' ) || in_array( $this->context, array( 'requires_connection' ) ) ) {
			return;
		}

		parent::bulk_actions();

		if ( Jetpack::is_development_mode() ) {
			echo '<strong>' . __( 'Development Mode:', 'rocketeer' ) . '</strong> ' . __( 'Yes', 'rocketeer' );
		}
	}

	/**
	 * Get a list of columns. The format is: 'internal-name' => 'Title'
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox">',
			'name'        => __( 'Module', 'rocketeer' ),
			'description' => __( 'Description', 'rocketeer' ),
			'introduced'  => __( 'Introduced', 'rocketeer' ),
		);

		return $columns;
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'name'       => array( 'name', true ),
			'introduced' => array( 'introduced', false ),
		);
	}

	/**
	 * Generate the table rows.
	 *
	 * @since 1.0.0
	 * @uses Rocketeer_Modules_List_Table::$items
	 * @uses Rocketeer_Modules_List_Table::$context
	 */
	public function display_rows() {
		foreach ( $this->items[ $this->context ] as $module ) {
			$this->single_row( $module );
		}
	}

	/**
	 * Display a single row of the table.
	 *
	 * @since 1.0.0
	 * @uses Rocketeer_Modules_List_Table::$modules
	 *
	 * @param string $item The current module slug.
	 */
	public function single_row( $module ) {
		$module_data = $this->modules[ $module ];

		$actions     = array();
		$columns     = $this->get_columns();
		$description = '<p>' . $module_data['description'] . '</p>';

		if ( Jetpack::is_module_active( $module ) ) {
			$class = 'active';

			$toggle_args = array( 'page' => 'jetpack', 'action' => 'deactivate', 'module' => $module, 'redirect_to' => 'rocketeer' );
			$toggle_url = Jetpack::admin_url( array_merge( $toggle_args, Rocketeer::get_context_args() ) );
			$toggle_url = wp_nonce_url( $toggle_url, "jetpack_deactivate-$module" );

			if ( $module_data['deactivate'] && current_user_can( 'manage_options' ) ) {
				$actions['deactivate'] = sprintf( '<a href="%s">%s</a>', $toggle_url, __( 'Deactivate', 'rocketeer' ) );
			}

			if ( current_user_can( 'manage_options' ) && apply_filters( 'jetpack_module_configurable_' . $module, false ) ) {
				$actions['configure'] = '<a href="' . esc_url( Jetpack::module_configuration_url( $module ) ) . '">' . __( 'Configure', 'rocketeer' ) . '</a>';
			}
		} else {
			$class = 'inactive';

			if ( Jetpack::is_active() || ( Jetpack::is_development_mode() && ! $module_data['requires_connection'] ) ) {
				$toggle_args = array( 'page' => 'jetpack', 'action' => 'activate', 'module' => $module, 'redirect_to' => 'rocketeer' );
				$toggle_url = Jetpack::admin_url( array_merge( $toggle_args, Rocketeer::get_context_args() ) );
				$toggle_url = wp_nonce_url( $toggle_url, "jetpack_activate-$module" );

				if ( current_user_can( 'manage_options' ) && apply_filters( 'jetpack_can_activate_' . $module, true ) ) {
					$actions['activate'] = sprintf( '<a href="%s">%s</a>', $toggle_url, __( 'Activate', 'rocketeer' ) );
				}
			} else {
				$class .= ' disabled';
				$actions['inactive'] = '<em>' . __( 'Requires Connection', 'rocketeer' ) . '</em>';
			}
		}

		echo '<tr id="' . sanitize_title( $module_data['name'] ) . '" class="' . $class . '">';

			foreach ( $columns as $column_name => $column_display_name ) {
				switch ( $column_name ) {
					case 'cb':
						echo '<th scope="row" class="check-column">';
							if ( current_user_can( 'manage_options' ) ) {
								$disabled = ! Jetpack::is_active() && ( ! Jetpack::is_development_mode() || $module_data['requires_connection'] );
								$disabled = ! $module_data['deactivate'] || $disabled;

								echo '<label class="screen-reader-text" for="toggle-' . esc_attr( $module ) . '" >' . sprintf( __( 'Select %s' ), $module_data['name'] ) . '</label>';
								echo'<input type="checkbox" name="checked[]" value="' . esc_attr( $module ) . '" id="toggle-' . esc_attr( $module ) . '" ';
									echo disabled( $disabled );
								echo '>';
							}
						echo '</th>';
						break;
					case 'name':
						echo '<td class="plugin-title"><strong>' . $module_data['name'] . '</strong>';
							echo $this->row_actions( $actions, true );
						echo '</td>';
						break;
					case 'description':
						echo '<td class="column-description desc">';
							echo wpautop( $module_data['description'] );
							// var_dump( $module_data );
						echo '</td>';
						break;
					case 'introduced':
						echo '<td class="column-introduced">';
							echo $module_data['introduced'];
						echo '</td>';
						break;
				}
			}

		echo '</tr>';
	}

	/**
	 * Callback to sort modules based on a property.
	 *
	 * @since 1.0.0
	 * @uses Rocketeer_Modules_List_Table::$order
	 * @uses Rocketeer_Modules_List_Table::$orderby
	 *
	 * @param array $module_a Module data.
	 * @param array $module_b Module data.
	 * @return int
	 */
	private function _order_callback( $module_a, $module_b ) {
		$a = $module_a[ $this->orderby ];
		$b = $module_b[ $this->orderby ];

		if ( $a == $b ) {
			return 0;
		}

		if ( 'DESC' == $this->order ) {
			return ( $a < $b ) ? 1 : -1;
		} else {
			return ( $a < $b ) ? -1 : 1;
		}
	}
}
