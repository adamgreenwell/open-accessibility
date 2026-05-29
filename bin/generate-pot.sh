#!/usr/bin/env bash
# Generate the translation template for Open Accessibility.

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PLUGIN_SLUG="open-accessibility"
PLUGIN_NAME="Open Accessibility"
PLUGIN_FILE="open-accessibility.php"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
POT_FILE="${PLUGIN_DIR}/languages/${PLUGIN_SLUG}.pot"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN} Open Accessibility POT Generator${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${YELLOW}Plugin directory:${NC} ${PLUGIN_DIR}"
echo -e "${YELLOW}Output file:${NC}      ${POT_FILE}"
echo ""

if ! command -v wp >/dev/null 2>&1; then
	echo -e "${RED}WP-CLI is not installed or not in PATH.${NC}"
	echo "Install WP-CLI first: https://wp-cli.org/"
	exit 1
fi

if ! command -v php >/dev/null 2>&1; then
	echo -e "${RED}PHP is not installed or not in PATH.${NC}"
	exit 1
fi

WP_BIN="$(command -v wp)"
PHP_BIN="$(command -v php)"

mkdir -p "$(dirname "${POT_FILE}")"

VERSION=$(grep -E "^[[:space:]]*\\* Version:" "${PLUGIN_DIR}/${PLUGIN_FILE}" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r ')
if [[ -z "${VERSION}" ]]; then
	echo -e "${YELLOW}Could not determine plugin version; using development.${NC}"
	VERSION="development"
fi

echo -e "${YELLOW}Plugin version:${NC} ${GREEN}${VERSION}${NC}"
echo ""
echo -e "${YELLOW}Generating translation template...${NC}"

WP_CLI_CONFIG_PATH=/dev/null \
"${PHP_BIN}" -d "error_reporting=E_ALL&~E_DEPRECATED&~E_USER_DEPRECATED" "${WP_BIN}" i18n make-pot "${PLUGIN_DIR}" "${POT_FILE}" \
	--domain="${PLUGIN_SLUG}" \
	--package-name="${PLUGIN_NAME}" \
	--headers='{"Report-Msgid-Bugs-To":"https://wordpress.org/support/plugin/open-accessibility/"}' \
	--exclude="build,dist,node_modules,tests,vendor,.git,.svn,*-svn,open-accessibility-svn,bin,docs,*.bak,*.tmp" \
	--skip-js \
	--skip-audit \
	--allow-root 2>&1 || {
		echo ""
		echo -e "${YELLOW}WP-CLI returned a non-zero status. Checking whether the POT file was still created...${NC}"
		echo ""
	}

if [[ ! -f "${POT_FILE}" ]]; then
	echo -e "${RED}POT file was not created.${NC}"
	exit 1
fi

if grep -qE 'open-accessibility-svn|/\.svn|#: .*bin/|#: .*docs/' "${POT_FILE}"; then
	echo -e "${RED}POT file contains development/SVN references.${NC}"
	grep -nE 'open-accessibility-svn|/\.svn|#: .*bin/|#: .*docs/' "${POT_FILE}" | head -20
	exit 1
fi

STRING_COUNT=$(grep -c "^msgid" "${POT_FILE}" || echo "0")
FILE_SIZE=$(du -h "${POT_FILE}" | awk '{print $1}')

echo ""
echo -e "${GREEN}Translation template generated.${NC}"
echo ""
echo -e "  File:          ${POT_FILE}"
echo -e "  Version:       ${VERSION}"
echo -e "  Strings:       ${STRING_COUNT}"
echo -e "  File size:     ${FILE_SIZE}"
echo ""
