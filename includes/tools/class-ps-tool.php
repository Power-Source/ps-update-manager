<?php
/**
 * Abstract Base Class for PS Manager Tools
 * 
 * All tools extend this class and implement the required methods.
 * Tools can be 'network-only' or 'universal'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class PS_Manager_Tool {

	/**
	 * Tool ID/Slug
	 * Must be unique, used for tabs, options keys, etc.
	 */
	public $id = '';

	/**
	 * Tool Display Name
	 */
	public $name = '';

	/**
	 * Tool Description
	 */
	public $description = '';

	/**
	 * Tool Icon (dashicon name without 'dashicons-' prefix)
	 */
	public $icon = 'admin-tools';

	/**
	 * Tool Type: 'network-only' or 'universal'
	 * - network-only: Only visible in network admin
	 * - universal: Available in both network and single-site admin
	 */
	public $type = 'universal';

	/**
	 * Required Capability
	 */
	public $capability = 'manage_options';

	/**
	 * Tool is active/enabled
	 */
	protected $is_active = true;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the tool (hooks, etc.)
	 * Override in child class if needed
	 */
	public function init() {
		// Override in child class
	}

	/**
	 * Render the tool's interface
	 * Must be implemented in child classes
	 */
	abstract public function render();

	/**
	 * Save tool settings
	 * Override in child class if needed
	 */
	public function save_settings() {
		// Override in child class if needed
		return true;
	}

	/**
	 * Check if tool is available for current context
	 */
	public function is_available() {
		// Network-only tools not available in single site admin
		if ( 'network-only' === $this->type && ! is_network_admin() ) {
			return false;
		}

		// Check capability
		if ( ! current_user_can( $this->capability ) ) {
			return false;
		}

		return $this->is_active;
	}

	/**
	 * Get tool tab HTML
	 */
	public function get_tab_html( $active = false ) {
		if ( ! $this->is_available() ) {
			return '';
		}

		$class = $active ? 'nav-tab nav-tab-active' : 'nav-tab';
		$href = add_query_arg( 'tool', $this->id );

		return sprintf(
			'<a href="%s" class="%s"><span class="dashicons dashicons-%s"></span> %s</a>',
			esc_url( $href ),
			esc_attr( $class ),
			esc_attr( $this->icon ),
			esc_html( $this->name )
		);
	}

	/**
	 * Get tool panel wrapper HTML
	 */
	public function get_panel_html( $active = false ) {
		if ( ! $this->is_available() ) {
			return '';
		}

		$style = $active ? '' : 'style="display:none;"';

		ob_start();
		?>
		<div class="ps-manager-tool-panel" id="tool-<?php echo esc_attr( $this->id ); ?>" <?php echo $style; ?>>
			<div class="tool-content">
				<?php $this->render(); ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check if current tool should be displayed
	 * Used by Tool Manager to determine active tab
	 */
	public function is_current_tool( $current_tool_id ) {
		return $this->id === $current_tool_id;
	}
}
