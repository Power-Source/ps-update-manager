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
		'name'        => 'PSOURCE Manager',
		'repo'        => 'Power-Source/ps-update-manager',
		'description' => 'Zentraler Update-Manager für alle PSource Plugins und Themes. Installiere, aktualisiere und verwalte alle PSource Produkte direkt aus dem ClassicPress-Dashboard. Inklusive Multisite-Unterstützung und hilfreichen Multisite-Tools. Unser Ziel: PSOURCE Open-Source für ClassicPress nach Deutschen Bedürfnissen.',
		'category'    => 'development',
		'icon'        => 'dashicons-update',
		'featured'    => true,
	),


	'ps-snapshot' => array(
		'type'        => 'plugin',
		'name'        => 'PS Snapshot',
		'repo'        => 'Power-Source/ps-snapshot',
		'description' => 'Dieses Plugin ermöglicht es Dir, bei Bedarf schnelle Backup-Snapshots Deiner funktionierenden Datenbank zu erstellen. Du kannst aus den standardmäßigen ClassicPress-Tabellen sowie benutzerdefinierten Plugin-Tabellen innerhalb der Datenbankstruktur auswählen. Alle Snapshots werden protokolliert und Du kannst den Snapshot nach Bedarf wiederherstellen.',
		'category'    => 'sicherheit',
		'icon'        => 'dashicons-admin-appearance',
		'featured'    => true,
	),


	'ps-mitgliedschaften' => array(
		'type'        => 'plugin',
		'name'        => 'PS Mitgliedschaften',
		'repo'        => 'Power-Source/ps-mitgliedschaften',
		'description' => 'Das leistungsstärkste, benutzerfreundlichste und flexibelste Mitgliedschafts-Plugin für ClassicPress-Seiten. Erstelle und verwalte Mitgliedschaftspläne, schütze Inhalte und akzeptiere Zahlungen – alles direkt von Deinem ClassicPress-Dashboard aus! Die perfekte Lösung für Abonnements, Online-Kurse, Premium-Communities und mehr.',
		'category'    => 'community',
		'icon'        => 'dashicons-groups',
		'featured'    => true,
		'compatible_with' => array(
			'marketpress' => 'Verwalte Zahlungen & Abrechnungen für Mitgliedschaften',
		),
	),

	'ps-dsgvo' => array(
		'type'        => 'plugin',
		'name'        => 'PSOURCE DSGVO',
		'repo'        => 'Power-Source/ps-dsgvo',
		'description' => 'Dieses Plugin unterstützt Website- und Webshop-Besitzer bei der Einhaltung der europäischen Datenschutzbestimmungen, die als DSGVO bekannt sind. Es bietet Werkzeuge zur Verwaltung von Einwilligungen, Datenschutzrichtlinien und Benutzeranfragen, um sicherzustellen, dass Deine Webseite den gesetzlichen Anforderungen entspricht. Für beliebte Embedded-Dienste wie YouTube, Google Maps und Facebook sind Voreinstellungen enthalten.',
		'category'    => 'tools',
		'icon'        => 'dashicons-shield-alt',
		'compatible_with' => array(
			'marketpress'      => 'E-Commerce DSGVO-konform',
			'ps-mitgliedschaften' => 'Datenschutz für Mitgliedschaften',
		),
	),

	'marketpress' => array(
		'type'        => 'plugin',
		'name'        => 'MarketPress',
		'repo'        => 'Power-Source/marketpress',
		'description' => 'Das komplette ClassicPress E-Commerce-Plugin – funktioniert auch perfekt mit Multisite, um einen sozialen Marktplatz zu erstellen, auf dem Du eine Provision einbehalten kannst! Aktiviere das Plugin, passe Deine Einstellungen an und füge dann Produkte zu Deinem Shop hinzu.',
		'category'    => 'ecommerce',
		'icon'        => 'dashicons-cart',
		'compatible_with' => array(
			'ps-mitgliedschaften' => 'Integriere Mitgliedschaften & Rabatte',
			'ps-dsgvo'            => 'DSGVO-konform verkaufen',
			'ps-smart-crm'        => 'Kundenverwaltung & CRM',
		),
	),

	'psource-branding' => array(
		'type'        => 'plugin',
		'name'        => 'PSOURCE Toolkit',
		'repo'        => 'Power-Source/psource-branding',
		'description' => 'Eine komplette White-Label- und Branding-Lösung für Multisite. Adminbar, Loginsreens, Wartungsmodus, Favicons, Entfernen von ClassicPress-Links und Branding und vielem mehr. Schalte Deine Webseite in den Wartungsmodus, um anstehende Updates oder Wartungsarbeiten anzukündigen. Nutze SMTP-Einstellungen, oder gib anderen Nutzern in deiner Multisite Tipps vom Admin-Dashboard aus.',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),

	'ps-bloghosting' => array(
		'type'        => 'plugin',
		'name'        => 'PS Bloghosting',
		'repo'        => 'Power-Source/ps-bloghosting',
		'description' => 'Das ultimative Bloghosting-Plugin für Multisites verwandelt reguläre Webseiten in mehrere PRO-Webseite-Abonnementstufen, die Zugriff auf Speicherplatz, Premium-Themen, Premium-Plugins und vieles mehr bieten! Ideal für Agenturen und Entwickler, die ihren Kunden hochwertige Bloghosting-Dienste anbieten möchten. In Verbindung mit anderen PSOURCE-Produkten wie PS Mitgliedschaften und MarketPress kannst Du ein komplettes Bloghosting-Geschäftsmodell erstellen.',
		'category'    => 'multisite',
		'icon'        => 'dashicons-cloud',
	),

	'ps-smart-crm' => array(
		'type'        => 'plugin',
		'name'        => 'PS Smart CRM',
		'repo'        => 'Power-Source/ps-smart-crm',
		'description' => 'Fügt ClassicPress ein leistungsstarkes CRM hinzu. Verwalte Kunden, Rechnungen, TODO, Termine und zukünftige Benachrichtigungen an Agenten, Benutzer und Kunden. Halte Deine Buchhaltung und Kundenbeziehungen direkt in Deinem ClassicPress-Dashboard im Griff! Die PSOURCE Lösung für kleine und mittlere Unternehmen.',
		'category'    => 'ecommerce',
		'icon'        => 'dashicons-businessperson',
		'compatible_with' => array(
			'marketpress' => 'Buche erfolgreiche Verkäufe direkt im CRM',
		),
	),

	'ps-custom-post-widget' => array(
		'type'        => 'plugin',
		'name'        => 'PS-Beitrags-Widget',
		'repo'        => 'Power-Source/ps-custom-post-widget',
		'description' => 'Ermöglicht die Anzeige von benutzerdefinierten Beitragstypen und normalen Beiträgen mit Beitragsbildern und Auszügen als Widget. Ideal für Seitenleisten und Fußzeilen. Bringe Deinen Content dorthin, wo Deine Besucher ihn sehen sollen!',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'easyblogging' => array(
		'type'        => 'plugin',
		'name'        => 'Easy Blogging',
		'repo'        => 'Power-Source/easyblogging',
		'description' => 'Ändert den ClassicPress-Verwaltungsbereich so, dass er standardmäßig einen "Anfänger" -Bereich enthält, mit der Option, zum normalen "Erweitert" -Bereich zu wechseln.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
		'compatible_with' => array(
			'msreader'      => 'Netzwerkweiter Reader mit dem Benutzer Beiträge im Netzwerk durchsuchen können',
		),
	),

	'ps-jobboard' => array(
		'type'        => 'plugin',
		'name'        => 'PS Jobboard',
		'repo'        => 'Power-Source/ps-jobboard',
		'description' => 'Bringe Menschen mit Projekten und Branchenfachleute zusammen - es ist mehr als eine durchschnittliche Jobbörse. PS Jobboard ist eine umfassende Lösung für die Erstellung und Verwaltung von Jobbörsen auf Deiner ClassicPress-Seite. Mit Funktionen wie benutzerdefinierten Jobkategorien, Bewerbungsformularen, Lebenslauf-Uploads und mehr bietet es eine benutzerfreundliche Plattform für Arbeitgeber und Arbeitssuchende.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
		'compatible_with' => array(
			'marketpress'      => 'Erhalte Zahlungen für Jobangebote oder Premium-Listings',
		),
	),

	'ps-voting' => array(
		'type'        => 'plugin',
		'name'        => 'PS Voting',
		'repo'        => 'Power-Source/ps-voting',
		'description' => 'Ein mächtiges Voting-Plugin für Deine ClassicPress-Seite. Ermögliche Benutzern, Inhalte zu bewerten, Umfragen zu erstellen und Feedback zu sammeln. Ideal für Community-Engagement und Meinungsforschung.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'terminmanager' => array(
		'type'        => 'plugin',
		'name'        => 'Terminmanager',
		'repo'        => 'Power-Source/terminmanager',
		'description' => 'Ein mächtiges Terminmanager-Plugin für Deine ClassicPress-Seite. Ermögliche Benutzern, Termine zu verwalten, Erinnerungen zu erhalten und vieles mehr. Ideal für die Organisation von Meetings und Veranstaltungen.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'ps-postindexer' => array(
		'type'        => 'plugin',
		'name'        => 'Multisite Index',
		'repo'        => 'Power-Source/ps-postindexer',
		'description' => 'Ein mächtiges Multisite-Index Plugin - Bringe deinen Content dahin wo du ihn brauchst! Multisite-weite Suche, Anzeige von Beiträgen aus dem gesamten Netzwerk, Filteroptionen und vieles mehr. Ideal für Netzwerke mit vielen Blogs und umfangreichem Content. Inklusive hilfreicher Widgets und Monitoring-Tools.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
		'compatible_with' => array(
			'msreader'      => 'Netzwerkweiter Reader mit dem Benutzer Beiträge im Netzwerk durchsuchen können',
		),
	),

	'ps-community' => array(
		'type'        => 'plugin',
		'name'        => 'PS Community',
		'repo'        => 'Power-Source/ps-community',
		'description' => 'Füge Deiner ClassicPress-Webseite schnell und einfach ein soziales Netzwerk hinzu! PS Community ist unsere Community-Lösung für ClassicPress mit Benutzerprofilen, Freundschaften, privaten Nachrichten, Aktivitäts-Feeds, Gruppen und vielem mehr. Erstelle eine lebendige Community rund um Deine Webseite und binde Deine Benutzer mit sozialen Funktionen ein.',
		'category'    => 'community',
		'icon'        => 'dashicons-groups',
	),

	'ps-wiki' => array(
		'type'        => 'plugin',
		'name'        => 'PS Wiki',
		'repo'        => 'Power-Source/ps-wiki',
		'description' => 'Ein simples aber mächtiges Wiki-Plugin für Deine ClassicPress Seite, inkl. Multisitesupport, Frontend-Editor, Rechtemanagment. und vielem mehr. Erstelle und verwalte Wissensdatenbanken, Dokumentationen oder kollaborative Inhalte direkt auf Deiner Webseite.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'private-messaging' => array(
		'type'        => 'plugin',
		'name'        => 'PS PM System',
		'repo'        => 'Power-Source/private-messaging',
		'description' => 'Private Benutzer-zu-Benutzer-Kommunikation zur Abgabe von Angeboten, zum Teilen von Projektspezifikationen und zur versteckten internen Kommunikation. Komplett mit Front-End-Integration, geschützten Kontaktinformationen und geschützter Dateifreigabe.',
		'category'    => 'community',
		'icon'        => 'dashicons-groups',
	),

	'custompress' => array(
		'type'        => 'plugin',
		'name'        => 'CustomPress',
		'repo'        => 'Power-Source/custompress',
		'description' => 'CustomPress - Benutzerdefinierter Post-, Taxonomie- und Feldmanager für ClassicPress. Erstelle und verwalte benutzerdefinierte Beitragstypen, Taxonomien und benutzerdefinierte Felder mit Leichtigkeit – alles direkt aus Deinem ClassicPress-Dashboard heraus.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'in-post-ads' => array(
		'type'        => 'plugin',
		'name'        => 'PS BeitragsAds',
		'repo'        => 'Power-Source/in-post-ads',
		'description' => 'Definiere benutzerdefinierte Werbeanzeigen für Beitragstypen und mehr, das einfachste Werkzeug um effektiv Werbeanzeigen zu schalten. Integriert sich nahtlos in Dein ClassicPress-Dashboard und bietet flexible Platzierungsoptionen für maximale Sichtbarkeit und Einnahmen.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'msreader' => array(
		'type'        => 'plugin',
		'name'        => 'Multisite Reader',
		'repo'        => 'Power-Source/msreader',
		'description' => 'Mit dem Multisite-Reader erstellst Du einen Netzwerkweiten Newsfeed für Deine Benutzer/Autoren, etc. Du kannst eigene Listen mit Deinen Lieblingsinhalten anlegen, Empfehlen & Bewerten. Du kannst ganz einfach einzelnen Blogs in Deinem Netzwerk folgen um so über die Neusten Beiträge und Inhalte Deiner favorisierten Netzwerk-Seiten informiert zu bleiben.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'coursepress' => array(
		'type'        => 'plugin',
		'name'        => 'CoursePress',
		'repo'        => 'Power-Source/coursepress',
		'description' => 'CoursePress vereinfacht die Online-Ausbildung mit Kursseiten, Paywalls, Social Sharing und einer interaktiven Lernumgebung, mit der mehr Schüler miteinander verbunden werden können. Erstelle und verwalte Online-Kurse direkt von Deinem ClassicPress-Dashboard aus – ideal für Lehrer, Trainer und Bildungseinrichtungen.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'e-newsletter' => array(
		'type'        => 'plugin',
		'name'        => 'PS e-Newsletter',
		'repo'        => 'Power-Source/e-newsletter',
		'description' => 'Das ultimative Newsletter Plugin für ClassicPress. Keine Drittanbieterdienste oder Abo-Kosten, Newsletter direkt aus dem ClassicPress-Dashboard managen und versenden.',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),
	
	'ps-chat' => array(
		'type'        => 'plugin',
		'name'        => 'PS Chat',
		'repo'        => 'Power-Source/ps-chat',
		'description' => 'Echtzeit-Chat für ClassicPress Multisite Netzwerke. Ermögliche es Deinen Benutzern, in Echtzeit zu kommunizieren, private Nachrichten zu senden und Gruppen-Chats zu erstellen. Alles bleibt auf Deinem eigenen Server – keine Drittanbieter erforderlich!',
		'category'    => 'community',
		'icon'        => 'dashicons-format-chat',
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

	'ps-fundraising' => array(
		'type'        => 'plugin',
		'name'        => 'PS Fundraising',
		'repo'        => 'Power-Source/ps-fundraising',
		'description' => 'PS Fundraising ist das ultimative WordPress-Plugin zur Verwaltung von Spendenaktionen, Crowdfunding-Kampagnen und Fundraising-Initiativen direkt auf Deiner Website. Mit voller Integration in BuddyPress und einer beeindruckenden Bandbreite an Features bietet es eine sichere, flexible und benutzerfreundliche Lösung für alle Fundraising-Anforderungen.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'rss-autoblog' => array(
		'type'        => 'plugin',
		'name'        => 'PS RSS AutoBlog',
		'repo'        => 'Power-Source/rss-autoblog',
		'description' => 'Dieses Plugin veröffentlicht automatisch Inhalte aus RSS-Feeds in verschiedenen Blogs auf Deiner ClassicPress Seite oder in Deiner Multisite.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'ps-maps' => array(
		'type'        => 'plugin',
		'name'        => 'PS Maps',
		'repo'        => 'Power-Source/ps-maps',
		'description' => 'Google Maps lässt sich ganz einfach in Deine Webseite einbinden, anpassen und nutzen – in Beiträgen, Seiten oder als benutzerfreundliches Widget. Zeige lokale Bilder an und ermögliche Deinen Besuchern, innerhalb von Sekunden Wegbeschreibungen zu erhalten.',
		'category'    => 'content',
		'icon'        => 'dashicons-welcome-widgets-menus',
	),

	'ps-pretty-plugins' => array(
		'type'        => 'plugin',
		'name'        => 'PS Pretty Plugins',
		'repo'        => 'Power-Source/ps-pretty-plugins',
		'description' => 'Verleihe Deinen Plugin-Seiten in Multisite-Netzwerken das Aussehen eines App Stores mit ausgewählten Bildern, Kategorien und einer erstaunlichen Suche.',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),
			
	'psource-link-checker' => array(
		'type'        => 'plugin',
		'name'        => 'PS Link Checker',
		'repo'        => 'Power-Source/psource-link-checker',
		'description' => 'Dieses Plugin überwacht Deinen Blog auf defekte Links und teilt Dir mit, ob welche gefunden wurden. Du kannst dann die defekten Links ganz einfach in Deinem Dashboard anzeigen und korrigieren.',
		'category'    => 'tools',
		'icon'        => 'dashicons-admin-links',
	),

	'ps-live-debug' => array(
		'type'        => 'plugin',
		'name'        => 'PSOURCE Live Debug',
		'repo'        => 'Power-Source/ps-live-debug',
		'description' => 'Aktiviert das Debuggen und fügt dem ClassicPress-Admin einen Bildschirm hinzu, um das debug.log anzuzeigen.',
		'category'    => 'development',
		'icon'        => 'dashicons-search',
	),
	
	/**
	 * THEMES
	 */
	'ps-padma' => array(
		'type'        => 'theme',
		'name'        => 'PS Padma',
		'repo'        => 'Power-Source/ps-padma',
		'description' => 'Leistungsstarker Drag & Drop Pagebuilder für ClassicPress. Erstelle professionelle Websites mit visuellem Editor, exportiere und importiere Templates.',
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
