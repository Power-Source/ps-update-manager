<?php
/**
 * Offizielle PSource Produkte Manifest
 * 
 * Diese Liste definiert alle offiziellen PSource Plugins und Themes.
 * Nur Produkte in dieser Liste werden vom Update Manager erkannt.
 * 
 * Quelle: https://github.com/Power-Source
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	/**
	 * PLUGINS
	 */
	'ps-update-manager' => array(
		'type'        => 'plugin',
		'name'        => 'PS Update Manager',
		'repo'        => 'Power-Source/ps-update-manager',
		'description' => 'Zentraler Update-Manager für alle PSource Plugins und Themes.',
		'category'    => 'development',
		'icon'        => 'dashicons-update',
	),
	
	'ps-chat' => array(
		'type'        => 'plugin',
		'name'        => 'PS Chat',
		'repo'        => 'Power-Source/ps-chat',
		'description' => 'Echtzeit-Chat für WordPress Multisite Netzwerke.',
		'category'    => 'community',
		'icon'        => 'dashicons-format-chat',
	),
	
	'default-theme' => array(
		'type'        => 'plugin',
		'name'        => 'Standard Theme',
		'repo'        => 'Power-Source/default-theme',
		'description' => 'Ermöglicht die einfache Auswahl eines neuen Standardthemes für neue Blog-Anmeldungen.',
		'category'    => 'multisite',
		'icon'        => 'dashicons-admin-appearance',
	),

    'ps-live-debug' => array(
		'type'        => 'plugin',
		'name'        => 'PSOURCE Live Debug',
		'repo'        => 'Power-Source/ps-live-debug',
		'description' => 'Ermöglicht die einfache Auswahl eines neuen Standardthemes für neue Blog-Anmeldungen.',
		'category'    => 'multisite',
		'icon'        => 'dashicons-admin-appearance',
	),
	
	/**
	 * THEMES
	 */
	// 'psource-theme' => array(
	// 	'type'        => 'theme',
	// 	'name'        => 'PSource Theme',
	// 	'repo'        => 'Power-Source/psource-theme',
	// 	'description' => 'Das offizielle PSource WordPress Theme.',
	// 	'category'    => 'theme',
	// 	'icon'        => 'dashicons-admin-customizer',
	// ),
	
	/**
	 * Weitere Produkte können hier hinzugefügt werden:
	 * 
	 * 'plugin-slug' => array(
	 *     'type'        => 'plugin',           // 'plugin' oder 'theme'
	 *     'name'        => 'Plugin Name',      // Anzeigename
	 *     'repo'        => 'Power-Source/repo-name', // GitHub Repo (Owner/Name)
	 *     'description' => 'Beschreibung',     // Kurzbeschreibung
	 *     'category'    => 'category',         // Kategorie für Sortierung
	 *     'icon'        => 'dashicons-name',   // Dashicon für UI
	 * ),
	 */
);
