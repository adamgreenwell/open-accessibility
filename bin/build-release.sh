#!/usr/bin/env bash
# Build a clean WordPress.org-ready release package.

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

PLUGIN_SLUG="open-accessibility"
PLUGIN_FILE="open-accessibility.php"
README_FILE="README.txt"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${PLUGIN_DIR}/build"
DIST_DIR="${PLUGIN_DIR}/dist"
RELEASE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN} Open Accessibility Release Build${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${YELLOW}Plugin directory:${NC} ${PLUGIN_DIR}"
echo -e "${YELLOW}Build directory:${NC}  ${BUILD_DIR}"
echo -e "${YELLOW}Dist directory:${NC}   ${DIST_DIR}"
echo ""

if [[ ! -f "${PLUGIN_DIR}/.distignore" ]]; then
	echo -e "${RED}Missing .distignore. Refusing to build an unconstrained package.${NC}"
	exit 1
fi

echo -e "${YELLOW}Cleaning previous build output...${NC}"
rm -rf "${BUILD_DIR}" "${DIST_DIR}"
mkdir -p "${RELEASE_DIR}" "${DIST_DIR}"

echo -e "${YELLOW}Copying runtime plugin files...${NC}"
rsync -a \
	--delete \
	--exclude-from="${PLUGIN_DIR}/.distignore" \
	--exclude='build' \
	--exclude='dist' \
	--exclude='vendor' \
	"${PLUGIN_DIR}/" \
	"${RELEASE_DIR}/"

VERSION=$(grep -E "^[[:space:]]*\\* Version:" "${RELEASE_DIR}/${PLUGIN_FILE}" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r ')
if [[ -z "${VERSION}" ]]; then
	echo -e "${RED}Could not determine plugin version from ${PLUGIN_FILE}.${NC}"
	exit 1
fi

STABLE_TAG=$(grep -E "^Stable tag:" "${RELEASE_DIR}/${README_FILE}" | head -1 | sed -E 's/Stable tag:[[:space:]]*//' | tr -d '\r ')
if [[ "${VERSION}" != "${STABLE_TAG}" ]]; then
	echo -e "${RED}Version mismatch: plugin header is ${VERSION}, ${README_FILE} stable tag is ${STABLE_TAG}.${NC}"
	exit 1
fi

CONSTANT_VERSION=$(grep -E "OPEN_ACCESSIBILITY_VERSION" "${RELEASE_DIR}/${PLUGIN_FILE}" | head -1 | sed -E "s/.*OPEN_ACCESSIBILITY_VERSION', '([^']+)'.*/\\1/" | tr -d '\r ')
if [[ "${VERSION}" != "${CONSTANT_VERSION}" ]]; then
	echo -e "${RED}Version mismatch: plugin header is ${VERSION}, OPEN_ACCESSIBILITY_VERSION is ${CONSTANT_VERSION}.${NC}"
	exit 1
fi

echo -e "${YELLOW}Release version:${NC} ${GREEN}${VERSION}${NC}"
echo ""

CRITICAL_FILES=(
	"${PLUGIN_FILE}"
	"${README_FILE}"
	"index.php"
	"uninstall.php"
	"admin/class-open-accessibility-admin.php"
	"admin/partials/admin-display.php"
	"assets/css/open-accessibility-admin.css"
	"assets/css/open-accessibility-public.css"
	"assets/css/skip-link.css"
	"assets/js/open-accessibility-admin.js"
	"assets/js/open-accessibility-public.js"
	"includes/ajax/class-open-accessibility-ajax.php"
	"includes/class-open-accessibility-loader.php"
	"includes/class-open-accessibility-shortcode.php"
	"includes/class-open-accessibility-statement-generator.php"
	"includes/class-open-accessibility-utils.php"
	"includes/class-open-accessibility-widget.php"
	"includes/class-open-accessibility.php"
	"includes/database/class-open-accessibility-db.php"
	"languages/open-accessibility.pot"
	"public/class-open-accessibility-public.php"
	"public/partials/widget-template.php"
)

echo -e "${YELLOW}Checking critical files...${NC}"
for file in "${CRITICAL_FILES[@]}"; do
	if [[ ! -f "${RELEASE_DIR}/${file}" ]]; then
		echo -e "${RED}Missing required release file: ${file}${NC}"
		exit 1
	fi
	echo -e "${GREEN}OK${NC} ${file}"
done
echo ""

DISALLOWED_PATHS=(
	".distignore"
	".gitattributes"
	".github"
	".gitignore"
	"Makefile"
	"TRANSLATION_MAINTENANCE.md"
	"bin"
	"build"
	"composer.json"
	"composer.lock"
	"dist"
	"docs"
	"node_modules"
	"open-accessibility-svn"
	"package-lock.json"
	"package.json"
	"phpcs.xml"
	"phpcs.xml.dist"
	"pnpm-lock.yaml"
	"tests"
	"vendor"
	"yarn.lock"
)

echo -e "${YELLOW}Checking for development-only files...${NC}"
for path in "${DISALLOWED_PATHS[@]}"; do
	if [[ -e "${RELEASE_DIR}/${path}" ]]; then
		echo -e "${RED}Development-only path included in build: ${path}${NC}"
		exit 1
	fi
done

UNWANTED_FILES=$(find "${RELEASE_DIR}" \( -name '.DS_Store' -o -name '*.log' -o -name '*.sql' -o -name '*.sqlite' \) -print)
if [[ -n "${UNWANTED_FILES}" ]]; then
	echo -e "${RED}Unwanted local/dev files were included in the build:${NC}"
	echo "${UNWANTED_FILES}"
	exit 1
fi
echo -e "${GREEN}OK${NC} No development-only paths found"
echo ""

FILE_COUNT=$(find "${RELEASE_DIR}" -type f | wc -l | tr -d ' ')
if [[ "${FILE_COUNT}" -lt 45 ]]; then
	echo -e "${RED}Only ${FILE_COUNT} files found in build output. Build may be incomplete.${NC}"
	exit 1
fi

ZIP_FILE="${DIST_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

echo -e "${YELLOW}Creating release zip...${NC}"
(
	cd "${BUILD_DIR}"
	zip -rq "${ZIP_FILE}" "${PLUGIN_SLUG}"
)

echo -e "${YELLOW}Testing release zip...${NC}"
unzip -t "${ZIP_FILE}" >/dev/null

BAD_ZIP_ENTRIES=$(zipinfo -1 "${ZIP_FILE}" | grep -E "^${PLUGIN_SLUG}/(\\.distignore|\\.gitattributes|\\.github|\\.gitignore|Makefile|TRANSLATION_MAINTENANCE\\.md|bin|build|composer\\.(json|lock)|dist|docs|node_modules|open-accessibility-svn|package(-lock)?\\.json|phpcs\\.xml(\\.dist)?|pnpm-lock\\.yaml|tests|vendor|yarn\\.lock)(/|$)" || true)
if [[ -n "${BAD_ZIP_ENTRIES}" ]]; then
	echo -e "${RED}Development-only files were included in the release zip:${NC}"
	echo "${BAD_ZIP_ENTRIES}"
	exit 1
fi

SOURCE_SIZE=$(du -sh "${PLUGIN_DIR}" | awk '{print $1}')
BUILD_SIZE=$(du -sh "${RELEASE_DIR}" | awk '{print $1}')
ZIP_SIZE=$(du -sh "${ZIP_FILE}" | awk '{print $1}')

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN} Build complete${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "  Version:       ${GREEN}${VERSION}${NC}"
echo -e "  Files:         ${FILE_COUNT}"
echo -e "  Source size:   ${SOURCE_SIZE}"
echo -e "  Build size:    ${BUILD_SIZE}"
echo -e "  Zip size:      ${ZIP_SIZE}"
echo -e "  Release file:  ${BOLD}${ZIP_FILE}${NC}"
echo ""
