<?php
/*
Plugin Name: Standard Theme
Version: 1.0.5
Plugin URI: https://cp-psource.github.io/default-theme/
Description: Ermöglicht die einfache Auswahl eines neuen Standardthemes für neue Blog-Anmeldungen
Author: PSOURCE
Author URI: https://github.com/cp-psource
Network: true
*/

/*
Copyright 2020-2025 PSOURCE (https://github.com/cp-psource)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * @@@@@@@@@@@@@@@@@ PS UPDATE MANAGER @@@@@@@@@@@
 * NEU: Leichtgewichtige Integration mit PS Update Manager
 * Ersetzt den alten Yanis Updater mit nur wenigen Zeilen Code!
 **/
add_action( 'plugins_loaded', function() {
	// PS Update Manager verfügbar?
	if ( function_exists( 'ps_register_product' ) ) {
		ps_register_product( array(
			'slug'          => 'default-theme',
			'name'          => 'Standard Theme',
			'version'       => '1.0.5',
			'type'          => 'plugin',
			'file'          => __FILE__,
			'github_repo'   => 'cp-psource/default-theme',
			'docs_url'      => 'https://cp-psource.github.io/default-theme/',
			'support_url'   => 'https://github.com/cp-psource/default-theme/issues',
			'changelog_url' => 'https://github.com/cp-psource/default-theme/releases',
			'description'   => 'Ermöglicht die einfache Auswahl eines neuen Standardthemes für neue Blog-Anmeldungen in WordPress Multisite.',
		) );
	}
}, 5 );

// Optional: Freundlicher Hinweis wenn Update Manager nicht installiert
add_action( 'admin_notices', function() {
	if ( ! function_exists( 'ps_register_product' ) && current_user_can( 'install_plugins' ) ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'plugins', 'plugins-network' ) ) ) {
			echo '<div class="notice notice-info is-dismissible">';
			echo '<p><strong>Standard Theme:</strong> ';
			echo 'Für automatische Updates empfehlen wir den ';
			echo '<a href="https://github.com/cp-psource/ps-update-manager" target="_blank">PS Update Manager</a>.';
			echo '</p></div>';
		}
	}
});
/**
 * @@@@@@@@@@@@@@@@@ ENDE PS UPDATE MANAGER @@@@@@@@@@@
 **/

//force multisite
if ( ! is_multisite() ) {
	exit( __( 'Das Standard Theme ist nur mit Multisite-Installationen kompatibel.', 'defaulttheme' ) );
}

//------------------------------------------------------------------------//
//---Hook-----------------------------------------------------------------//
//------------------------------------------------------------------------//

add_action( 'plugins_loaded', 'default_theme_localization' );
add_action( 'wpmu_options', 'default_theme_site_admin_options' );
add_action( 'update_wpmu_options', 'default_theme_site_admin_options_process', 1 );
add_action( 'wpmu_new_blog', 'default_theme_switch_theme', 1, 1 );

//------------------------------------------------------------------------//
//---Functions------------------------------------------------------------//
//------------------------------------------------------------------------//

function default_theme_localization() {
	// Load up the localization file if we're using WordPress in a different language
	// Place it in this plugin's "languages" folder and name it "defaulttheme-[value in wp-config].mo"
	load_plugin_textdomain( 'defaulttheme', false, '/default-theme/languages' );
}

function default_theme_switch_theme( $blog_ID ) {

	$default_theme = get_site_option( 'default_theme' );
	$themes        = wp_get_themes();

	//we have to go through all this to handle child themes, otherwise it will throw errors
	foreach ( (array) $themes as $key => $theme ) {
		$stylesheet = esc_html( $theme['Stylesheet'] );
		$template   = esc_html( $theme['Template'] );
		if ( $default_theme == $stylesheet || $default_theme == $template ) {
			$new_stylesheet = $stylesheet;
			$new_template   = $template;
		}
	}

	//activate it
	switch_to_blog( $blog_ID );
	switch_theme( $new_template, $new_stylesheet );
	restore_current_blog();
}

function default_theme_site_admin_options_process() {
	update_site_option( 'default_theme', $_POST['default_theme'] );
}

//------------------------------------------------------------------------//
//---Output Functions-----------------------------------------------------//
//------------------------------------------------------------------------//

function default_theme_site_admin_options() {

	$themes = wp_get_themes();

	$default_theme = get_site_option( 'default_theme' );
	if ( empty( $default_theme ) ) {
		$default_theme = 'default';
	}
	?>
	<h3><?php _e( 'Theme Einstellungen', 'defaulttheme' ) ?></h3>
	<table class="form-table">
		<tr valign="top">
			<th scope="row"><?php _e( 'Standard Theme', 'defaulttheme' ) ?></th>
			<td><select name="default_theme">
					<?php
					foreach ( $themes as $key => $theme ) {
						$theme_key = esc_html( $theme['Stylesheet'] );
						echo '<option value="' . $theme_key . '"' . ( $theme_key == $default_theme ? ' selected' : '' ) . '>' . $key . '</option>' . "\n";
					}
					?>
				</select>
				<br/><?php _e( 'Standardtheme für neue Blogs.', 'defaulttheme' ); ?></td>
		</tr>
	</table>
	<?php
}
