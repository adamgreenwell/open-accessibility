#!/usr/bin/env bash
# Run local release checks that do not require a WordPress test install.

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

PLUGIN_SLUG="open-accessibility"
PLUGIN_FILE="open-accessibility.php"
README_FILE="README.txt"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

cd "${PLUGIN_DIR}"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN} Open Accessibility Release Checks${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

echo -e "${YELLOW}Checking version consistency...${NC}"
VERSION=$(grep -E "^[[:space:]]*\\* Version:" "${PLUGIN_FILE}" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r ')
STABLE_TAG=$(grep -E "^Stable tag:" "${README_FILE}" | head -1 | sed -E 's/Stable tag:[[:space:]]*//' | tr -d '\r ')
CONSTANT_VERSION=$(grep -E "OPEN_ACCESSIBILITY_VERSION" "${PLUGIN_FILE}" | head -1 | sed -E "s/.*OPEN_ACCESSIBILITY_VERSION', '([^']+)'.*/\\1/" | tr -d '\r ')

if [[ -z "${VERSION}" || -z "${STABLE_TAG}" || -z "${CONSTANT_VERSION}" ]]; then
	echo -e "${RED}Could not read version from plugin header, ${README_FILE}, or OPEN_ACCESSIBILITY_VERSION.${NC}"
	exit 1
fi

if [[ "${VERSION}" != "${STABLE_TAG}" || "${VERSION}" != "${CONSTANT_VERSION}" ]]; then
	echo -e "${RED}Version mismatch: header=${VERSION}, stable_tag=${STABLE_TAG}, constant=${CONSTANT_VERSION}.${NC}"
	exit 1
fi
echo -e "${GREEN}OK${NC} Version ${VERSION}"
echo ""

echo -e "${YELLOW}Checking PHP syntax...${NC}"
find . \
	-path './.git' -prune -o \
	-path './vendor' -prune -o \
	-path './build' -prune -o \
	-path './dist' -prune -o \
	-path './node_modules' -prune -o \
	-path "./${PLUGIN_SLUG}-svn" -prune -o \
	-name '*.php' -print0 | xargs -0 -n1 php -l
echo -e "${GREEN}OK${NC} PHP syntax"
echo ""

echo -e "${YELLOW}Checking JavaScript syntax...${NC}"
if ! command -v node >/dev/null 2>&1; then
	echo -e "${RED}Node.js is required for JavaScript syntax checks.${NC}"
	exit 1
fi

find assets/js -name '*.js' -print0 | xargs -0 -n1 node --check
echo -e "${GREEN}OK${NC} JavaScript syntax"
echo ""

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
	echo -e "${YELLOW}Checking whitespace errors...${NC}"
	git diff --check
	echo -e "${GREEN}OK${NC} git diff --check"
	echo ""
fi

echo -e "${YELLOW}Validating release package...${NC}"
bash bin/build-release.sh
echo -e "${GREEN}OK${NC} Release package validates"
echo ""

echo -e "${GREEN}All release checks passed.${NC}"
