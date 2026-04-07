=== PSOURCE Manager ===
Contributors: PSOURCE
Tags: updates, github, plugins, themes, auto-update
Requires at least: 5.0
Tested up to: 6.4
ClassicPress: 2.7.0
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zentraler Installations & Update-Manager für alle PSOURCE Plugins und Themes.

== Description ==

Der **PS Manager** ist deine zentrale Lösung für die Verwaltung von PSOURCE Plugins und Themes auf deiner ClassicPress Installation, egal ob Single oder Multisite.

= Features =

* **Zentrales Dashboard** mit Übersicht aller registrierten PSOURCE Plugins/Themes
* **Update-Benachrichtigungen** im ClassicPress Admin
* **Plugin-Info Popup** mit Changelog aus GitHub Releases
* **Multisite-kompatibel** - funktioniert netzwerkweit
* **Links zu Docs, Support, Changelog** direkt im Dashboard
* **Open Source** - vollständig anpassbar

Das war's! Mehr brauchst du nicht.

= Dashboard Features =

Das PS Manager Dashboard bietet:

* **Statistiken:** Anzahl Produkte, verfügbare Updates, aktive Produkte
* **Produkt-Karten:** Übersicht mit Status, Version, Update-Info
* **Schnelllinks:** Direkte Links zu Docs, Support, GitHub
* **Update-Check:** Manuell Updates prüfen
* **Multisite:** Funktioniert im Netzwerk-Admin

= Dokumentation =

Vollständige Dokumentation findest du auf [GitHub](https://github.com/Power-Source/ps-update-manager).

== Installation ==

1. Plugin hochladen in `/wp-content/plugins/ps-update-manager/`
2. Plugin im ClassicPress Admin aktivieren
3. Dashboard ist unter "PS MANAGER" verfügbar

== Changelog ==

= 1.3.1 =

* Fix: Unterseiten-Datenschutz wird in Multisite nun zuverlässig gespeichert, wenn Überschreiben im Netzwerk-Admin aktiviert ist (kein unerwarteter Rückfall auf "Öffentlich").
* Fix: Datenschutz-Auswahl für Unterseiten/Signup hat jetzt einen robusten Fallback, wenn im Netzwerk noch keine verfügbaren Stufen konfiguriert sind.
* Fix: "Erweitere deine Möglichkeiten mit"-Box im Katalog zeigt nur noch die direkt beim jeweiligen Plugin definierten compatible_with-Einträge – kein unerwünschter Reverse-Lookup mehr aus anderen Plugins.
* Fix: Doppelter Install-Handler entfernt – der globale Handler in admin.js greift nicht mehr auf der Katalog-Seite, verhinderte korrekte AJAX-Verarbeitung und erzwang unnötigen Seiten-Reload.
* Add: Aktiver Tab im PSOURCE Katalog bleibt nach Reload, Installation, Aktivierung und Deaktivierung erhalten (URL-Hash + sessionStorage).
* Add: Hero-Banner auf der Einstellungsseite mit direkten Links zur PSOURCE-Webseite: DEV-News (Aktivitätswall), Wiki-Dokumentation, Forum und GitHub-Repository.

= 1.3.0 =

* Fix: Hero-Stat-Layout im Dashboard repariert (saubere HTML-Struktur, korrekt geschlossene Container)
* Fix: Empfehlung aus rechter Dark-Sidebar in den Hero-Stats-Bereich integriert
* Fix: Empfehlungskachel visuell an restliche Hinweis-/Stat-Kacheln angepasst (kein aggressiver Hervorhebungsstil)
* Fix: Empfehlungsbereich zeigt bis zu 3 Plugin-Empfehlungen mit Logo, Name und Kurzbegründung
* Fix: Responsive-/Overflow-Probleme im Empfehlungsbereich beseitigt (kein Herausragen über Container)
* Fix: Community-Pulse-Kachel und zugehörige Dashboard-JS-Initialisierung entfernt
* Fix: Dashboard-Hinweis "GitHub Token fehlt" vollständig entfernt
* Fix: Token-bezogene Logik aus GitHub-API-Request entfernt (keine Token-Option/Anzeige mehr im UI)
* Fix: Fehlermeldung bei GitHub-Rate-Limit neutralisiert (kein Verweis mehr auf Token-Konfiguration)
* Fix: Multisite-Privacy-Tool weiter lokalisiert (Tool-Metadaten + JS-Fehlermeldungen über Textdomain)

= 1.2.9 =

* WEITER PSOURCE: PS Medienoptimierer, PS Cloner
* Fix: JavaScript-Fehler `$(...).pointer is not a function` durch fehlende `wp-pointer`-Abhängigkeit behoben
* Fix: Seitenladung extrem beschleunigt – GitHub-API-Calls werden nicht mehr synchron beim Rendern des Dashboards ausgeführt
* Fix: `scan_all()` läuft jetzt nur bei abgelaufenem Cache, nicht mehr bei jedem Seitenaufruf
* Fix: Update-Anzeige im Dashboard nutzt WP-eigene Update-Transients – Updates werden nun zuverlässig erkannt
* Fix: Fehlerhafter `SimplePie_Misc::absolutize_url()`-Aufruf im Changelog-Formatter entfernt

= 1.2.8 =

* Bugfixes
* Mehr PSOURCE hinzugefügt!
* Updater sollte nun zuverlässiger arbeiten

= 1.2.7 =

* Bugfixes
* Mehr PSOURCE hinzugefügt!

= 1.2.6 =

* Bugfixes
* Mehr PSOURCE hinzugefügt!
* PSOURCE Logos hinzugefügt

= 1.2.5 =

* Bugfixes
* Veraltete Betafunktionen entfernt
* Mehr PSOURCE hinzugefügt!

= 1.2.4 =

* Add: More PSOURCE Plugins

= 1.2.3 =

* Add: More PSOURCE Plugins

= 1.2.2 =

* Add: More PSOURCE Plugins

= 1.2.1 =
* **PSOURCE Katalog komplett überarbeitet** - Moderne Tab-basierte UI
* **AJAX-System** - Filter und Pagination ohne Seiten-Reload
* **Featured System** - PS Padma als Pagebuilder-Framework hervorgehoben
* **Badge-System** - Framework, Child Theme und Template Badges
* **Visual Upgrades** - Ribbon-Banner, Gradients, Animationen
* **Getrennte Kategorien** - Plugins und Themes haben eigene Kategorien
* **Dynamische Filter** - Kategorie-Dropdown lädt automatisch per AJAX
* **Multisite Privacy Tool** - AJAX Batch-Sync für alle Sites
* **Improved Scanner** - Bessere Erkennung und Registry-Cleanup
* **Pagebuilder-Präsentation** - PS Padma & Child visuell hervorgehoben

= 1.0.0 (2025-12-05) =
* Erstes Release

Vollständiger Changelog: [CHANGELOG.md](https://github.com/Power-Source/ps-update-manager/blob/master/CHANGELOG.md)

== Support ==

* GitHub Issues: https://github.com/Power-Source/ps-update-manager/issues
* Dokumentation: https://github.com/Power-Source/ps-update-manager

== Contribute ==

Dieses Plugin ist Open Source! Contributions sind willkommen auf [GitHub](https://github.com/Power-Source/ps-update-manager).
