<?php
/**
 * Tool Manager for PS Manager
 * 
 * Manages registration, filtering, and rendering of tools.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class PS_Manager_Tool_Manager {

	/**
	 * Singleton instance
	 */
	private static $instance = null;

	/**
	 * Registered tools
	 */
	private $tools = array();

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->register_built_in_tools();
		
		// Handle settings save (forms post back to same page)
		add_action( 'admin_init', array( $this, 'handle_save' ), 5 );
	}

	/**
	 * Register built-in tools
	 */
	private function register_built_in_tools() {
		// Load tool classes
		require_once PS_UPDATE_MANAGER_DIR . 'includes/tools/class-default-theme-tool.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/tools/class-signup-tos-tool.php';
		require_once PS_UPDATE_MANAGER_DIR . 'includes/tools/class-multisite-privacy-tool.php';

		// Register tools
		$this->register_tool( 'PS_Manager_Default_Theme_Tool' );
		$this->register_tool( 'PS_Manager_Signup_TOS_Tool' );
		$this->register_tool( 'PS_Manager_Multisite_Privacy_Tool' );
	}

	/**
	 * Register a tool
	 */
	public function register_tool( $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			return false;
		}

		$tool = new $class_name();
		$this->tools[ $tool->id ] = $tool;

		return true;
	}

	/**
	 * Get all available tools for current context
	 */
	public function get_available_tools() {
		$available = array();

		foreach ( $this->tools as $tool ) {
			if ( $tool->is_available() ) {
				$available[ $tool->id ] = $tool;
			}
		}

		return $available;
	}

	/**
	 * Get specific tool by ID
	 */
	public function get_tool( $tool_id ) {
		return isset( $this->tools[ $tool_id ] ) ? $this->tools[ $tool_id ] : null;
	}

	/**
	 * Get current active tool ID
	 */
	public function get_current_tool_id() {
		$available = $this->get_available_tools();

		if ( empty( $available ) ) {
			return null;
		}

		// Get from URL parameter
		$current = isset( $_GET['tool'] ) ? sanitize_key( $_GET['tool'] ) : null;

		// Validate it exists and is available
		if ( $current && isset( $available[ $current ] ) ) {
			return $current;
		}

		// Default to first available tool
		$keys = array_keys( $available );
		return reset( $keys );
	}

	/**
	 * Render tools tabs
	 */
	public function render_tabs() {
		$available = $this->get_available_tools();
		$current = $this->get_current_tool_id();

		if ( empty( $available ) ) {
			echo '<p>' . esc_html__( 'Keine Tools verfügbar.', 'ps-manager' ) . '</p>';
			return;
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $available as $tool ) {
			echo $tool->get_tab_html( $tool->id === $current );
		}
		echo '</h2>';
	}

	/**
	 * Render tools panels
	 */
	public function render_panels() {
		$available = $this->get_available_tools();
		$current = $this->get_current_tool_id();

		if ( empty( $available ) ) {
			return;
		}

		foreach ( $available as $tool ) {
			echo $tool->get_panel_html( $tool->id === $current );
		}
	}

	/**
	 * Handle tool settings save
	 */
	public function handle_save() {
		// Only run on POST
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		if ( ! isset( $_POST['ps_manager_tool_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ps_manager_tool_nonce'] ) ), 'ps_manager_tool_save' ) ) {
			return;
		}

		$tool_id = isset( $_POST['tool_id'] ) ? sanitize_key( wp_unslash( $_POST['tool_id'] ) ) : null;

		if ( ! $tool_id ) {
			return;
		}

		$tool = $this->get_tool( $tool_id );

		if ( ! $tool ) {
			return;
		}
		
		if ( ! $tool->is_available() ) {
			return;
		}

		// Call tool's save method
		$result = $tool->save_settings();

		// Redirect back to referring page to prevent resubmit and ensure UI refresh
		$redirect = isset( $_POST['_wp_http_referer'] )
			? wp_sanitize_redirect( wp_unslash( $_POST['_wp_http_referer'] ) )
			: wp_get_referer();

		if ( ! $redirect ) {
			// Fallback: tools page
			$redirect = network_admin_url( 'admin.php?page=ps-update-manager-tools' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}
