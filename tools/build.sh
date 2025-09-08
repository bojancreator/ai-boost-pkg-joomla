#!/bin/bash

#
# JoomlaBoost build skripta
# Kreira ZIP fajlove za plugin-e, module i template override-e
# i dodaje verziju u ime ZIP-a za plugin-ove.
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$PROJECT_ROOT/build"
JOOMLA_DIR="$PROJECT_ROOT/joomla"

echo "🚀 JoomlaBoost Build Script"
echo "==========================="
echo ""

# Obriši postojeći build folder
if [ -d "$BUILD_DIR" ]; then
    echo "🧹 Čišćenje postojećeg build foldera..."
    rm -rf "$BUILD_DIR"
fi

# Kreiraj build folder
mkdir -p "$BUILD_DIR"

echo "📦 Kreiranje ZIP paketa..."
echo ""

# Helper funkcija za kreiranje ZIP arhive sa fallback-ovima:
# 1) zip -r (ako postoji)
# 2) tar -a -cf (bsdtar, kreira .zip po ekstenziji)
# 3) PowerShell Compress-Archive (sa cygpath konverzijom na Windows-u)
do_zip_dir() {
    local src_dir="$1"
    local dest_zip="$2"

    # Preferiraj zip ako postoji
    if command -v zip >/dev/null 2>&1; then
        (cd "$src_dir" && zip -r "$dest_zip" . -x "*.git*" "*.DS_Store*" "Thumbs.db*")
        return
    fi

    # Zatim pokušaj sa tar (bsdtar na Windows-u može da kreira .zip sa -a)
    if command -v tar >/dev/null 2>&1; then
        (cd "$src_dir" && tar -a -cf "$dest_zip" --exclude=".git" --exclude="*.DS_Store*" --exclude="Thumbs.db*" .)
        return
    fi

    # PowerShell fallback (Compress-Archive)
    local pwsh_cmd=""
    if command -v pwsh >/dev/null 2>&1; then
        pwsh_cmd="pwsh -NoProfile -Command"
    elif command -v powershell >/dev/null 2>&1; then
        pwsh_cmd="powershell -NoProfile -Command"
    fi

    if [ -n "$pwsh_cmd" ]; then
        local src_win="$src_dir"
        local dest_win="$dest_zip"
        if command -v cygpath >/dev/null 2>&1; then
            src_win=$(cygpath -w "$src_dir")
            dest_win=$(cygpath -w "$dest_zip")
        fi
        # Napomena: Compress-Archive nema jednostavan exclude; u praksi u plugin dir-ovima nema .git/DS_Store
        eval $pwsh_cmd "Compress-Archive -Path '"$src_win"\*' -DestinationPath '"$dest_win"' -Force" || {
            echo "❌ PowerShell Compress-Archive nije uspeo za: $src_dir"
            exit 1
        }
        return
    fi

    echo "❌ Nije pronađen nijedan alat za pravljenje ZIP-a (zip/tar/pwsh)." >&2
    exit 1
}

# Procitaj verziju iz Version.php
VERSION_FILE="$PROJECT_ROOT/src/plugins/system/joomlaboost/src/Version.php"
PLUGIN_VERSION=""
if [ -f "$VERSION_FILE" ]; then
    # Grep liniju sa PLUGIN_VERSION i izvuci vrednost izmedju navodnika
    RAW_LINE=$(grep -E "PLUGIN_VERSION\s*=\s*'" "$VERSION_FILE" || true)
    if [ -n "$RAW_LINE" ]; then
        PLUGIN_VERSION=$(echo "$RAW_LINE" | sed -E "s/.*PLUGIN_VERSION\s*=\s*'([^']+)'.*/\1/")
        echo "ℹ️  Detektovana verzija plugina: $PLUGIN_VERSION"
    else
        echo "⚠️  Nije pronađena PLUGIN_VERSION u Version.php; koristi se bez sufiksa"
    fi
else
    echo "⚠️  Version.php nije pronađen; koristi se bez sufiksa"
fi

# Build plugin-ova
if [ -d "$JOOMLA_DIR/plugins" ]; then
    for plugin_group in "$JOOMLA_DIR/plugins"/*; do
        if [ -d "$plugin_group" ]; then
            group_name=$(basename "$plugin_group")
            
            for plugin_dir in "$plugin_group"/*; do
                if [ -d "$plugin_dir" ]; then
                    plugin_name=$(basename "$plugin_dir")
                    if [ -n "$PLUGIN_VERSION" ]; then
                        zip_name="plg_${group_name}_${plugin_name}-${PLUGIN_VERSION}.zip"
                    else
                        zip_name="plg_${group_name}_${plugin_name}.zip"
                    fi
                    
                    echo "  📦 Kreiram $zip_name..."
                    do_zip_dir "$plugin_dir" "$BUILD_DIR/$zip_name"
                fi
            done
        fi
    done
fi

# Build modula
if [ -d "$JOOMLA_DIR/modules" ]; then
    for module_dir in "$JOOMLA_DIR/modules"/*; do
        if [ -d "$module_dir" ]; then
            module_name=$(basename "$module_dir")
            zip_name="mod_${module_name}.zip"
            
            echo "  📦 Kreiram $zip_name..."
            do_zip_dir "$module_dir" "$BUILD_DIR/$zip_name"
        fi
    done
fi

# Build template override-a
if [ -d "$JOOMLA_DIR/templates" ]; then
    for template_dir in "$JOOMLA_DIR/templates"/*; do
        if [ -d "$template_dir" ]; then
            template_name=$(basename "$template_dir")
            zip_name="tpl_${template_name}_overrides.zip"
            
            echo "  📦 Kreiram $zip_name..."
            do_zip_dir "$template_dir" "$BUILD_DIR/$zip_name"
        fi
    done
fi

echo ""
echo "✅ Build završen!"
echo "📁 Paketi su dostupni u: $BUILD_DIR"
echo ""

# Lista kreiranh paketa
if [ -d "$BUILD_DIR" ] && [ -n "$(ls -A "$BUILD_DIR")" ]; then
    echo "📦 Kreirani paketi:"
    for zip_file in "$BUILD_DIR"/*.zip; do
        if [ -f "$zip_file" ]; then
            file_size=$(du -h "$zip_file" | cut -f1)
            echo "  - $(basename "$zip_file") ($file_size)"
        fi
    done
else
    echo "⚠️  Nema kreiranh paketa."
fi

echo ""
echo "🎯 Sledeći koraci:"
echo "1. Testirati plugin-e na staging sajtu"
echo "2. Instalirati kroz Joomla admin (Extensions → Install)"
echo "3. Aktivirati plugin-e u Plugin Manager"
echo ""