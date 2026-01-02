<?php
/**
 * Offizielle PSOURCE Manifest-Datei
 * 
 * Diese Liste definiert alle offiziellen PSource Plugins und Themes.
 * Nur Einträge in dieser Liste werden vom Update Manager erkannt.
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

	'marketpress' => array(
		'type'        => 'plugin',
		'name'        => 'MarketPress',
		'repo'        => 'Power-Source/marketpress',
		'description' => 'Das komplette ClassicPress E-Commerce-Plugin – funktioniert auch perfekt mit Multisite, um einen sozialen Marktplatz zu erstellen, auf dem Sie eine Provision einbehalten können! Aktivieren Sie das Plugin, passen Sie Ihre Einstellungen an und fügen Sie dann Produkte zu Ihrem Shop hinzu.',
		'category'    => 'ecommerce',
		'icon'        => 'dashicons-cart',
	),
	
	'psource-link-checker' => array(
		'type'        => 'plugin',
		'name'        => 'PS Link Checker',
		'repo'        => 'Power-Source/psource-link-checker',
		'description' => 'Dieses Plugin überwacht Deinen Blog auf defekte Links und teilt Dir mit, ob welche gefunden wurden.',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),

	'ps-snapshot' => array(
		'type'        => 'plugin',
		'name'        => 'PS Snapshot',
		'repo'        => 'Power-Source/ps-snapshot',
		'description' => 'Dieses Plugin ermöglicht es Dir, bei Bedarf schnelle Backup-Snapshots Deiner funktionierenden Datenbank zu erstellen. Du kannst aus den standardmäßigen WordPress-Tabellen sowie benutzerdefinierten Plugin-Tabellen innerhalb der Datenbankstruktur auswählen. Alle Snapshots werden protokolliert und Du kannst den Snapshot nach Bedarf wiederherstellen.',
		'category'    => 'sicherheit',
		'icon'        => 'dashicons-admin-appearance',
	),

	'ps-live-debug' => array(
		'type'        => 'plugin',
		'name'        => 'PSOURCE Live Debug',
		'repo'        => 'Power-Source/ps-live-debug',
		'description' => 'Aktiviert das Debuggen und fügt dem ClassicPress-Admin einen Bildschirm hinzu, um das debug.log anzuzeigen.',
		'category'    => 'development',
		'icon'        => 'dashicons-search',
	),

	'ps-mitgliedschaften' => array(
		'type'        => 'plugin',
		'name'        => 'PS Mitgliedschaften',
		'repo'        => 'Power-Source/ps-mitgliedschaften',
		'description' => 'Description: Das leistungsstärkste, benutzerfreundlichste und flexibelste Mitgliedschafts-Plugin für ClassicPress-Seiten.',
		'category'    => 'community',
		'icon'        => 'dashicons-groups',
	),

	'ps-bloghosting' => array(
		'type'        => 'plugin',
		'name'        => 'PS Bloghosting',
		'repo'        => 'Power-Source/ps-bloghosting',
		'description' => 'Das ultimative Bloghosting-Plugin für Multisites verwandelt reguläre Websites in mehrere PRO-Webseite-Abonnementstufen, die Zugriff auf Speicherplatz, Premium-Themen, Premium-Plugins und vieles mehr bieten!',
		'category'    => 'multisite',
		'icon'        => 'dashicons-cloud',
	),

	'ps-smart-crm' => array(
		'type'        => 'plugin',
		'name'        => 'PS Smart CRM',
		'repo'        => 'Power-Source/ps-smart-crm',
		'description' => 'Fügt WordPress ein leistungsstarkes CRM hinzu. Verwalten Sie Kunden, Rechnungen, TODO, Termine und zukünftige Benachrichtigungen an Agenten, Benutzer und Kunden',
		'category'    => 'ecommerce',
		'icon'        => 'dashicons-businessperson',
	),

	'ps-custom-post-widget' => array(
		'type'        => 'plugin',
		'name'        => 'PS-Beitrags-Widget',
		'repo'        => 'Power-Source/ps-custom-post-widget',
		'description' => 'Ermöglicht die Anzeige von benutzerdefinierten Beitragstypen und normalen Beiträgen mit Beitragsbildern und Auszügen als Widget',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'ps-postindexer' => array(
		'type'        => 'plugin',
		'name'        => 'Multisite Index',
		'repo'        => 'Power-Source/ps-postindexer',
		'description' => 'Ein mächtiges Multisite-Index Plugin - Bringe deinen Content dahin wo du ihn brauchst!',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'ps-community' => array(
		'type'        => 'plugin',
		'name'        => 'PS Community',
		'repo'        => 'Power-Source/ps-community',
		'description' => 'Füge Deiner ClassicPress-Webseite schnell und einfach ein soziales Netzwerk hinzu!',
		'category'    => 'community',
		'icon'        => 'dashicons-groups',
	),

	'ps-wiki' => array(
		'type'        => 'plugin',
		'name'        => 'PS Wiki',
		'repo'        => 'Power-Source/ps-wiki',
		'description' => 'Ein simples aber mächtiges Wiki-Plugin für Deine ClassicPress Seite, inkl. Multisitesupport, Frontend-Editor, Rechtemanagment',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'custompress' => array(
		'type'        => 'plugin',
		'name'        => 'CustomPress',
		'repo'        => 'Power-Source/custompress',
		'description' => 'CustomPress - Benutzerdefinierter Post-, Taxonomie- und Feldmanager',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'in-post-ads' => array(
		'type'        => 'plugin',
		'name'        => 'PS BeitragsAds',
		'repo'        => 'Power-Source/in-post-ads',
		'description' => 'Definiere benutzerdefinierte Werbeanzeigen für Beitragstypen und mehr, das einfachste Werkzeug um effektiv Werbeanzeigen zu schalten',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'psource-branding' => array(
		'type'        => 'plugin',
		'name'        => 'PSOURCE Toolkit',
		'repo'        => 'Power-Source/psource-branding',
		'description' => 'Definiere benutzerdefinierte Werbeanzeigen für Beitragstypen und mehr, das einfachste Werkzeug um effektiv Werbeanzeigen zu schalten',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),

	'coursepress' => array(
		'type'        => 'plugin',
		'name'        => 'CoursePress',
		'repo'        => 'Power-Source/coursepress',
		'description' => 'CoursePress vereinfacht die Online-Ausbildung mit Kursseiten, Paywalls, Social Sharing und einer interaktiven Lernumgebung, mit der mehr Schüler miteinander verbunden werden können',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'ps-dsgvo' => array(
		'type'        => 'plugin',
		'name'        => 'PSOURCE DSGVO',
		'repo'        => 'Power-Source/ps-dsgvo',
		'description' => 'Dieses Plugin unterstützt Website- und Webshop-Besitzer bei der Einhaltung der europäischen Datenschutzbestimmungen, die als DSGVO bekannt sind',
		'category'    => 'tools',
		'icon'        => 'dashicons-shield-alt',
	),

	'e-newsletter' => array(
		'type'        => 'plugin',
		'name'        => 'PS e-Newsletter',
		'repo'        => 'Power-Source/e-newsletter',
		'description' => 'Das ultimative Newsletter Plugin für ClassicPress. Keine Drittanbieterdienste oder Abo-Kosten, Newsletter direkt aus dem ClassicPress-Dashboard managen und versenden',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),

	'benutzerdefinierte-seitenleisten' => array(
		'type'        => 'plugin',
		'name'        => 'PS Power-Seitenleisten',
		'repo'        => 'Power-Source/benutzerdefinierte-seitenleisten',
		'description' => 'Ermöglicht das Erstellen von Widget-Bereichen und benutzerdefinierten Seitenleisten. Ersetze ganze Seitenleisten oder einzelne Widgets für bestimmte Beiträge und Seiten.',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),

	'cp-defender' => array(
		'type'        => 'plugin',
		'name'        => 'PS Security',
		'repo'        => 'Power-Source/cp-defender',
		'description' => 'Erhalte regelmäßige Sicherheitsüberprüfungen, Schwachstellenberichte, Sicherheitsempfehlungen und individuelle Sicherheitsmaßnahmen für Deine Webseite – mit nur wenigen Klicks. PS Security ist Dein Analyst und Sicherheitsexperte, der rund um die Uhr für Dich da ist.',
		'category'    => 'sicherheit',
		'icon'        => 'dashicons-admin-appearance',
	),

	'ps-stats' => array(
		'type'        => 'plugin',
		'name'        => 'PS Stats',
		'repo'        => 'Power-Source/ps-stats',
		'description' => 'Kompaktes, benutzerfreundliches und datenschutzkonformes Statistik-Plugin für ClassicPress.',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),

	'events-and-bookings' => array(
		'type'        => 'plugin',
		'name'        => 'PS Events',
		'repo'        => 'Power-Source/events-and-bookings',
		'description' => 'PS Events bietet Dir ein flexibles System zur Organisation von Partys, Abendessen, Spendenaktionen – was auch immer Du Dir vorstellen kannst.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),
	
	/**
	 * THEMES
	 */
	'ps-padma' => array(
		'type'        => 'theme',
		'name'        => 'PS Padma',
		'repo'        => 'Power-Source/ps-padma',
		'description' => 'Leistungsstarker Drag & Drop Pagebuilder für WordPress. Erstelle professionelle Websites mit visuellem Editor, exportiere und importiere Templates.',
		'category'    => 'pagebuilder',
		'icon'        => 'dashicons-layout',
		'featured'    => true,
		'badge'       => 'framework',
	),

	'ps-padma-child' => array(
	 	'type'        => 'theme',
	 	'name'        => 'PS Padma Child',
	 	'repo'        => 'Power-Source/ps-padma-child',
	 	'description' => 'Sicheres Child Theme für PS Padma. Perfekt für eigene Anpassungen und Custom Code, ohne Updates des Parent-Themes zu verlieren.',
	 	'category'    => 'pagebuilder',
	 	'icon'        => 'dashicons-admin-customizer',
	 	'featured'    => true,
	 	'badge'       => 'child-theme',
	),
	
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
