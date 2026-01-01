=== PSOURCE Manager ===
Contributors: PSource
Tags: updates, github, plugins, themes, auto-update
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zentraler Update-Manager für alle PSource Plugins und Themes - Updates direkt von GitHub oder eigenem Server.

== Description ==

Der **PS Update Manager** ist deine zentrale Lösung für die Verwaltung von Updates für eigene WordPress Plugins und Themes - unabhängig vom WordPress.org Repository.

= Features =

* ✅ **Zentrales Dashboard** mit Übersicht aller registrierten Plugins/Themes
* ✅ **Automatische Updates** von GitHub Releases oder eigenem Server
* ✅ **Minimale Integration** - nur 5-10 Zeilen Code pro Plugin
* ✅ **Update-Benachrichtigungen** im WordPress Admin
* ✅ **Plugin-Info Popup** mit Changelog aus GitHub Releases
* ✅ **Multisite-kompatibel** - funktioniert netzwerkweit
* ✅ **Links zu Docs, Support, Changelog** direkt im Dashboard
* ✅ **GitHub Integration** mit automatischem Release-Check
* ✅ **Caching** für bessere Performance
* ✅ **Open Source** - vollständig anpassbar

= Perfekt für: =

* **Plugin-Entwickler:** Verteile Updates an Kunden ohne WordPress.org
* **Agencies:** Verwalte Custom Plugins für mehrere Kunden
* **Multisite:** Zentrale Updates für alle Sites im Netzwerk
* **Private Plugins:** Updates für geschlossene Benutzergruppen
* **GitHub-First:** Entwickle auf GitHub, Updates automatisch

= Wie funktioniert es? =

1. **PS Update Manager installieren** (dieses Plugin)
2. **Integration-Code hinzufügen** in deine Plugins (5-10 Zeilen)
3. **GitHub Release erstellen** wenn neue Version fertig
4. **Automatisch** erscheint Update im WordPress Admin
5. **Ein Klick** - Update installiert!

= Integration-Beispiel =

```php
// In deiner Plugin-Hauptdatei:
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'        => 'mein-plugin',
            'name'        => 'Mein Plugin',
            'version'     => '1.0.0',
            'type'        => 'plugin',
            'file'        => __FILE__,
            'github_repo' => 'username/mein-plugin',
        ) );
    }
}, 5 );
```

Das war's! Mehr brauchst du nicht.

= Dashboard Features =

Das PS Update Manager Dashboard bietet:

* **Statistiken:** Anzahl Produkte, verfügbare Updates, aktive Produkte
* **Produkt-Karten:** Übersicht mit Status, Version, Update-Info
* **Schnelllinks:** Direkte Links zu Docs, Support, GitHub
* **Update-Check:** Manuell Updates prüfen
* **Multisite:** Funktioniert im Netzwerk-Admin

= GitHub Setup =

Einfach ein Release auf GitHub erstellen:

1. Tag erstellen (z.B. `v1.0.0`)
2. Release-Notes hinzufügen (werden als Changelog angezeigt)
3. Veröffentlichen
4. Fertig!

Der Update Manager prüft automatisch die GitHub API und zeigt neue Versionen in WordPress an.

= Custom Update Server =

Statt GitHub kannst du auch einen eigenen Update-Server nutzen:

```php
ps_register_product( array(
    // ...
    'update_url' => 'https://dein-server.de/updates/plugin.json',
) );
```

= Dokumentation =

