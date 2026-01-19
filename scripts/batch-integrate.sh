#!/bin/bash
#
# PS Update Manager - Batch Integration Script
# Fügt den Integration-Code automatisch in mehrere Plugins ein
#
# Verwendung:
#   chmod +x batch-integrate.sh
#   ./batch-integrate.sh
#

# Farben für Output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "======================================"
echo "PS Update Manager - Batch Integration"
echo "======================================"
echo ""

# Plugins-Verzeichnis
PLUGINS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"

# Liste deiner Plugins
# Format: "ordner-name|plugin-datei.php|Anzeigename|GitHub Repo"
PLUGINS=(
    "default-theme|default-theme.php|Standard Theme|Power-Source/default-theme"
    "events-and-bookings|events-and-bookings.php|Events and Bookings|Power-Source/events-and-bookings"
    "marketpress|marketpress.php|MarketPress|Power-Source/marketpress"
    "powerform|powerform.php|PowerForm|Power-Source/powerform"
    "ps-chat|psource-chat.php|PS Chat|Power-Source/ps-chat"
    "ps-live-debug|class-ps-live-debug.php|PS Live Debug|Power-Source/ps-live-debug"
    "psource-link-checker|broken-link-checker.php|PS Link Checker|Power-Source/psource-link-checker"
    "upfront-builder|upfront-theme-exporter.php|Upfront Builder|Power-Source/upfront-builder"
)

# Backup-Verzeichnis erstellen
BACKUP_DIR="$PLUGINS_DIR/ps-update-manager/backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

echo "Backup-Verzeichnis: $BACKUP_DIR"
echo ""

# Funktion: Integration-Code erstellen
create_integration_code() {
    local slug="$1"
    local name="$2"
    local github_repo="$3"
    
    cat <<EOF

/**
 * @@@@@@@@@@@@@@@@@ PS UPDATE MANAGER @@@@@@@@@@@
 * Automatische Updates via PS Update Manager
 **/
add_action( 'plugins_loaded', function() {
    if ( function_exists( 'ps_register_product' ) ) {
        ps_register_product( array(
            'slug'          => '$slug',
            'name'          => '$name',
            'version'       => '1.0.0', // TODO: Aktuelle Version eintragen
            'type'          => 'plugin',
            'file'          => __FILE__,
            'github_repo'   => '$github_repo',
            'docs_url'      => 'https://power-source.github.io/$slug/',
            'support_url'   => 'https://github.com/$github_repo/issues',
            'changelog_url' => 'https://github.com/$github_repo/releases',
        ) );
    }
}, 5 );

// Hinweis wenn Update Manager nicht installiert
add_action( 'admin_notices', function() {
    if ( ! function_exists( 'ps_register_product' ) && current_user_can( 'install_plugins' ) ) {
        \$screen = get_current_screen();
        if ( \$screen && in_array( \$screen->id, array( 'plugins', 'plugins-network' ) ) ) {
            echo '<div class="notice notice-info is-dismissible"><p>';
            echo '<strong>$name:</strong> ';
            echo 'Installiere den <a href="https://github.com/power-source/ps-update-manager">PS Update Manager</a> für Updates.';
            echo '</p></div>';
        }
    }
});
/**
 * @@@@@@@@@@@@@@@@@ ENDE PS UPDATE MANAGER @@@@@@@@@@@
 **/
EOF
}

# Funktion: Alte Updater-Blöcke entfernen
remove_old_updater() {
    local file="$1"
    
    # Entfernt Yanis Updater Block
    sed -i.tmp '/\/\*\*$/,/\*\*\/$/d' "$file" 2>/dev/null || true
    sed -i.tmp '/require.*psource-plugin-update/d' "$file" 2>/dev/null || true
    sed -i.tmp '/use YahnisElsts/d' "$file" 2>/dev/null || true
    sed -i.tmp '/PucFactory::buildUpdateChecker/,/;$/d' "$file" 2>/dev/null || true
    
    # Cleanup
    rm -f "${file}.tmp"
}

# Counter
processed=0
success=0
skipped=0
failed=0

# Plugins durchgehen
for plugin_info in "${PLUGINS[@]}"; do
    IFS='|' read -r folder file name repo <<< "$plugin_info"
    
    plugin_path="$PLUGINS_DIR/$folder/$file"
    
    echo -n "Verarbeite $name ($folder/$file)... "
    
    # Prüfen ob Datei existiert
    if [ ! -f "$plugin_path" ]; then
        echo -e "${RED}FEHLER: Datei nicht gefunden${NC}"
        ((failed++))
        continue
    fi
    
    # Prüfen ob bereits integriert
    if grep -q "PS UPDATE MANAGER" "$plugin_path"; then
        echo -e "${YELLOW}ÜBERSPRUNGEN (bereits integriert)${NC}"
        ((skipped++))
        continue
    fi
    
    # Backup erstellen
    cp "$plugin_path" "$BACKUP_DIR/${folder}_${file}.backup"
    
    # Alte Updater entfernen (optional)
    # remove_old_updater "$plugin_path"
    
    # Integration-Code erstellen
    integration_code=$(create_integration_code "$folder" "$name" "$repo")
    
    # Nach Plugin-Header einfügen
    # Findet die Zeile nach dem letzten */ und fügt dort ein
    awk -v code="$integration_code" '
        /\*\// && !inserted { 
            print; 
            print code; 
            inserted=1; 
            next 
        } 
        {print}
    ' "$plugin_path" > "${plugin_path}.new"
    
    # Prüfen ob erfolgreich
    if [ -s "${plugin_path}.new" ]; then
        mv "${plugin_path}.new" "$plugin_path"
        echo -e "${GREEN}✓ ERFOLG${NC}"
        ((success++))
    else
        echo -e "${RED}✗ FEHLER${NC}"
        rm -f "${plugin_path}.new"
        # Backup wiederherstellen
        cp "$BACKUP_DIR/${folder}_${file}.backup" "$plugin_path"
        ((failed++))
    fi
    
    ((processed++))
done

echo ""
echo "======================================"
echo "Integration abgeschlossen!"
echo "======================================"
echo ""
echo "Ergebnis:"
echo -e "  ${GREEN}Erfolgreich: $success${NC}"
echo -e "  ${YELLOW}Übersprungen: $skipped${NC}"
echo -e "  ${RED}Fehler: $failed${NC}"
echo "  Gesamt: $processed"
echo ""
echo "Backups gespeichert in:"
echo "  $BACKUP_DIR"
echo ""

if [ $success -gt 0 ]; then
    echo -e "${GREEN}✓${NC} Nächste Schritte:"
    echo "  1. Versionen in den Plugin-Dateien prüfen und anpassen"
    echo "  2. Plugins testen"
    echo "  3. PS Update Manager aktivieren"
    echo "  4. Im Dashboard 'Updates prüfen' klicken"
fi

echo ""
