<?php
/**
 * CoCart - WooCommerce System Status.
 *
 * Adds additional related information to the WooCommerce System Status.
 *
 * @author   Sébastien Dumont
 * @package  CoCart\Admin\WooCommerce System Status
 * @since    2.1.0
 * @version  3.0.7
 * @license  GPL-2.0+
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CoCart_Admin_WC_System_Status' ) ) {
	class CoCart_Admin_WC_System_Status {

		/**
		 * Constructor
		 *
		 * @access  public
		 * @since   2.1.0
		 * @version 3.0.0
		 */
		public function __construct() {
			// Provide CoCart details to System Status Report.
			if ( ! defined( 'COCART_WHITE_LABEL' ) || false === COCART_WHITE_LABEL ) {
				add_filter( 'woocommerce_system_status_report', array( $this, 'render_system_status_items' ) );
			}

			// Add debug buttons to System Status.
			add_filter( 'woocommerce_debug_tools', array( $this, 'debug_button' ) );

			// Add tools to REST System Status tool.
			add_filter( 'woocommerce_rest_insert_system_status_tool', array( $this, 'maybe_verify_database' ), 10, 2 );
			add_filter( 'woocommerce_rest_insert_system_status_tool', array( $this, 'maybe_update_database' ), 10, 2 );

			if ( defined( 'COCART_WHITE_LABEL' ) && false !== COCART_WHITE_LABEL ) {
				add_filter( 'woocommerce_debug_tools', array( $this, 'cocart_tools' ) );
			}
		} // END __construct()

		/**
		 * Renders the CoCart information in the WC status page.
		 *
		 * @access public
		 */
		public function render_system_status_items() {
			$data = $this->get_system_status_data();

			$system_status_sections = apply_filters(
				'cocart_system_status_sections',
				array(
					array(
						'title'   => 'CoCart',
						/* translators: %s: CoCart */
						'tooltip' => sprintf( esc_html__( 'This section shows any information about %s.', 'cart-rest-api-for-woocommerce' ), 'CoCart' ),
						'data'    => apply_filters( 'cocart_system_status_data', $data ),
					),
				)
			);

			foreach ( $system_status_sections as $section ) {
				$section_title   = $section['title'];
				$section_tooltip = $section['tooltip'];
				$debug_data      = $section['data'];

				include dirname( __FILE__ ) . '/views/html-wc-system-status.php';
			}
		} // END render_system_status_items()

		/**
		 * Gets the system status data to return.
		 *
		 * @access  public
		 * @since   2.1.0
		 * @version 3.0.0
		 * @return  array $data
		 */
		public function get_system_status_data() {
			$data = array();

			$data['cocart_version'] = array(
				'name'      => _x( 'Version', 'label that indicates the version of the plugin', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Version', 'cart-rest-api-for-woocommerce' ),
				'note'      => COCART_VERSION,
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_db_version'] = array(
				'name'      => _x( 'Database Version', 'label that indicates the database version of the plugin', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Database Version', 'cart-rest-api-for-woocommerce' ),
				'note'      => get_option( 'cocart_version', null ),
				'tip'       => sprintf(
					/* translators: 1: CoCart, 2: CoCart Pro */
					esc_html__( 'The version of %1$s that the database is formatted for. This should be the same as your %1$s version. Unless you have %2$s, then it should be the version of %1$s packaged.', 'cart-rest-api-for-woocommerce' ),
					'CoCart',
					'CoCart Pro'
				),
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_install_date'] = array(
				'name'      => _x( 'Install Date', 'label that indicates the install date of the plugin', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Install Date', 'cart-rest-api-for-woocommerce' ),
				'note'      => gmdate( get_option( 'date_format' ), get_option( 'cocart_install_date', time() ) ),
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_carts_in_session'] = array(
				'name'      => _x( 'Carts in Session', 'label that indicates the number of carts in session', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Carts in Session', 'cart-rest-api-for-woocommerce' ),
				'note'      => self::carts_in_session(),
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_carts_active'] = array(
				'name'      => _x( 'Carts Active', 'label that indicates the number of carts active', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Carts Active', 'cart-rest-api-for-woocommerce' ),
				'note'      => sprintf(
					/* translators: 1: Number of active carts, 2: Number of carts in session */
					esc_html__( '%1$d out of %2$d in session.', 'cart-rest-api-for-woocommerce' ),
					self::count_carts_active(),
					self::carts_in_session()
				),
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_carts_expiring_soon'] = array(
				'name'      => _x( 'Carts Expiring Soon', 'label that indicates the number of carts expiring soon', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Carts Expiring Soon', 'cart-rest-api-for-woocommerce' ),
				'note'      => sprintf(
					/* translators: 1: Number of carts expiring, 2: Number of carts in session */
					esc_html__( '%1$d out of %2$d in session.', 'cart-rest-api-for-woocommerce' ),
					self::count_carts_expiring(),
					self::carts_in_session()
				),
				'tip'       => esc_html__( 'Carts that only have less than 6 hours left before they have expired.', 'cart-rest-api-for-woocommerce' ),
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_carts_expired'] = array(
				'name'      => _x( 'Carts Expired', 'label that indicates the number of carts expired', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Carts Expired', 'cart-rest-api-for-woocommerce' ),
				'note'      => sprintf(
					/* translators: 1: Number of expired carts, 2: Number of carts in session */
					esc_html__( '%1$d out of %2$d in session.', 'cart-rest-api-for-woocommerce' ),
					self::count_carts_expired(),
					self::carts_in_session()
				),
				'tip'       => esc_html__( 'Any expired carts that get updated before being cleared will become an active cart again.', 'cart-rest-api-for-woocommerce' ),
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_carts_source_headless'] = array(
				'name'      => _x( 'Carts Source (by CoCart)', 'label that indicates the number of carts created via CoCart API', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Carts Source (by CoCart)', 'cart-rest-api-for-woocommerce' ),
				'note'      => self::carts_source_headless(),
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_carts_source_web'] = array(
				'name'      => _x( 'Carts Source (by Web)', 'label that indicates the number of carts created via the web', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Carts Source (by Web)', 'cart-rest-api-for-woocommerce' ),
				'note'      => self::carts_source_web(),
				'mark'      => '',
				'mark_icon' => '',
			);

			$data['cocart_carts_source_other'] = array(
				'name'      => _x( 'Carts Source (by Other)', 'label that indicates the number of carts created via other source', 'cart-rest-api-for-woocommerce' ),
				'label'     => esc_html__( 'Carts Source (by Other)', 'cart-rest-api-for-woocommerce' ),
				'note'      => self::carts_source_other(),
				'tip'       => sprintf(
					/* translators: 1: CoCart, 2: WooCommerce */
					esc_html__( 'These carts were created other than %1$s or %2$s.', 'cart-rest-api-for-woocommerce' ),
					'CoCart',
					'WooCommerce'
				),
				'mark'      => '',
				'mark_icon' => '',
			);

			return $data;
		} // END get_system_status_data()

		/**
		 * Checks if the session table exists before returning results.
		 * Helps prevents any fatal errors or crashes should debug mode be enabled.
		 *
		 * @access public
		 * @static
		 * @since  3.0.0
		 * @global $wpdb
		 * @return boolean Returns true or false if the session table exists.
		 */
		public static function maybe_show_results() {
			global $wpdb;

			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}cocart_carts';" ) ) {
				return true;
			}

			return false;
		} // END maybe_show_results()

		/**
		 * Counts how many carts are currently in session.
		 *
		 * @access public
		 * @param  string $session - Session table to count.
		 * @global $wpdb
		 * @return int - Number of carts in session.
		 */
		public static function carts_in_session( $session = '' ) {
			global $wpdb;

			if ( ! self::maybe_show_results() ) {
				return __( 'Missing session table.', 'cart-rest-api-for-woocommerce' );
			}

			if ( empty( $session ) ) {
				$results = $wpdb->get_results(
					"
					SELECT COUNT(cart_id) as count 
					FROM {$wpdb->prefix}cocart_carts",
					ARRAY_A
				);
			} else {
				$results = $wpdb->get_results(
					"
					SELECT COUNT(session_id) as count 
					FROM {$wpdb->prefix}woocommerce_sessions",
					ARRAY_A
				);
			}

			return $results[0]['count'];
		} // END carts_in_session()

		/**
		 * Counts how many carts are going to expire within the next 6 hours.
		 *
		 * @access public
		 * @since  2.7.2
		 * @global $wpdb
		 * @return int - Number of carts expiring.
		 */
		public static function count_carts_expiring() {
			global $wpdb;

			if ( ! self::maybe_show_results() ) {
				return 0;
			}

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT COUNT(cart_id) as count
					FROM {$wpdb->prefix}cocart_carts 
					WHERE cart_expiry BETWEEN %d AND %d",
					time(),
					( HOUR_IN_SECONDS * 6 ) + time()
				),
				ARRAY_A
			);

			return $results[0]['count'];
		} // END count_carts_expiring()

		/**
		 * Counts how many carts are active.
		 *
		 * @access public
		 * @since  3.0.0
		 * @global $wpdb
		 * @return int - Number of carts active.
		 */
		public static function count_carts_active() {
			global $wpdb;

			if ( ! self::maybe_show_results() ) {
				return 0;
			}

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT COUNT(cart_id) as count
					FROM {$wpdb->prefix}cocart_carts 
					WHERE cart_expiry > %d",
					time()
				),
				ARRAY_A
			);

			return $results[0]['count'];
		} // END count_carts_active()

		/**
		 * Counts how many carts have expired.
		 *
		 * @access public
		 * @global $wpdb
		 * @return int - Number of carts expired.
		 */
		public static function count_carts_expired() {
			global $wpdb;

			if ( ! self::maybe_show_results() ) {
				return 0;
			}

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT COUNT(cart_id) as count
					FROM {$wpdb->prefix}cocart_carts 
					WHERE cart_expiry < %d",
					time()
				),
				ARRAY_A
			);

			return $results[0]['count'];
		} // END count_carts_expired()

		/**
		 * Counts how many carts were created via the web.
		 *
		 * @access public
		 * @global $wpdb
		 * @return int - Number of carts created via the web.
		 */
		public function carts_source_web() {
			global $wpdb;

			if ( ! self::maybe_show_results() ) {
				return __( 'Missing session table.', 'cart-rest-api-for-woocommerce' );
			}

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT COUNT(cart_id) as count
					FROM {$wpdb->prefix}cocart_carts 
					WHERE cart_source=%s",
					'woocommerce'
				),
				ARRAY_A
			);

			return $results[0]['count'];
		} // END carts_source_web()

		/**
		 * Counts how many carts were created via CoCart API.
		 *
		 * @access public
		 * @global $wpdb
		 * @return int - Number of carts created via CoCart API.
		 */
		public function carts_source_headless() {
			global $wpdb;

			if ( ! self::maybe_show_results() ) {
				return __( 'Missing session table.', 'cart-rest-api-for-woocommerce' );
			}

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT COUNT(cart_id) as count
					FROM {$wpdb->prefix}cocart_carts 
					WHERE cart_source=%s",
					'cocart-rest-api'
				),
				ARRAY_A
			);

			return $results[0]['count'];
		} // END carts_source_web()

		/**
		 * Counts how many carts were the source is other or unknown.
		 *
		 * @access public
		 * @global $wpdb
		 * @return int - Number of carts created via other or unknown.
		 */
		public function carts_source_other() {
			global $wpdb;

			if ( ! self::maybe_show_results() ) {
				return __( 'Missing session table.', 'cart-rest-api-for-woocommerce' );
			}

			$results = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT COUNT(cart_id) as count
					FROM {$wpdb->prefix}cocart_carts 
					WHERE cart_source!=%s AND cart_source!=%s",
					'cocart-rest-api',
					'woocommerce'
				),
				ARRAY_A
			);

			return $results[0]['count'];
		} // END carts_source_other()

		/**
		 * Adds debug buttons under the tools section of WooCommerce System Status.
		 *
		 * @access  public
		 * @since   2.1.0
		 * @version 3.0.0
		 * @param   array $tools - All tools before adding ours.
		 * @return  array $tools - All tools after adding ours.
		 */
		public function debug_button( $tools ) {
			$tools['cocart_clear_carts'] = array(
				'name'     => esc_html__( 'Clear cart sessions', 'cart-rest-api-for-woocommerce' ),
				'button'   => esc_html__( 'Clear all', 'cart-rest-api-for-woocommerce' ),
				'desc'     => sprintf(
					'<strong class="red">%1$s</strong> %2$s',
					esc_html__( 'Note:', 'cart-rest-api-for-woocommerce' ),
					esc_html__( 'This tool will clear all carts in session handled by CoCart and saved carts.', 'cart-rest-api-for-woocommerce' )
				),
				'callback' => array( $this, 'debug_clear_carts' ),
			);

			$tools['cocart_cleanup_carts'] = array(
				'name'     => esc_html__( 'Clear expired carts', 'cart-rest-api-for-woocommerce' ),
				'button'   => esc_html__( 'Clear expired', 'cart-rest-api-for-woocommerce' ),
				'desc'     => sprintf(
					'<strong class="red">%1$s</strong> %2$s',
					esc_html__( 'Note:', 'cart-rest-api-for-woocommerce' ),
					sprintf(
						/* translators: <strong>only</strong>, */
						esc_html__( 'This tool will clear all expired carts %s stored in the database.', 'cart-rest-api-for-woocommerce' ),
						'<strong>' . esc_html__( 'only', 'cart-rest-api-for-woocommerce' ) . '</strong>'
					)
				),
				'callback' => array( $this, 'debug_clear_expired_carts' ),
			);

			$carts_to_sync = self::carts_in_session( 'woocommerce' );

			// Only show synchronize carts option if required.
			if ( $carts_to_sync > 0 ) {
				$tools['cocart_sync_carts'] = array(
					'name'     => esc_html__( 'Synchronize carts', 'cart-rest-api-for-woocommerce' ),
					'button'   => sprintf(
						/* translators: %s: Number of carts to sync */
						esc_html__( 'Synchronize (%d) cart/s', 'cart-rest-api-for-woocommerce' ),
						$carts_to_sync
					),
					'desc'     => sprintf(
						'<strong class="red">%1$s</strong> %2$s',
						esc_html__( 'Note:', 'cart-rest-api-for-woocommerce' ),
						esc_html__( 'This will copy any existing carts from WooCommerce\'s session table to CoCart\'s session table in the database. If cart already exists for a customer then it will not sync for that customer.', 'cart-rest-api-for-woocommerce' )
					),
					'callback' => array( $this, 'synchronize_carts' ),
				);
			} else {
				// Remove option to clear WooCommerce's session table if empty.
				unset( $tools['clear_sessions'] );
			}

			$tools['cocart_update_db'] = array(
				'name'     => esc_html__( 'Update CoCart Database', 'cart-rest-api-for-woocommerce' ),
				'button'   => esc_html__( 'Update Database', 'cart-rest-api-for-woocommerce' ),
				'desc'     => sprintf(
					'<strong class="red">%1$s</strong> %2$s',
					esc_html__( 'Note:', 'cart-rest-api-for-woocommerce' ),
					esc_html__( 'This will update CoCart\'s session table in the database to the latest version. This is only needed to be done if you prefer to update manually or the automatic update failed. Please ensure you make sufficient backups before proceeding.', 'cart-rest-api-for-woocommerce' )
				),
				'callback' => array( $this, 'update_database' ),
			);

			$tools['cocart_verify_db_tables'] = array(
				'name'     => esc_html__( 'Verify CoCart base database tables', 'cart-rest-api-for-woocommerce' ),
				'button'   => esc_html__( 'Verify database', 'cart-rest-api-for-woocommerce' ),
				'desc'     => sprintf(
					'<strong class="red">%1$s</strong> %2$s',
					esc_html__( 'Note:', 'cart-rest-api-for-woocommerce' ),
					esc_html__( 'Verify if all CoCart\'s base database tables are present.', 'cart-rest-api-for-woocommerce' )
				),
				'callback' => array( $this, 'verify_database' ),
			);

			return $tools;
		} // END debug_button

		/**
		 * Modifies the debug buttons under the tools section of
		 * WooCommerce System Status should white labelling is enabled.
		 *
		 * @access public
		 * @since  3.0.0
		 * @param  array $tools - All tools before.
		 * @return array $tools - All tools after modifications.
		 */
		public function cocart_tools( $tools ) {
			unset( $tools['clear_sessions'] );
			unset( $tools['cocart_sync_carts'] );

			$tools['cocart_clear_carts']['desc'] = sprintf(
				'<strong class="red">%1$s</strong> %2$s',
				esc_html__( 'Note:', 'cart-rest-api-for-woocommerce' ),
				esc_html__( 'This tool will clear all carts in session and saved carts.', 'cart-rest-api-for-woocommerce' )
			);

			return $tools;
		} // END cocart_tools()

		/**
		 * Runs the debug callback for clearing all carts.
		 *
		 * @access  public
		 * @since   2.1.0
		 * @version 3.0.0
		 * @return  string
		 */
		public function debug_clear_carts() {
			$results = CoCart_API_Session::clear_carts();

			/* translators: %s: results */
			return sprintf( esc_html__( 'All active carts have been cleared and %s saved carts.', 'cart-rest-api-for-woocommerce' ), absint( $results ) );
		} // END debug_clear_carts()

		/**
		 * Runs the debug callback for clearing expired carts ONLY.
		 *
		 * @access  public
		 * @version 3.0.0
		 * @return  string
		 */
		public function debug_clear_expired_carts() {
			CoCart_API_Session::cleanup_carts();

			return esc_html__( 'All expired carts have now been cleared from the database.', 'cart-rest-api-for-woocommerce' );
		} // END debug_clear_expired_carts()

		/**
		 * Synchronizes the carts from one session table to the other.
		 * Any cart that already exists for the customer will not sync.
		 *
		 * @access  public
		 * @since   2.1.2
		 * @version 3.0.0
		 * @global  object $wpdb
		 * @return  string
		 */
		public function synchronize_carts() {
			global $wpdb;

			$wpdb->query(
				"INSERT INTO {$wpdb->prefix}cocart_carts (`cart_key`, `cart_value`, `cart_expiry`)
				SELECT t1.session_key, t1.session_value, t1.session_expiry
				FROM {$wpdb->prefix}woocommerce_sessions t1
				WHERE NOT EXISTS(SELECT cart_key FROM {$wpdb->prefix}cocart_carts t2 WHERE t2.cart_key = t1.session_key) "
			);

			return esc_html__( 'Carts are now synchronized.', 'cart-rest-api-for-woocommerce' );
		} // END synchronize_carts()

		/**
		 * Maybe updates the database.
		 *
		 * @access public
		 * @since  3.0.0
		 * @param  array $tool - The system tool that is being run.
		 */
		public function maybe_update_database( $tool ) {
			if ( 'cocart_update_db' === $tool['id'] && $tool['success'] ) {
				self::update_database();
			}
		} // END maybe_update_database()

		/**
		 * Updates the database.
		 *
		 * @access public
		 * @since  3.0.0
		 * @return string
		 */
		public function update_database() {
			$blog_id = get_current_blog_id();

			// Used to fire an action added in WP_Background_Process::_construct() that calls WP_Background_Process::handle_cron_healthcheck().
			// This method will make sure the database updates are executed even if cron is disabled. Nothing will happen if the updates are already running.
			do_action( 'wp_' . $blog_id . '_cocart_updater_cron' );

			return esc_html__( 'Database upgrade routine has been scheduled to run in the background.', 'cart-rest-api-for-woocommerce' );
		} // END update_database()

		/**
		 * Maybe verify the database.
		 *
		 * @access public
		 * @since  3.0.0
		 * @param  array $tool - The system tool that is being run.
		 */
		public function maybe_verify_database( $tool ) {
			if ( 'cocart_verify_db_tables' === $tool['id'] && $tool['success'] ) {
				self::verify_database();
			}
		} // END maybe_verify_database()

		/**
		 * Verify the database.
		 *
		 * @access public
		 * @since  3.0.0
		 * @return string
		 */
		public function verify_database() {
			// Try to manually create table again.
			$missing_tables = CoCart_Install::verify_base_tables( true, true );

			if ( 0 === count( $missing_tables ) ) {
				$message = esc_html__( 'Database verified successfully.', 'cart-rest-api-for-woocommerce' );
			} else {
				$message  = esc_html__( 'Verifying database: ', 'cart-rest-api-for-woocommerce' );
				$message .= implode( ', ', $missing_tables );
			}

			return $message;
		} // END verify_database()

	} // END class

} // END if class

return new CoCart_Admin_WC_System_Status();