Vollständige Dokumentation findest du auf [GitHub](https://github.com/Power-Source/ps-update-manager).

== Installation ==

1. Plugin hochladen in `/wp-content/plugins/ps-update-manager/`
2. Plugin im WordPress Admin aktivieren
3. Dashboard ist unter "PS Updates" verfügbar
4. Integration-Code in deine Plugins einfügen

Für detaillierte Anleitung siehe [Quick Start Guide](https://github.com/Power-Source/ps-update-manager/blob/master/QUICKSTART.md).

== Frequently Asked Questions ==

= Funktioniert es mit privaten GitHub Repos? =

Ja! Du kannst ein GitHub Personal Access Token in `wp-config.php` hinterlegen:

```php
define( 'PS_GITHUB_TOKEN', 'ghp_dein_token' );
```

= Brauche ich das für jedes Plugin einzeln? =

Nein! Du installierst den PS Update Manager **einmal** und fügst dann nur 5-10 Zeilen Integration-Code in jedes deiner Plugins ein.

= Funktioniert es auch ohne GitHub? =

Ja! Du kannst auch einen eigenen Update-Server nutzen. Siehe Dokumentation für Details.

= Ist es Multisite-kompatibel? =

Ja! Funktioniert perfekt in Multisite-Netzwerken.

= Kann ich auch Themes damit updaten? =

Ja! Funktioniert mit Plugins UND Themes.

= Wie erstelle ich ein GitHub Release? =

1. Gehe zu deinem Repo auf GitHub
2. "Releases" → "Create a new release"
3. Tag erstellen (z.B. v1.0.0)
4. Release-Notes hinzufügen
5. Veröffentlichen

= Was passiert wenn der Update Manager nicht aktiv ist? =

Die Plugins/Themes funktionieren normal weiter. Du bekommst nur keine automatischen Updates. Optional kannst du eine Admin Notice anzeigen die zur Installation auffordert.

== Screenshots ==

1. Dashboard mit Produkt-Übersicht und Statistiken
2. Produkt-Karten mit Update-Status
3. WordPress Update-Seite mit PSource-Updates
4. Plugin-Info Popup mit Changelog

== Changelog ==

= 1.2.2 (2026-01-01) =

* Add: More PSOURCE Plugins

= 1.2.2 (2025-12-31) =

* Add: More PSOURCE Plugins

= 1.2.1 (2025-12-10) =
* 🎨 **PSOURCE Katalog komplett überarbeitet** - Moderne Tab-basierte UI
* ⚡ **AJAX-System** - Filter und Pagination ohne Seiten-Reload
* 🎯 **Featured System** - PS Padma als Pagebuilder-Framework hervorgehoben
* 🏷️ **Badge-System** - Framework, Child Theme und Template Badges
* 🎨 **Visual Upgrades** - Ribbon-Banner, Gradients, Animationen
* 📂 **Getrennte Kategorien** - Plugins und Themes haben eigene Kategorien
* 🔄 **Dynamische Filter** - Kategorie-Dropdown lädt automatisch per AJAX
* 🌐 **Multisite Privacy Tool** - AJAX Batch-Sync für alle Sites
* 🛠️ **Improved Scanner** - Bessere Erkennung und Registry-Cleanup
* 🎭 **Pagebuilder-Präsentation** - PS Padma & Child visuell hervorgehoben

= 1.0.0 (2025-12-05) =
* 🎉 Erstes Release
* ✅ Zentrales Dashboard für alle PSource Plugins/Themes
* ✅ Automatische Update-Prüfung von GitHub Releases
* ✅ Custom Update-Server Support
* ✅ Leichtgewichtige Integration
* ✅ Plugin-Info Popup mit Changelog
* ✅ Multisite-Unterstützung
* ✅ Admin Notices
* ✅ Caching für bessere Performance

Vollständiger Changelog: [CHANGELOG.md](https://github.com/Power-Source/ps-update-manager/blob/master/CHANGELOG.md)

== Upgrade Notice ==

= 1.0.0 =
Erstes Release - keine Upgrades nötig.

== Support ==

* GitHub Issues: https://github.com/Power-Source/ps-update-manager/issues
* Dokumentation: https://github.com/Power-Source/ps-update-manager

== Contribute ==

Dieses Plugin ist Open Source! Contributions sind willkommen auf [GitHub](https://github.com/Power-Source/ps-update-manager).
