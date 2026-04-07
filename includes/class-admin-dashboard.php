<?php
/**
 * Admin Dashboard Klasse
 * Erstellt die Verwaltungsseite für PS Update Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PS_Update_Manager_Admin_Dashboard {
	/**
	 * AJAX: Produkt von GitHub installieren
	 */
	public function ajax_install_product() {
		// Sicherheits- und Parameter-Checks
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );
		
		if ( ! PS_Update_Manager_Settings::get_instance()->user_can_access( 'install_products' ) ) {
			wp_send_json_error( __( 'Keine Berechtigung für diese Aktion', 'ps-update-manager' ) );
		}
		
		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$repo = isset( $_POST['repo'] ) ? sanitize_text_field( $_POST['repo'] ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'plugin';

		if ( empty( $slug ) || empty( $repo ) ) {
			wp_send_json_error( __( 'Ungültige Parameter', 'ps-update-manager' ) );
		}

		// SICHERHEIT: Manifest-Validierung - nur erlaubte Repos dürfen installiert werden
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		$official_product = $scanner->get_official_product( $slug );
		if ( ! $official_product || $official_product['repo'] !== $repo ) {
			wp_send_json_error( __( 'Sicherheitsfehler: Produkt nicht im offiziellen Manifest', 'ps-update-manager' ) );
		}

		// Type-Validierung
		if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
			wp_send_json_error( __( 'Ungültiger Produkttyp', 'ps-update-manager' ) );
		}

		// Installationslogik
		$result = $this->install_from_github( $slug, $repo, $type );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			) );
		}

		PS_Update_Manager_Product_Scanner::get_instance()->scan_all();

		wp_send_json_success( array(
			'message' => sprintf( __( '%s erfolgreich installiert!', 'ps-update-manager' ), $slug ),
		) );
	}

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prüft, ob der aktuelle Benutzer Zugriff auf die Admin-Seiten hat
	 * Handhabt Single-Site und Network-Admin korrekt.
	 *
	 * @return bool
	 */
	private function current_user_can_access() {
		if ( is_network_admin() ) {
			return current_user_can( 'manage_network_options' );
		}
		return current_user_can( 'manage_options' );
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'add_network_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_legacy_products_page' ) );
		add_action( 'admin_init', array( $this, 'cleanup_orphaned_products' ) );

		// AJAX Handlers
		add_action( 'wp_ajax_ps_force_update_check', array( $this, 'ajax_force_update_check' ) );
		add_action( 'wp_ajax_ps_install_product', array( $this, 'ajax_install_product' ) );
		add_action( 'wp_ajax_ps_test_github_api', array( $this, 'ajax_test_github_api' ) );
		add_action( 'wp_ajax_ps_load_products', array( $this, 'ajax_load_products' ) );
		add_action( 'wp_ajax_ps_get_categories', array( $this, 'ajax_get_categories' ) );
		add_action( 'wp_ajax_ps_deactivate_plugin', array( $this, 'ajax_deactivate_plugin' ) );
		add_action( 'wp_ajax_ps_activate_plugin', array( $this, 'ajax_activate_plugin' ) );
		add_action( 'wp_ajax_ps_update_product', array( $this, 'ajax_update_product' ) );
		add_action( 'wp_ajax_ps_community_pulse', array( $this, 'ajax_community_pulse' ) );
	}

	/**
	 * Admin Assets einbinden
	 *
	 * @param string $hook Aktueller Hook der Admin-Seite
	 */
	public function enqueue_assets( $hook = '' ) {
		// Nur auf unseren Plugin-Seiten laden
		$pages = array(
			'toplevel_page_ps-update-manager',
			'ps-update-manager_page_ps-update-manager',
			'ps-update-manager_page_ps-update-manager-psources',
			'ps-update-manager_page_ps-update-manager-tools',
			'ps-update-manager_page_ps-update-manager-settings',
		);

		// Fallback: prüfe ?page= Parameter, falls Hook nicht passt
		$current_page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
		$our_pages    = array(
			'ps-update-manager',
			'ps-update-manager-psources',
			'ps-update-manager-tools',
			'ps-update-manager-settings',
		);

		if ( ! in_array( $hook, $pages, true ) && ! in_array( $current_page, $our_pages, true ) ) {
			return;
		}

		$base_url = plugin_dir_url( dirname( __FILE__ ) );

		// Styles
		wp_enqueue_style( 'ps-update-manager-admin', $base_url . 'assets/css/admin.css', array(), '1.0.0' );
		
		// PSources Katalog CSS (nur auf der PSources-Seite)
		if ( in_array( $current_page, array( 'ps-update-manager-psources' ), true ) ) {
			wp_enqueue_style( 'ps-catalog', $base_url . 'assets/css/psources-catalog.css', array(), '1.0.0' );
		}
		
		// Settings CSS (Settings + Tools Seiten)
		if ( in_array( $current_page, array( 'ps-update-manager-settings', 'ps-update-manager-tools' ), true ) ) {
			wp_enqueue_style( 'ps-settings', $base_url . 'assets/css/settings.css', array(), '1.0.0' );
		}

		// Scripts
		wp_enqueue_script( 'ps-update-manager-admin', $base_url . 'assets/js/admin.js', array( 'jquery' ), '1.0.0', true );
		
		// PSources Katalog Script (nur auf der PSources-Seite)
		if ( in_array( $current_page, array( 'ps-update-manager-psources' ), true ) ) {
			wp_enqueue_script( 'ps-catalog', $base_url . 'assets/js/psources-catalog.js', array( 'jquery' ), '1.0.0', true );
		}

		// Lokalisierung / Nonces für AJAX
		wp_localize_script( 'ps-update-manager-admin', 'PSUpdateManager', array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'ps_update_manager_nonce' ),
			'networkAdmin'     => is_network_admin(),
			'forceCheckAction' => 'ps_force_update_check',
			'installAction'    => 'ps_install_product',
			'testGithub'       => 'ps_test_github_api',
			'strings'          => array(
				'checking' => __( 'Wird geprüft...', 'ps-update-manager' ),
				'success'  => __( 'Erfolgreich!', 'ps-update-manager' ),
				'error'    => __( 'Ein Fehler ist aufgetreten', 'ps-update-manager' ),
			),
		) );
	}

	/**
	 * Netzwerk-Menü hinzufügen (Multisite)
	 */
	public function add_network_menu() {
		if ( ! is_multisite() || ! is_super_admin() ) {
			return;
		}

		add_menu_page(
			__( 'PSOURCE Manager', 'ps-update-manager' ),
			__( 'PS MANAGER', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager',
			array( $this, 'render_dashboard' ),
			'dashicons-update',
			59
		);

		add_submenu_page(
			'ps-update-manager',
			__( 'Dashboard', 'ps-update-manager' ),
			__( 'Dashboard', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'ps-update-manager',
			__( 'PSOURCE Katalog', 'ps-update-manager' ),
			__( 'PSOURCE', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager-psources',
			array( $this, 'render_products' )
		);

		add_submenu_page(
			'ps-update-manager',
			__( 'Tools', 'ps-update-manager' ),
			__( 'Tools', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager-tools',
			array( $this, 'render_tools' )
		);

		add_submenu_page(
			'ps-update-manager',
			__( 'Einstellungen', 'ps-update-manager' ),
			__( 'Einstellungen', 'ps-update-manager' ),
			'manage_network_options',
			'ps-update-manager-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Legacy-Redirect für alte Produkte-Seite
	 */
	public function maybe_redirect_legacy_products_page() {
		if ( ! is_admin() ) {
			return;
		}
		if ( isset( $_GET['page'] ) && 'ps-update-manager-products' === sanitize_key( $_GET['page'] ) ) {
			$target = is_network_admin()
				? network_admin_url( 'admin.php?page=ps-update-manager-psources' )
				: admin_url( 'admin.php?page=ps-update-manager-psources' );
			wp_safe_redirect( $target );
			exit;
		}
	}

	/**
	 * Orphaned Produkte aus Registry bereinigen
	 */
	public function cleanup_orphaned_products() {
		$registry = PS_Update_Manager_Product_Registry::get_instance();
		$scanner  = PS_Update_Manager_Product_Scanner::get_instance();

		$official_products = $scanner->get_official_products();
		$official_slugs    = array_keys( $official_products );

		$all_products = $registry->get_all();
		foreach ( $all_products as $slug => $product ) {
			if ( ! in_array( $slug, $official_slugs, true ) && ! $product['is_active'] ) {
				$registry->unregister( $slug );
			}
		}
	}

	/**
	 * Menü hinzufügen (Normal/Einzelsite)
	 */
	public function add_menu() {
		// Nur für Single-Site Admin, Multisite nutzt network_admin_menu
		if ( is_multisite() ) {
			return;
		}

		if ( ! $this->current_user_can_access() ) {
			return;
		}

		add_menu_page(
			__( 'PSOURCE Manager', 'ps-update-manager' ),
			__( 'PS MANAGER', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager',
			array( $this, 'render_dashboard' ),
			'dashicons-update',
			59
		);

		add_submenu_page(
			'ps-update-manager',
			__( 'Dashboard', 'ps-update-manager' ),
			__( 'Dashboard', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'ps-update-manager',
			__( 'PSOURCE Katalog', 'ps-update-manager' ),
			__( 'PSOURCE', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager-psources',
			array( $this, 'render_products' )
		);

		add_submenu_page(
			'ps-update-manager',
			__( 'Tools', 'ps-update-manager' ),
			__( 'Tools', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager-tools',
			array( $this, 'render_tools' )
		);

		add_submenu_page(
			'ps-update-manager',
			__( 'Einstellungen', 'ps-update-manager' ),
			__( 'Einstellungen', 'ps-update-manager' ),
			'manage_options',
			'ps-update-manager-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Handle-Funktion für Einstellungen-Speicherung
	 */
	public function handle_settings_save() {
		if ( ! is_multisite() || ! is_super_admin() ) {
			return;
		}

		if ( ! isset( $_POST['ps_update_manager_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ps_update_manager_settings_nonce'] ) ), 'ps_update_manager_settings' ) ) {
			return;
		}

		$settings = PS_Update_Manager_Settings::get_instance();
		
		// Alle Berechtigungs-einstellungen verarbeiten
		$capability_settings = array(
			'allowed_roles',
			'catalog_roles',
			'check_updates_roles',
			'install_roles',
			'update_roles',
			'manage_plugins_roles',
			'manage_themes_roles',
			'network_tools_roles',
			'manage_tos_roles',
			'manage_settings_roles',
			'test_api_roles',
		);

		foreach ( $capability_settings as $setting_key ) {
			$post_key = 'ps_update_manager_' . $setting_key;
			
			if ( isset( $_POST[ $post_key ] ) ) {
				// Rollen als Array behandeln und sanitizen
				$roles = array_map( 'sanitize_key', wp_unslash( (array) $_POST[ $post_key ] ) );
				$settings->update_setting( $setting_key, $roles );
			} else {
				// Wenn keine Rollen ausgewählt, auf leeres Array setzen
				$settings->update_setting( $setting_key, array() );
			}
		}

		// Erfolg-Nachricht hinzufügen
		add_action( 'admin_notices', array( $this, 'settings_saved_notice' ) );
	}

	/**
	 * Erfolgs-Nachricht anzeigen
	 */
	public function settings_saved_notice() {
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Berechtigungseinstellungen erfolgreich gespeichert.', 'ps-update-manager' ); ?></p>
		</div>
		<?php
	}
	
	/**
	 * Dashboard rendern
	 */
	/**
	 * AJAX: Community Pulse – aggregierte GitHub-Stats aller installierten Repos
	 * Gecacht 24h als kombiniertes Transient um Rate-Limits zu schonen
	 */
	public function ajax_community_pulse() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! $this->current_user_can_access() ) {
			wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
		}

		$cache_key = 'ps_community_pulse_v2';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			wp_send_json_success( $cached );
		}

		// Manifest direkt nutzen – enthält alle PSOURCE Repos zuverlässig
		$manifest_file = PS_UPDATE_MANAGER_DIR . 'includes/products-manifest.php';
		$manifest      = file_exists( $manifest_file ) ? include $manifest_file : array();
		$github        = PS_Update_Manager_GitHub_API::get_instance();

		$active_30d    = 0;
		$latest_ts     = 0;
		$latest_repo   = '';
		$repos_fetched = 0;
		$limit         = 10; // max. Repos pro Aufruf um Rate-Limit zu schonen
		$cutoff_30d    = current_time( 'timestamp' ) - ( 30 * DAY_IN_SECONDS );

		foreach ( $manifest as $slug => $product ) {
			if ( empty( $product['repo'] ) ) {
				continue;
			}
			if ( $repos_fetched >= $limit ) {
				break;
			}

			$info = $github->get_repo_info( $product['repo'] );
			if ( is_wp_error( $info ) || ! is_array( $info ) ) {
				continue;
			}

			if ( ! empty( $info['updated_at'] ) ) {
				$ts = strtotime( $info['updated_at'] );
				if ( $ts >= $cutoff_30d ) {
					$active_30d++;
				}
				if ( $ts > $latest_ts ) {
					$latest_ts   = $ts;
					$latest_repo = $product['name'];
				}
			}

			$repos_fetched++;
		}

		$data = array(
			'active_30d'   => $active_30d,
			'repos_fetched'=> $repos_fetched,
			'latest_repo'  => $latest_repo,
			'latest_ago'   => $latest_ts > 0 ? human_time_diff( $latest_ts, current_time( 'timestamp' ) ) : '',
		);

		// 24h cachen
		set_transient( $cache_key, $data, 24 * HOUR_IN_SECONDS );

		wp_send_json_success( $data );
	}

	/**
	 * Ecosystem Stacks definieren – zielorientierte Plugin-Kombinationen
	 */
	private function get_ecosystem_stacks() {
		return array(
			'shop' => array(
				'title' => __( 'Online-Shop', 'ps-update-manager' ),
				'desc'  => __( 'Vollständiger E-Commerce mit DSGVO, Mitgliedschaften & CRM', 'ps-update-manager' ),
				'icon'  => 'dashicons-cart',
				'color' => '#0073aa',
				'slugs' => array( 'marketpress', 'ps-dsgvo', 'ps-mitgliedschaften', 'ps-smart-crm', 'affiliate' ),
			),
			'community' => array(
				'title' => __( 'Community-Plattform', 'ps-update-manager' ),
				'desc'  => __( 'Soziales Netzwerk, private Nachrichten, Wiki & Chat', 'ps-update-manager' ),
				'icon'  => 'dashicons-groups',
				'color' => '#00a32a',
				'slugs' => array( 'ps-community', 'private-messaging', 'ps-mitgliedschaften', 'ps-wiki', 'ps-chat' ),
			),
			'education' => array(
				'title' => __( 'Bildungsplattform', 'ps-update-manager' ),
				'desc'  => __( 'Online-Kurse, Mitgliedschaften & Terminverwaltung', 'ps-update-manager' ),
				'icon'  => 'dashicons-welcome-learn-more',
				'color' => '#8c00d4',
				'slugs' => array( 'coursepress', 'ps-mitgliedschaften', 'marketpress', 'terminmanager', 'e-newsletter' ),
			),
			'business' => array(
				'title' => __( 'Business Suite', 'ps-update-manager' ),
				'desc'  => __( 'CRM, Termine, Newsletter & Support auf einer Plattform', 'ps-update-manager' ),
				'icon'  => 'dashicons-businessperson',
				'color' => '#b26900',
				'slugs' => array( 'ps-smart-crm', 'terminmanager', 'private-messaging', 'ps-support', 'e-newsletter' ),
			),
			'network' => array(
				'title' => __( 'Multisite Netzwerk', 'ps-update-manager' ),
				'desc'  => __( 'Bloghosting, Cloner, Netzwerk-Index & Reader', 'ps-update-manager' ),
				'icon'  => 'dashicons-networking',
				'color' => '#d63638',
				'slugs' => array( 'ps-bloghosting', 'ps-cloner', 'ps-postindexer', 'msreader', 'easyblogging' ),
			),
		);
	}

	/**
	 * Empfehlungen aus compatible_with im Manifest generieren
	 *
	 * @param array $installed_slugs Liste der installierten Produkt-Slugs
	 * @return array
	 */
	private function get_recommended_products( $installed_slugs ) {
		$scanner  = PS_Update_Manager_Product_Scanner::get_instance();
		$official = $scanner->get_official_products();

		$recommendations = array();
		foreach ( $installed_slugs as $slug ) {
			if ( ! isset( $official[ $slug ] ) ) {
				continue;
			}
			$manifest = $official[ $slug ];
			if ( empty( $manifest['compatible_with'] ) ) {
				continue;
			}
			foreach ( $manifest['compatible_with'] as $rec_slug => $reason ) {
				if ( in_array( $rec_slug, $installed_slugs, true ) ) {
					continue;
				}
				if ( ! isset( $official[ $rec_slug ] ) ) {
					continue;
				}
				if ( ! isset( $recommendations[ $rec_slug ] ) ) {
					$rec_repo = $official[ $rec_slug ]['repo'] ?? '';
					$recommendations[ $rec_slug ] = array(
						'name'        => $official[ $rec_slug ]['name'],
						'reason'      => $reason,
						'logo_url'    => $this->get_product_logo_url( $rec_slug, $rec_repo ),
						'repo'        => $rec_repo,
						'type'        => $official[ $rec_slug ]['type'],
						'from'        => $official[ $slug ]['name'],
						'count'       => 1,
					);
				} else {
					$recommendations[ $rec_slug ]['count']++;
					$recommendations[ $rec_slug ]['from'] .= ', ' . $official[ $slug ]['name'];
				}
			}
		}

		uasort( $recommendations, function( $a, $b ) { return $b['count'] - $a['count']; } );

		return array_slice( $recommendations, 0, 6, true );
	}

	public function render_dashboard() {
		// Zugriffsprüfung
		if ( ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}

		// Scan nur bei abgelaufenem Cache
		$scanner   = PS_Update_Manager_Product_Scanner::get_instance();
		$last_scan = get_transient( 'ps_last_scan_time' );
		if ( ! $last_scan ) {
			$scanner->scan_all();
			$last_scan = get_transient( 'ps_last_scan_time' );
		}

		$products          = PS_Update_Manager_Product_Registry::get_instance()->get_all();
		$updates_available = $this->count_available_updates( $products );
		$active_count      = $this->count_active( $products );
		$total_count       = count( $products );
		$installed_slugs   = array_keys( $products );

		$stacks      = $this->get_ecosystem_stacks();
		$recommended = $this->get_recommended_products( $installed_slugs );
		$official    = $scanner->get_official_products();

		$_wp_update_plugins = get_site_transient( 'update_plugins' );
		$_wp_update_themes  = get_site_transient( 'update_themes' );

		$catalog_url = is_network_admin()
			? network_admin_url( 'admin.php?page=ps-update-manager-psources' )
			: admin_url( 'admin.php?page=ps-update-manager-psources' );

		$tools_url = is_network_admin()
			? network_admin_url( 'admin.php?page=ps-update-manager-tools' )
			: admin_url( 'admin.php?page=ps-update-manager-tools' );
		$settings_url = is_network_admin()
			? network_admin_url( 'admin.php?page=ps-update-manager-settings' )
			: admin_url( 'admin.php?page=ps-update-manager-settings' );

		$tool_manager    = PS_Manager_Tool_Manager::get_instance();
		$available_tools = $tool_manager->get_available_tools();

		?>
		<div class="wrap ps-update-manager-dashboard">

			<!-- =================== HERO =================== -->
			<div class="ps-hero">
				<div class="ps-hero-body">
					<div class="ps-hero-left">
						<div class="ps-hero-top">
							<div class="ps-hero-identity">
								<span class="dashicons dashicons-update ps-hero-icon"></span>
								<div>
									<h1><?php esc_html_e( 'PSOURCE Manager', 'ps-update-manager' ); ?></h1>
									<p class="ps-hero-sub"><?php esc_html_e( 'Dein ClassicPress Ökosystem – Open Source, Deutsch, vollständig.', 'ps-update-manager' ); ?></p>
								</div>
							</div>
							<div class="ps-hero-btns">
								<button type="button" id="ps-force-check" class="button button-primary">
									<span class="dashicons dashicons-update"></span>
									<?php esc_html_e( 'Updates prüfen', 'ps-update-manager' ); ?>
								</button>
								<a href="<?php echo esc_url( $catalog_url ); ?>" class="button">
									<span class="dashicons dashicons-store"></span>
									<?php esc_html_e( 'Alle PSOURCE entdecken', 'ps-update-manager' ); ?>
								</a>
							</div>
						</div>

						<div class="ps-hero-stats">
							<div class="ps-stat-tile">
								<span class="dashicons dashicons-admin-plugins ps-stat-icon"></span>
								<span class="ps-stat-num"><?php echo intval( $total_count ); ?></span>
								<span class="ps-stat-lbl"><?php esc_html_e( 'Installiert', 'ps-update-manager' ); ?></span>
							</div>
							<div class="ps-stat-tile<?php echo $active_count > 0 ? ' ps-stat-green' : ''; ?>">
								<span class="dashicons dashicons-yes-alt ps-stat-icon"></span>
								<span class="ps-stat-num"><?php echo intval( $active_count ); ?></span>
								<span class="ps-stat-lbl"><?php esc_html_e( 'Aktiv', 'ps-update-manager' ); ?></span>
							</div>
							<div class="ps-stat-tile<?php echo $updates_available > 0 ? ' ps-stat-orange' : ''; ?>">
								<span class="dashicons dashicons-update-alt ps-stat-icon"></span>
								<span class="ps-stat-num"><?php echo intval( $updates_available ); ?></span>
								<span class="ps-stat-lbl"><?php esc_html_e( 'Updates', 'ps-update-manager' ); ?></span>
							</div>
							<?php if ( ! empty( $recommended ) ) :
								$top_recommendations = array_slice( $recommended, 0, 3, true );
							?>
							<div class="ps-stat-tile ps-stat-reco">
								<div class="ps-reco-tile-head">
									<span class="ps-reco-tile-badge"><?php esc_html_e( 'Tipps fuer dein Setup', 'ps-update-manager' ); ?></span>
								</div>
								<div class="ps-reco-tile-grid">
									<?php foreach ( $top_recommendations as $r_slug => $reco ) : ?>
										<a href="<?php echo esc_url( $catalog_url ); ?>" class="ps-reco-tile-item" title="<?php echo esc_attr( $reco['reason'] ); ?>">
											<img src="<?php echo esc_url( $reco['logo_url'] ); ?>" alt="" class="ps-reco-tile-logo">
											<div class="ps-reco-tile-info">
												<strong><?php echo esc_html( $reco['name'] ); ?></strong>
												<span class="ps-reco-tile-reason"><?php echo esc_html( $reco['reason'] ); ?></span>
											</div>
										</a>
									<?php endforeach; ?>
								</div>
							</div>
							<?php endif; ?>
						</div>

						<div class="ps-hero-footer">
							<?php if ( $last_scan ) : ?>
								<span class="ps-hero-scan">
									<span class="dashicons dashicons-clock"></span>
									<?php printf(
										esc_html__( 'Letzter Scan vor %s', 'ps-update-manager' ),
										human_time_diff( $last_scan, current_time( 'timestamp' ) )
									); ?>
								</span>
							<?php endif; ?>
						</div>
					</div><!-- .ps-hero-left -->

				</div><!-- .ps-hero-body -->
			</div>

			<?php if ( $updates_available > 0 ) : ?>
				<div class="ps-alert ps-alert-update">
					<span class="dashicons dashicons-update-alt"></span>
					<div>
						<strong><?php printf(
							esc_html( _n( '%d Update verfügbar', '%d Updates verfügbar', $updates_available, 'ps-update-manager' ) ),
							intval( $updates_available )
						); ?></strong>
						<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="button button-small" style="margin-left:12px;">
							<?php esc_html_e( 'Jetzt aktualisieren', 'ps-update-manager' ); ?>
						</a>
					</div>
				</div>
			<?php endif; ?>

			<!-- =================== SCHNELLZUGRIFF =================== -->
			<section class="ps-section">
				<div class="ps-section-header">
					<h2><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Schnellzugriff', 'ps-update-manager' ); ?></h2>
					<p><?php esc_html_e( 'Direkte Shortcuts zu Tools, Einstellungen und wichtigen Aktionen.', 'ps-update-manager' ); ?></p>
				</div>
				<div class="ps-quick-grid">

					<a href="<?php echo esc_url( $catalog_url ); ?>" class="ps-quick-card ps-quick-catalog">
						<span class="dashicons dashicons-store ps-quick-icon"></span>
						<div class="ps-quick-info">
							<strong><?php esc_html_e( 'PSOURCE Katalog', 'ps-update-manager' ); ?></strong>
							<span><?php printf( esc_html__( '%d weitere Plugins verfügbar', 'ps-update-manager' ), max( 0, count( $official ) - $total_count ) ); ?></span>
						</div>
						<span class="dashicons dashicons-arrow-right-alt2 ps-quick-arrow"></span>
					</a>

					<?php foreach ( $available_tools as $tool ) :
						$tool_url = ( is_network_admin()
							? network_admin_url( 'admin.php?page=ps-update-manager-tools' )
							: admin_url( 'admin.php?page=ps-update-manager-tools' )
						) . '&tool=' . urlencode( $tool->id );
					?>
						<a href="<?php echo esc_url( $tool_url ); ?>" class="ps-quick-card ps-quick-tool">
							<span class="dashicons dashicons-<?php echo esc_attr( $tool->icon ); ?> ps-quick-icon"></span>
							<div class="ps-quick-info">
								<strong><?php echo esc_html( $tool->name ); ?></strong>
								<span><?php echo esc_html( wp_trim_words( $tool->description, 8 ) ); ?></span>
							</div>
							<span class="dashicons dashicons-arrow-right-alt2 ps-quick-arrow"></span>
						</a>
					<?php endforeach; ?>

					<a href="<?php echo esc_url( $settings_url ); ?>" class="ps-quick-card ps-quick-settings">
						<span class="dashicons dashicons-admin-generic ps-quick-icon"></span>
						<div class="ps-quick-info">
							<strong><?php esc_html_e( 'Einstellungen', 'ps-update-manager' ); ?></strong>
							<span><?php esc_html_e( 'Berechtigungen und Systemoptionen', 'ps-update-manager' ); ?></span>
						</div>
						<span class="dashicons dashicons-arrow-right-alt2 ps-quick-arrow"></span>
					</a>

				</div>
			</section>

			<!-- =================== STACKS =================== -->
			<section class="ps-section">
				<div class="ps-section-header">
					<h2><span class="dashicons dashicons-layout"></span> <?php esc_html_e( 'Was willst du bauen?', 'ps-update-manager' ); ?></h2>
					<p><?php esc_html_e( 'Fertige Plugin-Kombis für dein Ziel. Grün = bereits installiert.', 'ps-update-manager' ); ?></p>
				</div>
				<div class="ps-stacks-grid">
					<?php foreach ( $stacks as $stack_id => $stack ) :
						$stack_slugs        = $stack['slugs'];
						$installed_in_stack = array_intersect( $stack_slugs, $installed_slugs );
						$count_in           = count( $installed_in_stack );
						$count_total        = count( $stack_slugs );
						$pct                = (int) round( ( $count_in / $count_total ) * 100 );
					?>
						<div class="ps-stack-card" style="--stack-color: <?php echo esc_attr( $stack['color'] ); ?>">
							<div class="ps-stack-head">
								<span class="ps-stack-icon dashicons <?php echo esc_attr( $stack['icon'] ); ?>"></span>
								<div class="ps-stack-info">
									<h3><?php echo esc_html( $stack['title'] ); ?></h3>
									<p><?php echo esc_html( $stack['desc'] ); ?></p>
								</div>
								<div class="ps-stack-counter">
									<span class="ps-stack-num"><?php echo intval( $count_in ); ?></span>
									<span class="ps-stack-of">/<?php echo intval( $count_total ); ?></span>
								</div>
							</div>
							<div class="ps-stack-bar-wrap">
								<div class="ps-stack-bar" style="width: <?php echo intval( $pct ); ?>%"></div>
							</div>
							<div class="ps-stack-tags">
								<?php foreach ( $stack_slugs as $s_slug ) :
									$is_in  = in_array( $s_slug, $installed_slugs, true );
									$s_name = isset( $official[ $s_slug ]['name'] ) ? $official[ $s_slug ]['name'] : $s_slug;
								?>
									<span class="ps-stag <?php echo $is_in ? 'ps-stag-in' : 'ps-stag-out'; ?>">
										<?php if ( $is_in ) : ?>
											<span class="dashicons dashicons-yes"></span>
										<?php endif; ?>
										<?php echo esc_html( $s_name ); ?>
									</span>
								<?php endforeach; ?>
							</div>
							<?php if ( $count_in < $count_total ) : ?>
								<a href="<?php echo esc_url( $catalog_url ); ?>" class="ps-stack-cta">
									<?php esc_html_e( 'Stack vervollständigen', 'ps-update-manager' ); ?>
									<span class="dashicons dashicons-arrow-right-alt2"></span>
								</a>
							<?php else : ?>
								<span class="ps-stack-done">
									<span class="dashicons dashicons-awards"></span>
									<?php esc_html_e( 'Stack vollständig!', 'ps-update-manager' ); ?>
								</span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>



			<!-- =================== INSTALLIERTE PRODUKTE =================== -->
			<section class="ps-section">
				<div class="ps-section-header">
					<h2><span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e( 'Installierte PSOURCE', 'ps-update-manager' ); ?></h2>
					<p><?php printf( esc_html__( '%d Plugins & Themes auf dieser Installation.', 'ps-update-manager' ), intval( $total_count ) ); ?></p>
				</div>
				<div class="ps-installed-grid">
					<?php foreach ( $products as $product ) :
						$has_update  = false;
						$new_version = '';
						if ( 'plugin' === $product['type'] && ! empty( $product['basename'] ) && is_object( $_wp_update_plugins ) && isset( $_wp_update_plugins->response[ $product['basename'] ] ) ) {
							$has_update  = true;
							$new_version = $_wp_update_plugins->response[ $product['basename'] ]->new_version ?? '';
						} elseif ( 'theme' === $product['type'] && ! empty( $product['slug'] ) && is_object( $_wp_update_themes ) && isset( $_wp_update_themes->response[ $product['slug'] ] ) ) {
							$has_update  = true;
							$new_version = $_wp_update_themes->response[ $product['slug'] ]['new_version'] ?? '';
						}
						$is_net_active = is_multisite() && $product['is_active'] && ! empty( $product['basename'] ) && is_plugin_active_for_network( $product['basename'] );
					?>
						<div class="ps-inst-card<?php echo $has_update ? ' ps-inst-update' : ''; ?>">
							<div class="ps-inst-top">
								<div class="ps-inst-name">
									<span class="dashicons dashicons-<?php echo 'plugin' === $product['type'] ? 'admin-plugins' : 'admin-appearance'; ?>"></span>
									<?php echo esc_html( $product['name'] ); ?>
								</div>
								<span class="ps-inst-version">v<?php echo esc_html( $product['version'] ); ?></span>
							</div>
							<div class="ps-inst-meta">
								<?php if ( $has_update ) : ?>
									<span class="ps-badge ps-badge-update">
										<span class="dashicons dashicons-update-alt"></span>
										v<?php echo esc_html( $new_version ?: '?' ); ?>
									</span>
								<?php endif; ?>
								<?php if ( $is_net_active ) : ?>
									<span class="ps-badge ps-badge-network">
										<span class="dashicons dashicons-networking"></span>
										<?php esc_html_e( 'Netzwerk', 'ps-update-manager' ); ?>
									</span>
								<?php elseif ( $product['is_active'] ) : ?>
									<span class="ps-badge ps-badge-active"><?php esc_html_e( 'Aktiv', 'ps-update-manager' ); ?></span>
								<?php else : ?>
									<span class="ps-badge ps-badge-inactive"><?php esc_html_e( 'Inaktiv', 'ps-update-manager' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="ps-inst-links">
								<?php
								$docs_url = ! empty( $product['docs_url'] )
									? $product['docs_url']
									: 'https://power-source.github.io/' . rawurlencode( $product['slug'] );
								?>
								<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" class="ps-icon-link" title="<?php esc_attr_e( 'Dokumentation', 'ps-update-manager' ); ?>">
									<span class="dashicons dashicons-book"></span>
								</a>
								<?php if ( ! empty( $product['github_repo'] ) ) : ?>
									<a href="<?php echo esc_url( 'https://github.com/' . $product['github_repo'] ); ?>" target="_blank" class="ps-icon-link" title="GitHub">
										<span class="dashicons dashicons-admin-site-alt3"></span>
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</section>

			<!-- =================== COMMUNITY FOOTER =================== -->
			<div class="ps-community-banner">
				<div class="ps-community-text">
					<strong><?php esc_html_e( 'PSOURCE ist Open Source', 'ps-update-manager' ); ?></strong>
					<span><?php esc_html_e( 'Alles kostenlos, alles auf GitHub. Beiträge, Issues und Ideen willkommen.', 'ps-update-manager' ); ?></span>
				</div>
				<a href="https://github.com/power-source" target="_blank" class="button ps-community-btn">
					<span class="dashicons dashicons-admin-site-alt3"></span>
					<?php esc_html_e( 'GitHub / Power-Source', 'ps-update-manager' ); ?>
				</a>
			</div>

		</div>
		<?php
	}
	
	/**
	 * Produkte-Seite rendern (Tab-basiert mit AJAX)
	 */
	public function render_products() {
		// Zugriffsprüfung
		if ( ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'Du hast keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}
		
		?>
		<div class="wrap ps-update-manager-psources">
			<h1><?php esc_html_e( 'PSOURCE Katalog', 'ps-update-manager' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Entdecke und installiere offizielle PSOURCE Plugins und Themes aus dem Power-Source Repository.', 'ps-update-manager' ); ?>
			</p>

			<!-- Tabs -->
			<h2 class="nav-tab-wrapper">
				<a href="#" class="nav-tab nav-tab-active ps-tab-link" data-tab="plugins">
					<span class="dashicons dashicons-admin-plugins"></span>
					<?php esc_html_e( 'Plugins', 'ps-update-manager' ); ?>
				</a>
				<a href="#" class="nav-tab ps-tab-link" data-tab="themes">
					<span class="dashicons dashicons-admin-appearance"></span>
					<?php esc_html_e( 'Themes', 'ps-update-manager' ); ?>
				</a>
			</h2>

			<!-- Filter -->
			<form id="ps-catalog-filters" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
				<div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
					<input type="search" id="ps-search" class="regular-text" placeholder="<?php esc_attr_e( 'Suche nach Name oder Beschreibung…', 'ps-update-manager' ); ?>" />
					
					<select id="ps-category">
						<option value="all"><?php esc_html_e( 'Alle Kategorien', 'ps-update-manager' ); ?></option>
						<!-- Kategorien werden per AJAX geladen je nach aktivem Tab -->
					</select>
					
					<select id="ps-status">
						<option value="all"><?php esc_html_e( 'Alle', 'ps-update-manager' ); ?></option>
						<option value="installed"><?php esc_html_e( 'Installiert', 'ps-update-manager' ); ?></option>
						<option value="active"><?php esc_html_e( 'Aktiv', 'ps-update-manager' ); ?></option>
						<option value="available"><?php esc_html_e( 'Verfügbar', 'ps-update-manager' ); ?></option>
						<option value="updates"><?php esc_html_e( 'Update verfügbar', 'ps-update-manager' ); ?></option>
					</select>
					
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-filter"></span>
						<?php esc_html_e( 'Filter anwenden', 'ps-update-manager' ); ?>
					</button>
					
					<button type="button" id="ps-reset-filters" class="button">
						<span class="dashicons dashicons-undo"></span>
						<?php esc_html_e( 'Zurücksetzen', 'ps-update-manager' ); ?>
					</button>
				</div>
			</form>

			<!-- Produkt-Grid (AJAX-Container) -->
			<div id="ps-products-grid" class="ps-products-store"></div>

			<!-- Pagination (AJAX) -->
			<div id="ps-pagination" style="margin-top: 20px;"></div>
		</div>
		<?php
	}
	
	/**
	 * Update-Info für Produkt abrufen
	 */
	private function get_product_update_info( $product ) {
		$release = false;

		if ( ! empty( $product['github_repo'] ) ) {
			$github = PS_Update_Manager_GitHub_API::get_instance();
			$release = $github->get_latest_release( $product['github_repo'] );
		}

		if ( ( ! $release || is_wp_error( $release ) ) && ! empty( $product['update_url'] ) ) {
			$release = $this->get_custom_update_info( $product['update_url'] );
		}

		if ( is_wp_error( $release ) || ! is_array( $release ) ) {
			return false;
		}

		if ( empty( $release['version'] ) || empty( $release['download_url'] ) ) {
			return false;
		}

		return $release;
	}

	/**
	 * Update-Info von Custom URL abrufen
	 */
	private function get_custom_update_info( $url ) {
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * AJAX: Produkte laden (für Tab-System)
	 */
	public function ajax_load_products() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! PS_Update_Manager_Settings::get_instance()->user_can_access( 'view_catalog' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für diese Aktion', 'ps-update-manager' ) ) );
		}

		$tab      = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'plugins';
		$page     = isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_key( $_POST['category'] ) : 'all';
		$status   = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'all';

		// Scan nur wenn Cache abgelaufen (nicht bei jedem AJAX-Aufruf neu scannen)
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		if ( ! get_transient( 'ps_discovered_products' ) ) {
			$scanner->scan_all();
		}

		$official_products  = $scanner->get_official_products();
		$registry           = PS_Update_Manager_Product_Registry::get_instance();
		$installed_products = $registry->get_all();

		// WP-Update-Transients einmalig laden (schnell, keine GitHub-API-Calls)
		$update_plugins = get_site_transient( 'update_plugins' );
		$update_themes  = get_site_transient( 'update_themes' );

		// Produkte vorbereiten
		$all_products = array();
		foreach ( $official_products as $slug => $manifest ) {
			$product = array(
				'slug'             => $slug,
				'name'             => $manifest['name'],
				'description'      => $manifest['description'],
				'type'             => $manifest['type'],
				'repo'             => $manifest['repo'],
				'github_repo'      => $manifest['repo'] ?? '',
				'update_url'       => $manifest['update_url'] ?? '',
				'icon'             => $manifest['icon'] ?? 'dashicons-admin-plugins',
				'category'         => $manifest['category'] ?? 'general',
				'installed'        => false,
				'active'           => false,
				'version'          => null,
				'update_available' => false,
				'new_version'      => null,
				'basename'         => null,
				'network_mode'     => 'none',
				'featured'         => $manifest['featured'] ?? false,
				'badge'            => $manifest['badge'] ?? null,
				'logo'             => $this->get_product_logo_url( $slug, $manifest['repo'] ?? '' ),
			);

			if ( isset( $installed_products[ $slug ] ) ) {
				$installed                  = $installed_products[ $slug ];
				$product['installed']       = true;
				$product['active']          = $installed['is_active'];
				$product['version']         = $installed['version'];
				$product['basename']        = $installed['basename'] ?? null;
				$product['network_mode']    = $installed['network_mode'] ?? 'none';
				$product['github_repo']     = $installed['github_repo'] ?? $product['github_repo'];
				$product['update_url']      = $installed['update_url'] ?? $product['update_url'];

				// Update-Status aus WP-Transient lesen (kein GitHub-API-Call pro Produkt)
				if ( 'plugin' === $product['type'] ) {
					$_bn = $installed['basename'] ?? null;
					if ( $_bn && is_object( $update_plugins ) && isset( $update_plugins->response[ $_bn ] ) ) {
						$product['update_available'] = true;
						$product['new_version']      = $update_plugins->response[ $_bn ]->new_version ?? '';
					}
				} elseif ( 'theme' === $product['type'] ) {
					if ( is_object( $update_themes ) && isset( $update_themes->response[ $slug ] ) ) {
						$product['update_available'] = true;
						$product['new_version']      = $update_themes->response[ $slug ]['new_version'] ?? '';
					}
				}
			}

			$all_products[ $slug ] = $product;
		}

		// Nach Typ filtern
		$items = array_filter( $all_products, function( $item ) use ( $tab ) {
			return $item['type'] === $tab || ( 'plugins' === $tab && 'plugin' === $item['type'] ) || ( 'themes' === $tab && 'theme' === $item['type'] );
		});

		// Filter anwenden
		if ( $search ) {
			$items = array_filter( $items, function( $item ) use ( $search ) {
				$hay = strtolower( $item['name'] . ' ' . $item['slug'] . ' ' . $item['description'] );
				return strpos( $hay, strtolower( $search ) ) !== false;
			});
		}

		if ( $category && 'all' !== $category ) {
			$items = array_filter( $items, function( $item ) use ( $category ) {
				return isset( $item['category'] ) && $item['category'] === $category;
			});
		}

		if ( $status && 'all' !== $status ) {
			$items = array_filter( $items, function( $item ) use ( $status ) {
				if ( 'installed' === $status ) { return $item['installed']; }
				if ( 'active' === $status ) { return $item['active']; }
				if ( 'available' === $status ) { return ! $item['installed']; }
				if ( 'updates' === $status ) { return $item['update_available']; }
				return true;
			});
		}

		// Featured-Produkte nach oben sortieren (wenn kein Filter aktiv)
		if ( empty( $search ) && 'all' === $status ) {
			uasort( $items, function( $a, $b ) {
				$a_featured = isset( $a['featured'] ) && $a['featured'];
				$b_featured = isset( $b['featured'] ) && $b['featured'];
				if ( $a_featured === $b_featured ) { return 0; }
				return $a_featured ? -1 : 1;
			});
		}

		// Pagination
		$per_page = 12;
		$total    = count( $items );
		$pages    = max( 1, ceil( $total / $per_page ) );
		$page     = min( $page, $pages );
		$offset   = ( $page - 1 ) * $per_page;
		$slice    = array_slice( $items, $offset, $per_page, true );

		// HTML rendern
		ob_start();
		if ( empty( $slice ) ) {
			echo '<div class="ps-no-results"><p>' . esc_html__( 'Keine PSOURCE gefunden.', 'ps-update-manager' ) . '</p></div>';
		} else {
			foreach ( $slice as $slug => $product ) {
				$this->render_product_card( $product, $installed_products );
			}
		}
		$html = ob_get_clean();

		// Pagination HTML
		$pagination_html = '';
		if ( $pages > 1 ) {
			$pagination_html = '<div class="ps-pagination">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$class = ( $i === $page ) ? 'button button-primary' : 'button';
				$pagination_html .= sprintf(
					'<a href="#" class="ps-pagination-link %s" data-page="%d">%d</a> ',
					esc_attr( $class ),
					intval( $i ),
					intval( $i )
				);
			}
			$pagination_html .= '</div>';
		}

		wp_send_json_success( array(
			'html'       => $html,
			'pagination' => $pagination_html,
			'total'      => $total,
			'pages'      => $pages,
		) );
	}

	/**
	 * AJAX: Kategorien für Tab abrufen
	 */
	public function ajax_get_categories() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! PS_Update_Manager_Settings::get_instance()->user_can_access( 'view_catalog' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für diese Aktion', 'ps-update-manager' ) ) );
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_key( $_POST['tab'] ) : 'plugins';

		// Kategorie-Mapping laden
		$category_map_file = PS_UPDATE_MANAGER_DIR . 'includes/category-map.php';
		$all_categories = file_exists( $category_map_file ) ? include $category_map_file : array();

		// Kategorien für den Tab
		$categories = array();
		if ( 'plugins' === $tab && isset( $all_categories['plugins'] ) ) {
			$categories = $all_categories['plugins'];
		} elseif ( 'themes' === $tab && isset( $all_categories['themes'] ) ) {
			$categories = $all_categories['themes'];
		}

		wp_send_json_success( array( 'categories' => $categories ) );
	}

	/**
	 * Einzelne Produkt-Karte rendern (für AJAX)
	 */
	private function render_product_card( $product, $installed_products = array() ) {
		$slug = $product['slug'];
		$is_network_active = false;
		if ( is_multisite() && $product['installed'] && isset( $product['basename'] ) && $product['basename'] ) {
			$is_network_active = is_plugin_active_for_network( $product['basename'] );
		}
		
		$is_featured = isset( $product['featured'] ) && $product['featured'];
		$badge_type = isset( $product['badge'] ) ? $product['badge'] : null;
		$card_class = $is_featured ? 'ps-store-card ps-store-card-featured' : 'ps-store-card';
		
		?>
		<div class="<?php echo esc_attr( $card_class ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
			<?php if ( $is_featured ) : ?>
				<div class="ps-featured-ribbon">
					<span class="dashicons dashicons-star-filled"></span>
					<?php esc_html_e( 'Empfohlen', 'ps-update-manager' ); ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $product['logo'] ) ) : ?>
				<?php
				$default_logo = PS_UPDATE_MANAGER_URL . 'psource-logo.png';
				$repo_logo_fallback = '';
				if ( ! empty( $product['repo'] ) ) {
					$safe_repo = preg_replace( '#[^A-Za-z0-9._/-]#', '', (string) $product['repo'] );
					if ( ! empty( $safe_repo ) ) {
						$repo_logo_fallback = 'https://cdn.jsdelivr.net/gh/' . $safe_repo . '@HEAD/psource-logo.png';
					}
				}
				$onerror = ! empty( $repo_logo_fallback )
					? "this.onerror=function(){this.onerror=null;this.src='" . esc_js( $default_logo ) . "';};this.src='" . esc_js( $repo_logo_fallback ) . "';"
					: "this.onerror=null;this.src='" . esc_js( $default_logo ) . "';";
				?>
				<div class="ps-store-card-logo">
					<img src="<?php echo esc_url( $product['logo'] ); ?>" alt="<?php echo esc_attr( $product['name'] ); ?>" onerror="<?php echo esc_attr( $onerror ); ?>" />
				</div>
			<?php endif; ?>
			<div class="ps-store-card-header">
				<span class="ps-store-icon">
					<span class="dashicons <?php echo esc_attr( $product['icon'] ); ?>"></span>
				</span>
				<div class="ps-store-title">
					<h3><?php echo esc_html( $product['name'] ); ?></h3>
					<span class="ps-store-meta">
						<?php echo esc_html( ucfirst( $product['type'] ) ); ?> 
						<?php if ( $product['version'] ) : ?>
							· v<?php echo esc_html( $product['version'] ); ?>
						<?php endif; ?>
						<?php if ( $badge_type ) : ?>
							<?php if ( 'framework' === $badge_type ) : ?>
								<span class="ps-meta-badge ps-badge-framework">
									<span class="dashicons dashicons-welcome-widgets-menus"></span>
									<?php esc_html_e( 'Pagebuilder', 'ps-update-manager' ); ?>
								</span>
							<?php elseif ( 'child-theme' === $badge_type ) : ?>
								<span class="ps-meta-badge ps-badge-child">
									<span class="dashicons dashicons-admin-generic"></span>
									<?php esc_html_e( 'Child-Theme', 'ps-update-manager' ); ?>
								</span>
							<?php elseif ( 'template' === $badge_type ) : ?>
								<span class="ps-meta-badge ps-badge-template">
									<span class="dashicons dashicons-admin-page"></span>
									<?php esc_html_e( 'Template', 'ps-update-manager' ); ?>
								</span>
							<?php endif; ?>
						<?php endif; ?>
					</span>
				</div>
				
				<div class="ps-store-status">
					<?php if ( ! $product['installed'] ) : ?>
						<span class="ps-badge ps-badge-not-installed"><?php esc_html_e( 'Nicht installiert', 'ps-update-manager' ); ?></span>
					<?php elseif ( $product['update_available'] ) : ?>
						<span class="ps-badge ps-badge-update"><?php printf( __( 'Update: v%s', 'ps-update-manager' ), esc_html( $product['new_version'] ) ); ?></span>
					<?php elseif ( $product['active'] ) : ?>
						<?php if ( $is_network_active ) : ?>
							<span class="ps-badge ps-badge-network-active" title="<?php esc_attr_e( 'Netzwerkweit aktiviert', 'ps-update-manager' ); ?>">
								<span class="dashicons dashicons-networking" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span>
								<?php esc_html_e( 'Netzwerkweit', 'ps-update-manager' ); ?>
							</span>
						<?php else : ?>
							<span class="ps-badge ps-badge-active"><?php esc_html_e( 'Aktiv', 'ps-update-manager' ); ?></span>
						<?php endif; ?>
					<?php else : ?>
						<span class="ps-badge ps-badge-inactive"><?php esc_html_e( 'Inaktiv', 'ps-update-manager' ); ?></span>
					<?php endif; ?>
				</div>
			</div>
			
			<div class="ps-store-card-body">
				<p class="ps-store-description"><?php echo esc_html( $product['description'] ); ?></p>
				
				<?php
				// "Erweitert durch" Banner anzeigen
				$dependency_manager = PS_Update_Manager_Dependency_Manager::get_instance();
				echo $dependency_manager->render_extends_banner( $slug );
				?>
				
				<div class="ps-store-links">
					<a href="https://github.com/<?php echo esc_attr( $product['repo'] ); ?>" target="_blank" class="ps-link">
						<span class="dashicons dashicons-admin-site"></span> GitHub
					</a>
					<a href="https://github.com/<?php echo esc_attr( $product['repo'] ); ?>/issues" target="_blank" class="ps-link">
						<span class="dashicons dashicons-sos"></span> Support
					</a>
					<?php
					$docs_url = ! empty( $product['docs_url'] ) 
						? $product['docs_url'] 
						: 'https://power-source.github.io/' . rawurlencode( $product['slug'] );
					?>
					<a href="<?php echo esc_url( $docs_url ); ?>" target="_blank" class="ps-link">
						<span class="dashicons dashicons-media-document"></span> Handbuch
					</a>
				</div>
			</div>
			
			<div class="ps-store-card-footer">
				<?php if ( ! $product['installed'] ) : ?>
					<button class="button button-primary ps-install-product" data-slug="<?php echo esc_attr( $slug ); ?>" data-repo="<?php echo esc_attr( $product['repo'] ); ?>" data-type="<?php echo esc_attr( $product['type'] ); ?>">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Installieren', 'ps-update-manager' ); ?>
					</button>
				<?php elseif ( $product['update_available'] ) : ?>
					<button class="button button-primary ps-update-product" data-slug="<?php echo esc_attr( $slug ); ?>" data-basename="<?php echo esc_attr( $product['basename'] ); ?>" data-type="<?php echo esc_attr( $product['type'] ); ?>" data-new-version="<?php echo esc_attr( $product['new_version'] ); ?>">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Jetzt aktualisieren', 'ps-update-manager' ); ?>
					</button>
				<?php elseif ( ! $product['active'] && 'plugin' === $product['type'] ) : ?>
					<?php
					$plugin_basename = $product['basename'] ?? $slug . '/' . $slug . '.php';
					$network_mode    = $product['network_mode'];
					
					if ( is_multisite() && is_network_admin() ) {
						if ( 'none' === $network_mode ) {
							?>
							<button class="button" disabled>
								<span class="dashicons dashicons-admin-site"></span>
								<?php esc_html_e( 'Nur Unterseiten', 'ps-update-manager' ); ?>
							</button>
							<?php
						} else {
							?>
							<button class="button button-primary ps-activate-plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-basename="<?php echo esc_attr( $plugin_basename ); ?>" data-network="true">
								<span class="dashicons dashicons-yes"></span>
								<?php esc_html_e( 'Netzwerkweit aktivieren', 'ps-update-manager' ); ?>
							</button>
							<?php
						}
					} else {
						?>
						<button class="button button-primary ps-activate-plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-basename="<?php echo esc_attr( $plugin_basename ); ?>" data-network="false">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Aktivieren', 'ps-update-manager' ); ?>
						</button>
					<?php } ?>
				<?php elseif ( ! $product['active'] && 'theme' === $product['type'] ) : ?>
					<?php
					$activate_url = wp_nonce_url(
						is_network_admin() 
							? network_admin_url( 'themes.php?action=enable&theme=' . urlencode( $slug ) )
							: admin_url( 'themes.php?action=activate&stylesheet=' . urlencode( $slug ) ),
						is_network_admin() ? 'enable-theme_' . $slug : 'switch-theme_' . $slug
					);
					?>
					<a href="<?php echo esc_url( $activate_url ); ?>" class="button button-primary">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Aktivieren', 'ps-update-manager' ); ?>
					</a>
				<?php elseif ( 'plugin' === $product['type'] ) : ?>
					<button class="button button-secondary ps-deactivate-plugin" data-slug="<?php echo esc_attr( $slug ); ?>" data-basename="<?php echo esc_attr( $product['basename'] ); ?>" data-type="<?php echo esc_attr( $product['type'] ); ?>">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'Deaktivieren', 'ps-update-manager' ); ?>
					</button>
				<?php else : ?>
					<button class="button" disabled>
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Aktiv', 'ps-update-manager' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Einstellungen-Seite rendern
	 */
	public function render_settings() {
		// Zugriffsprüfung - nur Netzwerk-Admin
		if ( ! is_multisite() || ! is_super_admin() ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}
		
		$settings = PS_Update_Manager_Settings::get_instance();
		$available_roles = $settings->get_available_roles();

		// Berechtigungskategorien definieren
		$capabilities = array(
			'dashboard' => array(
				'label'       => __( 'Dashboard Zugriff', 'ps-update-manager' ),
				'description' => __( 'Berechtigung für allgemeinen Zugriff auf das Plugin-Dashboard', 'ps-update-manager' ),
			),
			'catalog' => array(
				'label'       => __( 'Katalog Anzeigen', 'ps-update-manager' ),
				'description' => __( 'Erlaubt Benutzern, die Produktkatalog anzuschauen', 'ps-update-manager' ),
			),
			'check_updates' => array(
				'label'       => __( 'Updates Prüfen', 'ps-update-manager' ),
				'description' => __( 'Erlaubt Force-Check und Aktualisierungssuche', 'ps-update-manager' ),
			),
			'install' => array(
				'label'       => __( 'Produkte Installieren', 'ps-update-manager' ),
				'description' => __( 'Erlaubt Installation von Plugins und Themes', 'ps-update-manager' ),
			),
			'update' => array(
				'label'       => __( 'Produkte Aktualisieren', 'ps-update-manager' ),
				'description' => __( 'Erlaubt Updates von installierten Produkten', 'ps-update-manager' ),
			),
			'manage_plugins' => array(
				'label'       => __( 'Plugins Verwalten', 'ps-update-manager' ),
				'description' => __( 'Erlaubt Aktivierung/Deaktivierung von Plugins', 'ps-update-manager' ),
			),
			'manage_themes' => array(
				'label'       => __( 'Themes Verwalten', 'ps-update-manager' ),
				'description' => __( 'Erlaubt Aktivierung/Deaktivierung von Themes', 'ps-update-manager' ),
			),
			'network_tools' => array(
				'label'       => __( 'Netzwerk-Tools', 'ps-update-manager' ),
				'description' => __( 'Zugriff auf Standard-Theme und andere Netzwerk-Tools', 'ps-update-manager' ),
			),
			'manage_tos' => array(
				'label'       => __( 'TOS Einstellungen', 'ps-update-manager' ),
				'description' => __( 'Verwaltung von Terms of Service Einstellungen', 'ps-update-manager' ),
			),
			'manage_settings' => array(
				'label'       => __( 'Plugin-Einstellungen', 'ps-update-manager' ),
				'description' => __( 'Zugriff auf diese Einstellungsseite', 'ps-update-manager' ),
			),
			'test_api' => array(
				'label'       => __( 'GitHub API Testen', 'ps-update-manager' ),
				'description' => __( 'Erlaubt GitHub API Verbindungstests', 'ps-update-manager' ),
			),
		);
		?>
		<div class="wrap ps-update-manager-settings">
			<h1><?php esc_html_e( 'PS Update Manager - Berechtigungsmanagement', 'ps-update-manager' ); ?></h1>

			<div class="ps-settings-hero">
				<div class="ps-settings-hero-content">
					<span class="ps-settings-hero-kicker"><?php esc_html_e( 'PSOURCE Netzwerk', 'ps-update-manager' ); ?></span>
					<h2><?php esc_html_e( 'Alles Wichtige rund um PSOURCE an einem Ort', 'ps-update-manager' ); ?></h2>
					<p><?php esc_html_e( 'Bleib bei neuen Entwicklungen auf dem Laufenden, spring direkt in die Dokumentation oder diskutiere mit der Community im Forum. Wenn Du tiefer einsteigen willst, findest Du alle Repositories auch gesammelt auf GitHub.', 'ps-update-manager' ); ?></p>
					<div class="ps-settings-hero-actions">
						<a class="ps-settings-hero-btn ps-settings-hero-btn-primary" href="https://psource.eimen.net/aktivitaetswall/" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-megaphone"></span>
							<?php esc_html_e( 'Aktuelle DEV News', 'ps-update-manager' ); ?>
						</a>
						<a class="ps-settings-hero-btn" href="https://psource.eimen.net/wiki/categories/psource/" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-media-document"></span>
							<?php esc_html_e( 'Dokumentationen', 'ps-update-manager' ); ?>
						</a>
						<a class="ps-settings-hero-btn" href="https://psource.eimen.net/ps-forum/" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-format-chat"></span>
							<?php esc_html_e( 'Forum', 'ps-update-manager' ); ?>
						</a>
						<a class="ps-settings-hero-btn" href="https://github.com/Power-Source" target="_blank" rel="noopener noreferrer">
							<span class="dashicons dashicons-admin-site-alt3"></span>
							GitHub
						</a>
					</div>
				</div>

				<div class="ps-settings-hero-menu">
					<a class="ps-settings-hero-menu-item" href="https://psource.eimen.net/aktivitaetswall/" target="_blank" rel="noopener noreferrer">
						<strong><?php esc_html_e( 'DEV News', 'ps-update-manager' ); ?></strong>
						<span><?php esc_html_e( 'Neue Builds, Änderungen und laufende Entwicklung.', 'ps-update-manager' ); ?></span>
					</a>
					<a class="ps-settings-hero-menu-item" href="https://psource.eimen.net/wiki/categories/psource/" target="_blank" rel="noopener noreferrer">
						<strong><?php esc_html_e( 'Wiki', 'ps-update-manager' ); ?></strong>
						<span><?php esc_html_e( 'Handbücher, Integrationen und technische Doku.', 'ps-update-manager' ); ?></span>
					</a>
					<a class="ps-settings-hero-menu-item" href="https://psource.eimen.net/ps-forum/" target="_blank" rel="noopener noreferrer">
						<strong><?php esc_html_e( 'Forum', 'ps-update-manager' ); ?></strong>
						<span><?php esc_html_e( 'Fragen, Feedback und Austausch mit der Community.', 'ps-update-manager' ); ?></span>
					</a>
					<a class="ps-settings-hero-menu-item" href="https://github.com/Power-Source" target="_blank" rel="noopener noreferrer">
						<strong>GitHub</strong>
						<span><?php esc_html_e( 'Quellcode, Issues und Beiträge direkt bei Power-Source.', 'ps-update-manager' ); ?></span>
					</a>
				</div>
			</div>
			
			<div class="ps-settings-container">
				<form method="post" action="">
					<?php wp_nonce_field( 'ps_update_manager_settings', 'ps_update_manager_settings_nonce' ); ?>
					
					<!-- Zugriff & Katalog -->
					<div class="ps-settings-section">
						<h2><?php esc_html_e( '🔐 Grundzugriff & Katalog', 'ps-update-manager' ); ?></h2>
						<?php $this->render_capability_group( $capabilities, $settings, $available_roles, array( 'dashboard', 'catalog', 'check_updates' ) ); ?>
					</div>

					<!-- Installation & Updates -->
					<div class="ps-settings-section">
						<h2><?php esc_html_e( '⬇️ Installation & Updates', 'ps-update-manager' ); ?></h2>
						<?php $this->render_capability_group( $capabilities, $settings, $available_roles, array( 'install', 'update', 'manage_plugins', 'manage_themes' ) ); ?>
					</div>

					<!-- Netzwerk-Tools -->
					<div class="ps-settings-section">
						<h2><?php esc_html_e( '🛠️ Netzwerk-Tools (Multisite)', 'ps-update-manager' ); ?></h2>
						<?php $this->render_capability_group( $capabilities, $settings, $available_roles, array( 'network_tools', 'manage_tos' ) ); ?>
					</div>

					<!-- Admin & Debug -->
					<div class="ps-settings-section">
						<h2><?php esc_html_e( '⚙️ Admin & Debug', 'ps-update-manager' ); ?></h2>
						<?php $this->render_capability_group( $capabilities, $settings, $available_roles, array( 'manage_settings', 'test_api' ) ); ?>
					</div>
					
					<?php submit_button( __( 'Einstellungen speichern', 'ps-update-manager' ), 'primary', 'submit', true ); ?>
				</form>
			</div>
			
			<div class="ps-settings-section" style="margin-top:20px;">
				<h3><?php esc_html_e( '💡 Wichtige Hinweise', 'ps-update-manager' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Der Netzwerk-Administrator hat immer Zugriff auf alle Funktionen.', 'ps-update-manager' ); ?></li>
					<li><?php esc_html_e( 'Wenn ein Benutzer z.B. "Katalog anzeigen" nicht darf, sieht er keine Produktliste.', 'ps-update-manager' ); ?></li>
					<li><?php esc_html_e( 'Installation ist NICHT automatisch erlaubt, wenn nur Katalog anzeigen aktiviert ist.', 'ps-update-manager' ); ?></li>
					<li><?php esc_html_e( 'Diese Einstellungen gelten netzwerkweit für alle Seiten.', 'ps-update-manager' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render capability group helper
	 */
	private function render_capability_group( $all_capabilities, $settings, $available_roles, $capability_keys ) {
		?>
		<div class="ps-capabilities-grid">
			<?php foreach ( $capability_keys as $key ) : ?>
				<?php if ( ! isset( $all_capabilities[ $key ] ) ) continue; ?>
				<?php $cap = $all_capabilities[ $key ]; ?>
				<?php $role_setting_key = $this->capability_key_to_setting( $key ); ?>
				<?php $allowed_roles = $settings->get_setting( $role_setting_key ); ?>
				
				<div class="ps-capability-card">
					<h4><?php echo esc_html( $cap['label'] ); ?></h4>
					<p class="description"><?php echo esc_html( $cap['description'] ); ?></p>
					
					<div class="ps-role-checkboxes">
						<?php foreach ( $available_roles as $role_slug => $role_data ) : ?>
							<label class="ps-role-checkbox">
								<input type="checkbox" 
									name="ps_update_manager_<?php echo esc_attr( $role_setting_key ); ?>[]" 
									value="<?php echo esc_attr( $role_slug ); ?>"
									<?php checked( in_array( $role_slug, $allowed_roles, true ) ); ?>
								>
								<span><?php echo esc_html( $role_data['name'] ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Convert capability key to setting key name
	 */
	private function capability_key_to_setting( $key ) {
		switch ( $key ) {
			case 'dashboard':
				return 'allowed_roles';
			case 'catalog':
				return 'catalog_roles';
			case 'check_updates':
				return 'check_updates_roles';
			case 'install':
				return 'install_roles';
			case 'update':
				return 'update_roles';
			case 'manage_plugins':
				return 'manage_plugins_roles';
			case 'manage_themes':
				return 'manage_themes_roles';
			case 'network_tools':
				return 'network_tools_roles';
			case 'manage_tos':
				return 'manage_tos_roles';
			case 'manage_settings':
				return 'manage_settings_roles';
			case 'test_api':
				return 'test_api_roles';
			default:
				return $key . '_roles';
		}
	}
	
	/**
	 * Verfügbare Updates zählen
	 */
	private function count_available_updates( $products ) {
		// WP-Update-Transients lesen – kein blockierender GitHub-API-Call pro Produkt
		$update_plugins = get_site_transient( 'update_plugins' );
		$update_themes  = get_site_transient( 'update_themes' );
		$count = 0;
		foreach ( $products as $product ) {
			if ( 'plugin' === $product['type'] ) {
				if ( ! empty( $product['basename'] ) && is_object( $update_plugins ) && isset( $update_plugins->response[ $product['basename'] ] ) ) {
					$count++;
				}
			} elseif ( 'theme' === $product['type'] ) {
				if ( ! empty( $product['slug'] ) && is_object( $update_themes ) && isset( $update_themes->response[ $product['slug'] ] ) ) {
					$count++;
				}
			}
		}
		return $count;
	}
	
	/**
	 * Aktive Produkte zählen
	 */
	private function count_active( $products ) {
		return count( array_filter( $products, function( $p ) {
			return $p['is_active'];
		} ) );
	}
	
	/**
	 * AJAX: Update-Check erzwingen
	 */
	public function ajax_force_update_check() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! PS_Update_Manager_Settings::get_instance()->user_can_access( 'check_updates' ) ) {
			wp_send_json_error( __( 'Keine Berechtigung für diese Aktion', 'ps-update-manager' ) );
		}

		// Force: Transients löschen und neu checken
		PS_Update_Manager_Update_Checker::get_instance()->force_check();

		// Kurze Verzögerung um sicherzustellen, dass WP-Update-Prüfung fertig ist
		sleep( 1 );

		// Immer scannen (nicht Cache-Guard)
		$scanner = PS_Update_Manager_Product_Scanner::get_instance();
		$scanner->scan_all();

		// Frische Daten laden
		$products = PS_Update_Manager_Product_Registry::get_instance()->get_all();
		$updates_available = $this->count_available_updates( $products );

		wp_send_json_success( array(
			'updates_available' => $updates_available,
			'total_products'    => count( $products ),
			'message'           => sprintf(
				__( 'Überprüfung abgeschlossen: %d Updates verfügbar', 'ps-update-manager' ),
				$updates_available
			),
		) );
	}
	
	/**
	 * Produkt von GitHub installieren
	 */
	private function install_from_github( $slug, $repo, $type = 'plugin' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// GitHub API: Latest Release holen
		$api = PS_Update_Manager_GitHub_API::get_instance();
		$release = $api->get_latest_release( $repo );

		// Debug: Fehler spezifisch handeln
		if ( is_wp_error( $release ) ) {
			// Preserve the actual error from GitHub API
			return $release;
		}

		if ( ! $release || ! is_array( $release ) ) {
			return new WP_Error( 'invalid_release', sprintf(
				__( 'Ungültiges Release-Format für "%s"', 'ps-update-manager' ),
				esc_html( $repo )
			) );
		}

		// ZIP-Download-URL (korrekter Array-Key)
		$download_url = $release['download_url'] ?? '';

		if ( empty( $download_url ) ) {
			return new WP_Error( 'no_download_url', sprintf(
				__( 'Keine Download-URL in GitHub Release "%s" gefunden', 'ps-update-manager' ),
				esc_html( $repo )
			) );
		}

		// Zielverzeichnis und Zielordner vorbereiten
		$destination = trailingslashit( ( 'theme' === $type ) ? WP_CONTENT_DIR . '/themes' : WP_PLUGIN_DIR );
		$slug_safe = sanitize_file_name( $slug );
		$target_dir = trailingslashit( $destination ) . $slug_safe;
		$dirs_before = glob( $destination . '*', GLOB_ONLYDIR );
		$dirs_before = is_array( $dirs_before ) ? array_map( 'realpath', $dirs_before ) : array();
		// Vorhandenen Zielordner vorab löschen
		if ( file_exists( $target_dir ) ) {
			$this->delete_directory_recursive( $target_dir );
		}
		
		// Temporäres Verzeichnis
		$temp_file = download_url( $download_url );
		
		if ( is_wp_error( $temp_file ) ) {
			return new WP_Error( 'download_failed', sprintf(
				__( 'Download fehlgeschlagen: %s', 'ps-update-manager' ),
				$temp_file->get_error_message()
			) );
		}
		
		if ( ! file_exists( $temp_file ) ) {
			return new WP_Error( 'temp_file_not_exists', __( 'Temporäre Datei konnte nicht erstellt werden', 'ps-update-manager' ) );
		}
		
		// Zielverzeichnis ist oben bereits gesetzt
		
		// Entpacken - WP_Filesystem initialisieren
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		$wp_filesystem_ok = WP_Filesystem();
		
		if ( ! $wp_filesystem_ok ) {
			if ( file_exists( $temp_file ) ) {
				wp_delete_file( $temp_file );
			}
			return new WP_Error( 'wp_filesystem_error', __( 'ClassicPress Dateisystem konnte nicht initialisiert werden. Bitte prüfe die Dateisystem-Berechtigungen.', 'ps-update-manager' ) );
		}
		
		global $wp_filesystem;
		
		$unzip_result = unzip_file( $temp_file, $destination );
		
		// Temp-Datei löschen (proper cleanup ohne error suppression)
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}
		
		if ( is_wp_error( $unzip_result ) ) {
			return new WP_Error( 'unzip_failed', sprintf(
				__( 'Entpacken fehlgeschlagen: %s', 'ps-update-manager' ),
				$unzip_result->get_error_message()
			) );
		}
		
		// GitHub ZIP hat Ordner wie "Power-Source-ps-chat-abc123"
		// Umbenennen zu "ps-chat"
		if ( is_dir( $target_dir ) ) {
			return true;
		}

		$extracted_dir = $this->find_extracted_directory( $destination, $repo );

		if ( ! $extracted_dir ) {
			$dirs_after = glob( $destination . '*', GLOB_ONLYDIR );
			$dirs_after = is_array( $dirs_after ) ? array_map( 'realpath', $dirs_after ) : array();
			$new_dirs = array_values( array_diff( $dirs_after, $dirs_before ) );
			if ( count( $new_dirs ) === 1 && is_dir( $new_dirs[0] ) ) {
				$extracted_dir = $new_dirs[0];
			}
		}
		
		if ( $extracted_dir ) {
			// SICHERHEIT: Path Traversal Prevention
			// $slug_safe und $target_dir sind oben bereits gesetzt
			// Prüfe ob Destination existiert
			if ( ! file_exists( $destination ) ) {
				return new WP_Error( 'destination_not_exists', __( 'Zielverzeichnis existiert nicht', 'ps-update-manager' ) );
			}

			$destination_real = realpath( $destination );
			if ( ! $destination_real ) {
				return new WP_Error( 'invalid_destination', __( 'Ungültiges Zielverzeichnis', 'ps-update-manager' ) );
			}

			$target_real = realpath( dirname( $target_dir ) );
			if ( ! $target_real || 0 !== strpos( $target_real, $destination_real ) ) {
				return new WP_Error( 'security_error', __( 'Sicherheitsfehler: Ungültiger Zielpfad', 'ps-update-manager' ) );
			}

			if ( ! file_exists( $target_dir ) ) {
				$rename_result = rename( $extracted_dir, $target_dir );
				if ( ! $rename_result ) {
					return new WP_Error( 'rename_failed', __( 'Umbenennen des Verzeichnisses fehlgeschlagen', 'ps-update-manager' ) );
				}
			}
			// Nach erfolgreichem Umbenennen: Ursprünglichen extrahierten Ordner löschen, falls noch vorhanden und nicht identisch mit Ziel
			if ( file_exists( $extracted_dir ) && $extracted_dir !== $target_dir ) {
				$this->delete_directory_recursive( $extracted_dir );
			}
		}

		if ( ! is_dir( $target_dir ) ) {
			return new WP_Error( 'install_directory_missing', __( 'Installation fehlgeschlagen: Zielordner wurde nach dem Entpacken nicht gefunden.', 'ps-update-manager' ) );
		}

		return true;
	}

	/**
	 * Hilfsfunktion: Verzeichnis rekursiv löschen
	 */
	private function delete_directory_recursive( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}
		if ( is_file( $dir ) || is_link( $dir ) ) {
			@unlink( $dir );
			return;
		}
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$this->delete_directory_recursive( $dir . DIRECTORY_SEPARATOR . $file );
		}
		@rmdir( $dir );
	}
	
	/**
	 * Extrahierten Ordner finden (GitHub ZIP hat Hash im Namen)
	 * GitHub erstellt Ordner wie: "Power-Source-repo-name-abc123def456"
	 */
	private function find_extracted_directory( $destination, $repo ) {
		// GitHub ZIP-Format: {owner}-{repo}-{hash}
		$repo_name = basename( $repo );
		
		// Versuche direkten Match mit Glob
		$pattern = $destination . '*' . $repo_name . '*';
		$files = @glob( $pattern, GLOB_ONLYDIR );
		
		if ( ! empty( $files ) && is_array( $files ) ) {
			// Filtere nur echte Verzeichnisse
			foreach ( $files as $file ) {
				if ( is_dir( $file ) ) {
					return $file;
				}
			}
		}
		
		// Fallback: Scanne das Verzeichnis manuell
		if ( is_dir( $destination ) ) {
			$handle = @opendir( $destination );
			if ( $handle ) {
				while ( false !== ( $file = readdir( $handle ) ) ) {
					if ( '.' !== $file && '..' !== $file ) {
						$path = $destination . $file;
						if ( is_dir( $path ) && strpos( $file, $repo_name ) !== false ) {
							closedir( $handle );
							return $path;
						}
					}
				}
				closedir( $handle );
			}
		}
		
		return false;
	}
	
	/**
	 * Tools-Seite rendern
	 */
	public function render_tools() {
		// Zugriffsprüfung
		if ( ! $this->current_user_can_access() ) {
			wp_die( esc_html__( 'Sie haben keine Berechtigung, um diese Seite anzuzeigen.', 'ps-update-manager' ) );
		}
		
		// Tools Manager laden
		$tool_manager = PS_Manager_Tool_Manager::get_instance();
		$available_tools = $tool_manager->get_available_tools();
		
		?>
		<div class="wrap ps-manager-wrap">
			<h1><?php esc_html_e( 'PS Manager Tools', 'ps-update-manager' ); ?></h1>
			
			<?php if ( empty( $available_tools ) ) : ?>
				<div class="notice notice-info"><p>
					<?php esc_html_e( 'Keine Tools verfügbar.', 'ps-update-manager' ); ?>
				</p></div>
			<?php else : ?>
				<div class="ps-manager-container">
					<?php $tool_manager->render_tabs(); ?>
					<div class="ps-manager-panels">
						<?php $tool_manager->render_panels(); ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
	
	/**
	 * Test GitHub API - Debug-Funktion
	 */
	public function ajax_test_github_api() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );
		
		if ( ! PS_Update_Manager_Settings::get_instance()->user_can_access( 'test_api' ) ) {
			wp_send_json_error( __( 'Keine Berechtigung für diese Aktion', 'ps-update-manager' ) );
		}
		
		$repo = isset( $_POST['repo'] ) ? sanitize_text_field( wp_unslash( $_POST['repo'] ) ) : '';
		
		if ( empty( $repo ) ) {
			wp_send_json_error( __( 'Repository-Name erforderlich', 'ps-update-manager' ) );
		}
		
		$api = PS_Update_Manager_GitHub_API::get_instance();
		$release = $api->get_latest_release( $repo );
		
		if ( is_wp_error( $release ) ) {
			wp_send_json_error( array(
				'code'    => $release->get_error_code(),
				'message' => $release->get_error_message(),
			) );
		}
		
		wp_send_json_success( array(
			'repo'           => $repo,
			'version'        => $release['version'] ?? 'N/A',
			'tag_name'       => $release['tag_name'] ?? 'N/A',
			'download_url'   => $release['download_url'] ?? 'N/A',
			'has_zip'        => ! empty( $release['download_url'] ),
		) );
	}

	/**
	 * Produkt-Logo URL auflösen
	 * Prüft ob Logo.png im Plugin-Verzeichnis existiert, sonst psource-logo.png Fallback
	 *
	 * @param string $slug Plugin/Theme Slug
	 * @return string Logo URL
	 */
	private function get_product_logo_url( $slug, $repo = '' ) {
		// Prüfe zunächst ob das Plugin/Theme lokal installiert ist
		$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
		$theme_dir  = WP_CONTENT_DIR . '/themes/' . $slug;

		// Logo.png lokal prüfen
		if ( file_exists( $plugin_dir . '/Logo.png' ) ) {
			return content_url( 'plugins/' . $slug . '/Logo.png' );
		} elseif ( file_exists( $theme_dir . '/Logo.png' ) ) {
			return content_url( 'themes/' . $slug . '/Logo.png' );
		}

		// Fallback auf psource-logo.png lokal
		if ( file_exists( $plugin_dir . '/psource-logo.png' ) ) {
			return content_url( 'plugins/' . $slug . '/psource-logo.png' );
		} elseif ( file_exists( $theme_dir . '/psource-logo.png' ) ) {
			return content_url( 'themes/' . $slug . '/psource-logo.png' );
		}

		if ( ! empty( $repo ) ) {
			$safe_repo = preg_replace( '#[^A-Za-z0-9._/-]#', '', (string) $repo );
			if ( ! empty( $safe_repo ) ) {
				return 'https://cdn.jsdelivr.net/gh/' . $safe_repo . '@HEAD/Logo.png';
			}
		}

		// Ultimativer Fallback: psource-logo.png aus PS Update Manager Plugin
		return PS_UPDATE_MANAGER_URL . 'psource-logo.png';
	}

	/**
	 * AJAX: Plugin deaktivieren
	 */
	public function ajax_deactivate_plugin() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! PS_Update_Manager_Settings::get_instance()->user_can_access( 'manage_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für diese Aktion', 'ps-update-manager' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$basename = isset( $_POST['basename'] ) ? sanitize_text_field( $_POST['basename'] ) : '';

		if ( empty( $slug ) || empty( $basename ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Parameter', 'ps-update-manager' ) ) );
		}

		// ClassicPress Plugin-Funktionen laden
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Plugin deaktivieren
		if ( is_multisite() && is_network_admin() ) {
			deactivate_plugins( $basename, false, true ); // Network-wide deactivation
		} else {
			deactivate_plugins( $basename );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( '%s wurde erfolgreich deaktiviert.', 'ps-update-manager' ), $slug ),
		) );
	}

	/**
	 * AJAX: Plugin aktivieren
	 */
	public function ajax_activate_plugin() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! PS_Update_Manager_Settings::get_instance()->user_can_access( 'manage_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für diese Aktion', 'ps-update-manager' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$basename = isset( $_POST['basename'] ) ? sanitize_text_field( $_POST['basename'] ) : '';
		$network = isset( $_POST['network'] ) && $_POST['network'] === 'true';

		if ( empty( $slug ) || empty( $basename ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Parameter', 'ps-update-manager' ) ) );
		}

		// ClassicPress Plugin-Funktionen laden
		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Plugin aktivieren
		$result = activate_plugin( $basename, '', $network );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		wp_send_json_success( array(
			'message' => sprintf( __( '%s wurde erfolgreich aktiviert.', 'ps-update-manager' ), $slug ),
		) );
	}

	/**
	 * AJAX: Produkt aktualisieren
	 */
	public function ajax_update_product() {
		check_ajax_referer( 'ps_update_manager_nonce', 'nonce' );

		if ( ! PS_Update_Manager_Settings::get_instance()->user_can_access( 'update_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für diese Aktion', 'ps-update-manager' ) ) );
		}

		$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
		$basename = isset( $_POST['basename'] ) ? sanitize_text_field( $_POST['basename'] ) : '';
		$type = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'plugin';

		if ( empty( $slug ) || ( 'plugin' === $type && empty( $basename ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Ungültige Parameter', 'ps-update-manager' ) ) );
		}

		// WordPress Update-Funktionen laden
		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( 'plugin' === $type ) {
			// Plugin-Update
			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$result = $upgrader->upgrade( $basename );
		} else {
			// Theme-Update
			if ( ! class_exists( 'Theme_Upgrader' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$result = $upgrader->upgrade( $slug );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		if ( $result === false ) {
			wp_send_json_error( array(
				'message' => __( 'Update fehlgeschlagen', 'ps-update-manager' ),
			) );
		}

		PS_Update_Manager_Product_Scanner::get_instance()->scan_all();

		wp_send_json_success( array(
			'message' => sprintf( __( '%s wurde erfolgreich aktualisiert.', 'ps-update-manager' ), $slug ),
		) );
	}
}

