#!/bin/bash

#
# JoomlaBoost build skripta
# Kreira ZIP pakete za plugin-e, module i template override-e
# i dodaje verziju u naziv fajla.
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BUILD_DIR="$PROJECT_ROOT/build"
SRC_DIR="$PROJECT_ROOT/src"

echo "🚀 JoomlaBoost Build Script"
echo "==========================="
echo ""

# Detekcija verzije iz PHP klase Version (autoload), sa fallback-om na grep
VERSION=""
if command -v php >/dev/null 2>&1; then
    VERSION=$(php -r 'error_reporting(E_ERROR); @require __DIR__."/../vendor/autoload.php"; if (class_exists("JoomlaBoost\\\\Plugin\\\\System\\\\JoomlaBoost\\\\Version")) { echo JoomlaBoost\\\\Plugin\\\\System\\\\JoomlaBoost\\\\Version::PLUGIN_VERSION; }' 2>/dev/null || true)
fi

if [ -z "$VERSION" ] && [ -f "$PROJECT_ROOT/src/plugins/system/joomlaboost/src/Version.php" ]; then
    VERSION=$(grep -oE "PLUGIN_VERSION\s*=\s*'[^']+'" "$PROJECT_ROOT/src/plugins/system/joomlaboost/src/Version.php" | sed -E "s/.*'([^']+)'.*/\1/" | head -n1)
fi

if [ -z "$VERSION" ]; then
    VERSION="0.0.0"
fi

# Obriši postojeći build folder
if [ -d "$BUILD_DIR" ]; then
    echo "🧹 Čišćenje postojećeg build foldera..."
    rm -rf "$BUILD_DIR"
fi

# Kreiraj build folder
mkdir -p "$BUILD_DIR"

echo "📦 Kreiranje ZIP paketa..."
echo ""

# Build plugin-ova iz src/ (univerzalni plugin)
if [ -d "$SRC_DIR/plugins" ]; then
    for plugin_group in "$SRC_DIR/plugins"/*; do
        if [ -d "$plugin_group" ]; then
            group_name=$(basename "$plugin_group")

            for plugin_dir in "$plugin_group"/*; do
                if [ -d "$plugin_dir" ]; then
                    plugin_name=$(basename "$plugin_dir")

                    # Preskoči očigledne legacy/OffRoad nazive
                    case "$plugin_name" in
                        offroad*|Offroad*|offroadseo|offroadstage)
                            continue
                            ;;
                    esac

                    zip_name="plg_${group_name}_${plugin_name}-v${VERSION}.zip"

                    echo "  📦 Kreiram $zip_name..."

                    cd "$plugin_dir"
                    zip -r "$BUILD_DIR/$zip_name" . -x "*.git*" "*.DS_Store*" "Thumbs.db*"
                    cd "$PROJECT_ROOT"
                fi
            done
        fi
    done
fi

# Build modula iz src/
if [ -d "$SRC_DIR/modules" ]; then
    for module_dir in "$SRC_DIR/modules"/*; do
        if [ -d "$module_dir" ]; then
            module_name=$(basename "$module_dir")
            zip_name="mod_${module_name}-v${VERSION}.zip"
            
            echo "  📦 Kreiram $zip_name..."
            
            cd "$module_dir"
            zip -r "$BUILD_DIR/$zip_name" . -x "*.git*" "*.DS_Store*" "Thumbs.db*"
            cd "$PROJECT_ROOT"
        fi
    done
fi

# Build template override-a iz src/ (preskače offroad specifične)
if [ -d "$SRC_DIR/templates" ]; then
    for template_dir in "$SRC_DIR/templates"/*; do
        if [ -d "$template_dir" ]; then
            template_name=$(basename "$template_dir")

            # Preskoči očigledne legacy/OffRoad nazive
            case "$template_name" in
                offroad*|Offroad*|*offroad*)
                    continue
                    ;;
            esac
            zip_name="tpl_${template_name}_overrides-v${VERSION}.zip"
            
            echo "  📦 Kreiram $zip_name..."
            
            cd "$template_dir"
            zip -r "$BUILD_DIR/$zip_name" . -x "*.git*" "*.DS_Store*" "Thumbs.db*"
            cd "$PROJECT_ROOT"
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