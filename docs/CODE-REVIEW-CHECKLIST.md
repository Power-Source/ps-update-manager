# Code Review Checklist für PSource Plugins

Diese Checkliste nutzt die Sicherheits- und Performance-Standards des PS Update Manager als Vorlage für alle PSource-Plugins.

---

## 🔐 Sicherheit

### Input Validation
- [ ] Alle `$_POST`, `$_GET`, `$_REQUEST` mit `wp_unslash()` und `sanitize_*()` behandelt
- [ ] Alle URL-Parameter validiert (z.B. IDs als Integer mit `absint()`)
- [ ] File Uploads mit `wp_handle_upload()` und MIME-Type-Check
- [ ] Keine direkte Verwendung von `$_SERVER` ohne Sanitization

### Output Escaping
- [ ] Alle HTML-Outputs mit `esc_html()`, `esc_attr()`, `esc_textarea()`
- [ ] Alle URLs mit `esc_url()` oder `esc_url_raw()`
- [ ] Alle JavaScript-Strings mit `esc_js()`
- [ ] SQL-Outputs mit `esc_sql()` (wenn wpdb nicht reicht)

### Authentication & Authorization
- [ ] Nonces in allen Forms: `wp_nonce_field()` und `wp_verify_nonce()`
- [ ] Nonces in AJAX: `wp_create_nonce()` und `check_ajax_referer()`
- [ ] Capability-Checks: `current_user_can()` vor allen Admin-Aktionen
- [ ] Multisite: `is_network_admin()` und Network-Capabilities prüfen

### File Operations
- [ ] Alle Pfade mit `sanitize_file_name()` oder `validate_file()`
- [ ] Path Traversal Prevention: `realpath()` gegen Base-Path validieren
- [ ] Keine `@` Error Suppression - stattdessen `file_exists()` Checks
- [ ] Temporäre Files mit `wp_tempnam()` und `wp_delete_file()`
- [ ] ABSPATH-Check in allen PHP-Files: `if ( ! defined( 'ABSPATH' ) ) exit;`

### Database
- [ ] Prepared Statements: `$wpdb->prepare()` für alle Queries
- [ ] Nie direkt `$wpdb->query()` mit User-Input
- [ ] Table-Namen mit `$wpdb->prefix` oder besser: Options API nutzen
- [ ] XSS-Schutz: Alle DB-Outputs escapen

### APIs & Remote Requests
- [ ] Nur `wp_remote_get()`, `wp_remote_post()` (nie curl/file_get_contents)
- [ ] Timeouts setzen: `'timeout' => 10` (max 15s)
- [ ] SSL-Verify: `'sslverify' => true` (außer Dev-Umgebung)
- [ ] Error Handling: `is_wp_error()` prüfen
- [ ] Response-Code prüfen: `wp_remote_retrieve_response_code()`

### Code Injection Prevention
- [ ] Kein `eval()`, `exec()`, `system()`, `shell_exec()`
- [ ] Kein `create_function()` (deprecated)
- [ ] Kein `extract()` mit User-Input
- [ ] Kein dynamisches `require/include` mit User-Input

---

## ⚡ Performance

### Caching
- [ ] Transients für API-Responses: `set_transient()`, `get_transient()`
- [ ] Object Cache für häufige DB-Queries
- [ ] Fragment Caching für HTML-Blöcke
- [ ] Cache-Invalidierung bei Änderungen: `delete_transient()`

### Database Queries
- [ ] Keine Queries in Loops
- [ ] `WP_Query` mit sinnvollen `posts_per_page` Limits
- [ ] `get_posts()` statt `WP_Query` wenn kein Pagination
- [ ] `'no_found_rows' => true` wenn keine Pagination
- [ ] `'update_post_meta_cache' => false` wenn Meta nicht benötigt

### Asset Loading
- [ ] CSS/JS nur wo benötigt: `wp_enqueue_*()` mit Page-Checks
- [ ] Asset Minification (bei Production)
- [ ] Lazy Loading für Scripts: `'in_footer' => true`
- [ ] Dependencies korrekt angeben in `wp_enqueue_script()`

### Lazy Loading
- [ ] Settings nur in `is_admin()` laden
- [ ] Classes nur instanziieren wenn benötigt
- [ ] Autoloading statt require_once für alle Files
- [ ] Admin-Features nicht im Frontend laden

