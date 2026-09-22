**Deutsch** | [English](README.en.md)

[![Version](https://img.shields.io/badge/Version-1.3.6-2271b1?style=flat-square)](readme.txt)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![ClassicPress](https://img.shields.io/badge/ClassicPress-2.7.0%2B-03768e?style=flat-square)
[![Lizenz](https://img.shields.io/badge/Lizenz-GPL--2.0--or--later-2ea44f?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)

<div align="center">
  <img src="psource-logo.png" alt="PSOURCE Manager" width="112" height="112">

  <h1>PSOURCE Manager</h1>
  <p><strong>Dein zentrales Cockpit für das komplette PSOURCE-Ökosystem.</strong></p>
  <p>Installiere, aktualisiere und verwalte alle PSOURCE Plugins und Themes direkt aus dem ClassicPress-Dashboard – inklusive Multisite-Tools, Berechtigungsmanagement und eingebettetem PSOURCE-Portal.</p>
  <p><a href="https://psource.eimen.net/wiki/psource-manager-dokumentation/">Dokumentation</a> · <a href="https://github.com/Power-Source/ps-update-manager/issues">Fehler melden</a> · <a href="https://psource.eimen.net/">PSOURCE</a></p>
</div>

---

## Mehr als ein Updater

Der **PSOURCE Manager** ist die zentrale Schaltstelle für alle PSOURCE Plugins und Themes auf einer ClassicPress-Installation – egal ob Single-Site oder Multisite-Netzwerk. Statt jedes Produkt einzeln zu pflegen, läuft alles über ein Dashboard: Entdecken, Installieren, Aktualisieren, Berechtigungen vergeben und Netzwerk-Tools verwalten.

Das Plugin scannt die Installation automatisch nach offiziellen PSOURCE-Produkten (Whitelist über ein signiertes Manifest), zieht Updates direkt aus den GitHub Releases der jeweiligen Repos und fügt sich dabei nahtlos in die native ClassicPress-Update-Oberfläche ein.

## Highlights

| Bereich | Was der PSOURCE Manager mitbringt |
| --- | --- |
| **Dashboard** | Statistiken zu installierten Produkten, verfügbaren Updates und aktiven Plugins/Themes, Schnellzugriff auf Katalog, Tools und Einstellungen |
| **PSOURCE Katalog** | Durchsuchbare Übersicht aller offiziellen PSOURCE Plugins & Themes mit Kategorie-Filtern, Badges (Pagebuilder, Child-Theme, Template) und Ein-Klick-Installation aus GitHub Releases |
| **Update-Manager** | Erkennt neue Versionen automatisch über GitHub Releases, reiht sich in die normalen ClassicPress-Update-Listen ein (inkl. nativer Auto-Update-Umschaltung), Plugin-Info-Popup mit Changelog |
| **Täglicher Auto-Scan** | WP-Cron-Job prüft täglich neu installierte Produkte und verfügbare Updates, ganz ohne manuellen Klick |
| **Netzwerk-Tools** | Standard-Theme für neue Blog-Registrierungen, Multisite-Datenschutzstufen je Unterseite inkl. Netzwerk-Sync, Nutzungsbedingungen (TOS) bei der Registrierung |
| **PSOURCE Portal** | Live-Ansicht von psource.eimen.net direkt im Adminbereich, mit Schnelllinks zu Wiki, DEV-News und Forum |
| **Berechtigungsmanagement** | Granulare Capabilities pro Rolle: Dashboard-Zugriff, Katalog ansehen, Updates prüfen, Produkte installieren/aktualisieren, Plugins/Themes verwalten, Netzwerk-Tools, TOS-Einstellungen |
| **Empfehlungen** | Zeigt zueinander passende PSOURCE-Produkte an ("Erweitere deine Möglichkeiten mit …"), basierend auf einem offiziellen Kompatibilitäts-Manifest |
| **Mehrsprachig** | Vollständig übersetzbar über Standard-WordPress/ClassicPress-i18n, Deutsch als Ausgangssprache, Englisch bereits enthalten |
| **Multisite-first** | Funktioniert im Netzwerk-Admin, unterstützt netzwerkweite und Site-für-Site-Aktivierung, je nach Produkt |

## Modular aufgebaut

Unter **PS MANAGER → Tools** stehen eigenständige Netzwerk-Tools zur Verfügung, die unabhängig voneinander genutzt werden können:

| Tool | Funktion |
| --- | --- |
| `Standard-Theme` | Legt fest, welches Theme bei neuen Blog-Registrierungen automatisch aktiviert wird, inklusive Empfehlungen für PSOURCE-Themes |
| `Multisite-Datenschutz` | Steuert verfügbare Datenschutzstufen (öffentlich, privat, passwortgeschützt, nur Mitglieder, …) für Unterseiten sowie Netzwerk-weite Synchronisierung |
| `Nutzungsbedingungen (TOS)` | Konfiguriert Zustimmungspflicht und Text der Nutzungsbedingungen bei der Registrierung – global, mit Unterseiten-Überschreibung oder komplett pro Unterseite |

Zusätzlich gibt es unter **PS MANAGER → Einstellungen** ein feingranulares Berechtigungssystem, mit dem sich jede einzelne Funktion des Plugins pro Benutzerrolle freigeben oder sperren lässt.

## Schnellstart

### Voraussetzungen

- Eine laufende ClassicPress-Installation (ab Version 2.7.0)
- PHP 7.4 oder neuer
- Für den Update-Check: ausgehende HTTPS-Verbindungen zur GitHub API müssen erlaubt sein

### Installation

1. Lade den Ordner `ps-update-manager` nach `wp-content/plugins/` hoch oder installiere das Plugin über Deine gewohnte Paketverwaltung.
2. Aktiviere **PSOURCE Manager** im Pluginbereich (bei Multisite: netzwerkweite Aktivierung empfohlen).
3. Das Dashboard ist danach unter **PS MANAGER** im Adminmenü verfügbar.
4. Öffne **PS MANAGER → PSOURCE Katalog**, um weitere offizielle PSOURCE Plugins und Themes zu entdecken und zu installieren.
5. Passe unter **PS MANAGER → Einstellungen** bei Bedarf die Berechtigungen pro Rolle an.

## Wie Updates funktionieren

1. Der Produkt-Scanner erkennt installierte PSOURCE Plugins/Themes anhand eines offiziellen Manifests (Whitelist, keine Fremdprodukte).
2. Für jedes erkannte Produkt wird die neueste GitHub-Release-Version abgefragt und mit der installierten Version verglichen.
3. Ist eine neuere Version verfügbar, erscheint sie ganz normal in den ClassicPress-Update-Listen (Plugins, Themes, `Updates`-Übersicht) – inklusive nativer Auto-Update-Option.
4. Ein täglicher WP-Cron-Job hält Produktliste und Update-Status automatisch aktuell; zusätzlich kann jederzeit manuell über **„Updates prüfen“** im Dashboard synchronisiert werden.
5. Updates lassen sich einzeln oder als Batch direkt aus dem PSOURCE-Manager-Dashboard anstoßen.

## Für Multisite gemacht

Im Netzwerk-Administrator-Bereich bietet der PSOURCE Manager zusätzliche Möglichkeiten:

- Netzwerkweite oder Site-für-Site-Aktivierung, abhängig vom jeweiligen Produkt (`Network: required` bzw. `PS Network: flexible`)
- Zentrale Steuerung von Standard-Theme, Datenschutzstufen und Registrierungs-TOS für alle Unterseiten
- Notfall-Synchronisierung, um Datenschutz-Einstellungen aller Unterseiten auf einen konsistenten Stand zu bringen
- Berechtigungen, die netzwerkweit für alle Seiten gelten

## Sicherheit

- Nur Produkte aus dem offiziellen, versionierten PSOURCE-Manifest werden erkannt und aktualisiert – keine beliebigen Fremd-Repos
- Nonces und Capability-Checks für alle schreibenden AJAX-Aktionen (Installation, Aktivierung, Update, Einstellungen)
- Granulares Rollen-/Berechtigungssystem statt pauschalem "Admin darf alles"
- GitHub-Release-Downloads laufen über den regulären ClassicPress-Upgrader-Flow

Bitte melde sicherheitsrelevante Funde nicht öffentlich, sondern über die Kontaktmöglichkeiten auf [PSOURCE](https://psource.eimen.net/) oder als vertrauliches GitHub-Issue.

## Für andere Plugin-Entwickler

Andere PSOURCE-Plugins können sich selbst beim Manager registrieren, um von Update-Erkennung, Katalog-Eintrag und Berechtigungssystem zu profitieren:

```php
add_action( 'ps_update_manager_init', function( $manager ) {
    $manager->register_product( array(
        'slug'    => 'mein-plugin',
        'name'    => 'Mein Plugin',
        'version' => '1.0.0',
        'type'    => 'plugin',
        'file'    => __FILE__,
        'github_repo' => 'Power-Source/mein-plugin',
    ) );
} );
```

Weitere Details und Beispiele findest Du in [`docs/PLUGIN-INTEGRATION.md`](docs/PLUGIN-INTEGRATION.md) sowie im Ordner [`examples/`](examples/).

### Projektstruktur

```text
includes/
  class-admin-dashboard.php      Dashboard, Katalog, Portal, Einstellungen (Admin-UI)
  class-product-scanner.php      Erkennung installierter PSOURCE Produkte
  class-product-registry.php     Zentrale Produkt-Registry
  class-update-checker.php       GitHub-basierte Update-Erkennung & ClassicPress-Integration
  class-github-api.php           Kommunikation mit der GitHub API
  class-dependency-manager.php   Empfehlungen kompatibler PSOURCE-Produkte
  class-settings.php             Berechtigungsverwaltung
  class-tool-manager.php         Registrierung der Netzwerk-Tools
  tools/                         Standard-Theme, Multisite-Datenschutz, Registrierungs-TOS
  products-manifest.php          Offizielle PSOURCE Produktliste (Whitelist)
assets/                          Admin-CSS/JS für Dashboard und Katalog
docs/                            Erweiterte Dokumentation
examples/                        Beispiele für die Plugin-Integration
languages/                       Übersetzungsvorlage (.pot) und Sprachdateien
```

## Übersetzungen

Die Ausgangssprache des Plugins ist Deutsch. Im Ordner [`languages/`](languages/) liegen eine aktuelle `.pot`-Vorlage sowie eine vollständige englische Übersetzung (`en_US`). Weitere Sprachen lassen sich auf Basis der `.pot`-Datei ergänzen (z. B. mit Poedit) und als `ps-update-manager-{locale}.po`/`.mo` im selben Ordner ablegen.

## Mitmachen

Fehlerberichte, konkrete Verbesserungsvorschläge und Pull Requests sind willkommen.

- [GitHub-Repository](https://github.com/Power-Source/ps-update-manager)
- [Issues und Feature-Ideen](https://github.com/Power-Source/ps-update-manager/issues)
- [PSOURCE](https://psource.eimen.net/)

Bitte beschreibe bei Fehlern Deine ClassicPress- und PHP-Version, ob es sich um eine Multisite-Installation handelt, sowie die Schritte, mit denen sich das Verhalten reproduzieren lässt.

## Lizenz

PSOURCE Manager ist freie Software unter der **GNU General Public License, Version 2 oder neuer**. Weitere Details stehen in der [Lizenz](https://www.gnu.org/licenses/gpl-2.0.html).

---

<div align="center">
  Entwickelt von <a href="https://psource.eimen.net/">PSOURCE</a> für ein ClassicPress-Ökosystem, das sich zentral verwalten lässt.
</div>