### API Calls
- [ ] Rate Limiting für externe APIs
- [ ] Exponential Backoff bei Fehlern
- [ ] Batch-Requests statt einzelne Calls
- [ ] Background Processing für langsame Tasks (WP-Cron)

---

## 🏗️ Code Quality

### WordPress Standards
- [ ] WordPress Coding Standards (WPCS) einhalten
- [ ] PHPDoc Blocks für alle Functions/Methods
- [ ] Keine PHP Short Tags (`<?`)
- [ ] Single Quotes für Strings (außer Interpolation)

### Error Handling
- [ ] Try-Catch für externe APIs
- [ ] `WP_Error` für eigene Fehler zurückgeben
- [ ] Fehler loggen: `error_log()` mit WP_DEBUG
- [ ] User-friendly Fehlermeldungen (nie PHP-Errors zeigen)

### Hooks & Filters
- [ ] Sinnvolle Priority-Werte (Standard: 10)
- [ ] Accepted Args korrekt angeben
- [ ] Eigene Hooks für Erweiterbarkeit
- [ ] Hook-Namen mit Plugin-Prefix

### i18n (Übersetzungen)
- [ ] Alle Strings mit `__()`, `_e()`, `esc_html__()` etc.
- [ ] Textdomain überall gleich (Plugin-Slug)
- [ ] `load_plugin_textdomain()` im Constructor
- [ ] Keine Concatenation in übersetzten Strings

---

## 🧪 Testing

### Funktionale Tests
- [ ] Plugin Activation/Deactivation funktioniert
- [ ] Multisite-kompatibel (wenn relevant)
- [ ] PHP 7.4+ Kompatibilität
- [ ] WordPress 5.8+ Kompatibilität
- [ ] Keine Konflikte mit gängigen Plugins

### Sicherheitstests
- [ ] XSS-Test: JavaScript in alle Input-Felder
- [ ] SQL Injection: `' OR '1'='1` in Inputs
- [ ] CSRF-Test: Nonce-Check funktioniert
- [ ] Path Traversal: `../../` in File-Uploads

### Performance Tests
- [ ] Query Monitor für DB-Queries
- [ ] Browser DevTools für Asset-Loading
- [ ] Memory-Usage mit `memory_get_peak_usage()`
- [ ] Load-Test mit vielen Produkten/Posts

---

## 📱 Multisite

### Network Admin
- [ ] Separate Menüs für Network vs. Site Admin
- [ ] `add_menu_page()` vs. `add_network_admin_menu()`
- [ ] Settings mit `get_site_option()` statt `get_option()`
- [ ] Activation Hook: `plugins_loaded` prüfen für Netzwerk

### Berechtigungen
- [ ] Network Admin: `manage_network_plugins`
- [ ] Site Admin: `manage_options`
- [ ] Rollenbasierte Zugriffskontrolle
- [ ] Netzwerkweite Settings vs. Site-spezifisch

---

## 🚀 Deployment

### Pre-Release
- [ ] Version-Number in Plugin-Header erhöht
- [ ] CHANGELOG.md aktualisiert
- [ ] README.md auf aktuellstem Stand
- [ ] Tested up to: neueste WP-Version
- [ ] Screenshots aktualisiert (wenn UI-Änderungen)

### GitHub Release
- [ ] Tag im Format `v1.2.3` (mit "v")
- [ ] Release Notes mit Changelog
- [ ] Assets hinzufügen (Screenshots, ZIP)
- [ ] Breaking Changes markieren

### PS Update Manager Integration
- [ ] Plugin in `products-manifest.php` eingetragen
- [ ] Repo-URL korrekt (Power-Source/*)
- [ ] Admin-Hinweis für Update Manager hinzugefügt
- [ ] Self-Update-Test durchgeführt

---

## ✅ Production Ready Checklist

- [ ] Alle Sicherheits-Checks ✅
- [ ] Alle Performance-Checks ✅
- [ ] Code Quality Standards ✅
- [ ] Testing abgeschlossen ✅
- [ ] Dokumentation vollständig ✅
- [ ] GitHub Release erstellt ✅
- [ ] Manifest aktualisiert ✅

---

**Vorlage basiert auf:** PS Update Manager v2.0 Security & Performance Standards  
**Letzte Aktualisierung:** 7. Dezember 2025
