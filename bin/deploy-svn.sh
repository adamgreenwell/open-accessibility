#!/usr/bin/env bash
# Deploy Open Accessibility to the WordPress.org SVN working copy.
#
# Usage:
#   bin/deploy-svn.sh              Build and sync to SVN, stopping before commit.
#   bin/deploy-svn.sh --skip-build Use existing build output.
#   bin/deploy-svn.sh --commit     Commit after syncing and sanity checks.

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

PLUGIN_SLUG="open-accessibility"
PLUGIN_FILE="open-accessibility.php"
README_FILE="README.txt"
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SVN_DIR="${OPEN_ACCESSIBILITY_SVN_DIR:-${PLUGIN_DIR}/${PLUGIN_SLUG}-svn}"
BUILD_DIR="${PLUGIN_DIR}/build/${PLUGIN_SLUG}"

SKIP_BUILD=false
DO_COMMIT=false

for arg in "$@"; do
	case "${arg}" in
		--skip-build)
			SKIP_BUILD=true
			;;
		--commit)
			DO_COMMIT=true
			;;
		--help|-h)
			echo "Usage: bin/deploy-svn.sh [--skip-build] [--commit]"
			echo ""
			echo "  --skip-build  Skip the release build and use existing build output."
			echo "  --commit      Commit to SVN after syncing and sanity checks."
			exit 0
			;;
		*)
			echo -e "${RED}Unknown argument: ${arg}${NC}"
			echo "Run with --help to see usage."
			exit 1
			;;
	esac
done

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN} Open Accessibility SVN Deploy${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

if [[ ! -d "${SVN_DIR}" ]]; then
	echo -e "${RED}SVN directory not found at ${SVN_DIR}.${NC}"
	echo "Check out the WordPress.org SVN repo first:"
	echo "  svn co https://plugins.svn.wordpress.org/${PLUGIN_SLUG} ${SVN_DIR}"
	exit 1
fi

if [[ ! -d "${SVN_DIR}/.svn" ]]; then
	echo -e "${RED}${SVN_DIR} is not an SVN working copy.${NC}"
	exit 1
fi

if [[ "${SKIP_BUILD}" == false ]]; then
	echo -e "${BOLD}Step 1/5: Building release${NC}"
	cd "${PLUGIN_DIR}"
	if command -v composer >/dev/null 2>&1; then
		composer build
	else
		bash bin/generate-pot.sh
		bash bin/build-release.sh
	fi
	echo ""
else
	echo -e "${BOLD}Step 1/5: Build skipped${NC}"
fi

if [[ ! -d "${BUILD_DIR}" ]]; then
	echo -e "${RED}Build directory not found at ${BUILD_DIR}.${NC}"
	echo "Run composer build first, or remove --skip-build."
	exit 1
fi

VERSION=$(grep -E "^[[:space:]]*\\* Version:" "${BUILD_DIR}/${PLUGIN_FILE}" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r ')
if [[ -z "${VERSION}" ]]; then
	echo -e "${RED}Could not determine version from build output.${NC}"
	exit 1
fi
echo -e "${YELLOW}Deploying version:${NC} ${GREEN}${VERSION}${NC}"
echo ""

echo -e "${BOLD}Step 2/5: Updating SVN working copy${NC}"
cd "${SVN_DIR}"

if svn status | grep -q '^[AMDRC]'; then
	echo -e "${RED}SVN working copy has uncommitted changes.${NC}"
	echo "Resolve or revert those changes before deploying."
	svn status | head -20
	exit 1
fi

svn update --quiet
SVN_REV=$(svn info --show-item revision)
echo -e "${GREEN}OK${NC} SVN working copy updated to revision ${SVN_REV}"
echo ""

echo -e "${BOLD}Step 3/5: Syncing build to trunk${NC}"
find "${SVN_DIR}/trunk" -mindepth 1 -delete 2>/dev/null || true

if [[ ! -d "${SVN_DIR}/.svn" ]]; then
	echo -e "${RED}SVN metadata is missing after clearing trunk. Aborting.${NC}"
	exit 1
fi

rsync -a "${BUILD_DIR}/" "${SVN_DIR}/trunk/"

FILE_COUNT=$(find "${SVN_DIR}/trunk" -type f -not -path '*/.svn*' | wc -l | tr -d ' ')
if [[ "${FILE_COUNT}" -lt 45 ]]; then
	echo -e "${RED}Only ${FILE_COUNT} files found in trunk. Build output may be incomplete.${NC}"
	exit 1
fi

echo -e "${GREEN}OK${NC} Trunk synced with ${FILE_COUNT} files"
echo ""

echo -e "${BOLD}Step 4/5: Reconciling SVN adds/removes${NC}"

ADDED=0
while IFS= read -r line; do
	file="${line:8}"
	svn add --parents --quiet "${file}"
	ADDED=$((ADDED + 1))
done < <(svn status "${SVN_DIR}/trunk" | grep '^?' || true)

REMOVED=0
while IFS= read -r line; do
	file="${line:8}"
	svn rm --quiet "${file}"
	REMOVED=$((REMOVED + 1))
done < <(svn status "${SVN_DIR}/trunk" | grep '^!' || true)

echo -e "${GREEN}OK${NC} Files added: ${ADDED}"
echo -e "${GREEN}OK${NC} Files removed: ${REMOVED}"
echo ""

TAG_DIR="${SVN_DIR}/tags/${VERSION}"

if [[ -d "${TAG_DIR}" ]] && svn info "${TAG_DIR}" >/dev/null 2>&1; then
	echo -e "${YELLOW}Tag ${VERSION} already exists; updating it.${NC}"
	find "${TAG_DIR}" -mindepth 1 -delete 2>/dev/null || true
	rsync -a "${BUILD_DIR}/" "${TAG_DIR}/"

	while IFS= read -r line; do
		file="${line:8}"
		svn add --parents --quiet "${file}"
	done < <(svn status "${TAG_DIR}" | grep '^?' || true)

	while IFS= read -r line; do
		file="${line:8}"
		svn rm --quiet "${file}"
	done < <(svn status "${TAG_DIR}" | grep '^!' || true)
else
	if [[ -d "${TAG_DIR}" ]]; then
		rm -rf "${TAG_DIR}"
	fi
	mkdir -p "${TAG_DIR}"
	rsync -a "${BUILD_DIR}/" "${TAG_DIR}/"
	svn add --parents --quiet "${TAG_DIR}"
fi

echo -e "${GREEN}OK${NC} Tag ${VERSION} prepared"
echo ""

echo -e "${BOLD}Step 5/5: Review${NC}"
echo ""

CHANGES=$(svn status "${SVN_DIR}/trunk" "${TAG_DIR}" | grep -c '^[AMDR]' || true)
ADD_COUNT=$(svn status "${SVN_DIR}/trunk" "${TAG_DIR}" | grep -c '^A' || true)
MOD_COUNT=$(svn status "${SVN_DIR}/trunk" "${TAG_DIR}" | grep -c '^M' || true)
DEL_COUNT=$(svn status "${SVN_DIR}/trunk" "${TAG_DIR}" | grep -c '^D' || true)

echo -e "${CYAN}SVN status summary${NC}"
echo -e "  Added:    ${GREEN}${ADD_COUNT}${NC}"
echo -e "  Modified: ${YELLOW}${MOD_COUNT}${NC}"
echo -e "  Deleted:  ${RED}${DEL_COUNT}${NC}"
echo -e "  Total:    ${BOLD}${CHANGES}${NC} changed files"
echo ""

echo -e "${CYAN}Plugin file changes${NC}"
svn status "${SVN_DIR}/trunk" | grep '^[AMDR]' | head -30 || echo "  (none)"
echo ""

echo -e "${CYAN}Sanity checks${NC}"
ALL_OK=true
CRITICAL_FILES=(
	"${PLUGIN_FILE}"
	"${README_FILE}"
	"index.php"
	"uninstall.php"
	"assets/js/open-accessibility-public.js"
	"assets/css/open-accessibility-public.css"
	"public/class-open-accessibility-public.php"
	"public/partials/widget-template.php"
	"includes/class-open-accessibility.php"
	"languages/open-accessibility.pot"
)

for file in "${CRITICAL_FILES[@]}"; do
	if [[ -f "${SVN_DIR}/trunk/${file}" ]]; then
		echo -e "  ${GREEN}OK${NC} trunk/${file}"
	else
		echo -e "  ${RED}MISSING${NC} trunk/${file}"
		ALL_OK=false
	fi
done

for path in bin build dist docs tests node_modules vendor .github composer.json composer.lock Makefile TRANSLATION_MAINTENANCE.md open-accessibility-svn; do
	if [[ -e "${SVN_DIR}/trunk/${path}" ]]; then
		echo -e "  ${RED}FOUND${NC} trunk/${path}"
		ALL_OK=false
	fi
done

TRUNK_VERSION=$(grep -E "^[[:space:]]*\\* Version:" "${SVN_DIR}/trunk/${PLUGIN_FILE}" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r ')
README_VERSION=$(grep -E "^Stable tag:" "${SVN_DIR}/trunk/${README_FILE}" | head -1 | sed -E 's/Stable tag:[[:space:]]*//' | tr -d '\r ')
CONSTANT_VERSION=$(grep -E "OPEN_ACCESSIBILITY_VERSION" "${SVN_DIR}/trunk/${PLUGIN_FILE}" | head -1 | sed -E "s/.*OPEN_ACCESSIBILITY_VERSION', '([^']+)'.*/\\1/" | tr -d '\r ')
if [[ "${TRUNK_VERSION}" == "${README_VERSION}" && "${TRUNK_VERSION}" == "${CONSTANT_VERSION}" ]]; then
	echo -e "  ${GREEN}OK${NC} Version consistent: ${TRUNK_VERSION}"
else
	echo -e "  ${RED}MISMATCH${NC} plugin=${TRUNK_VERSION} readme=${README_VERSION} constant=${CONSTANT_VERSION}"
	ALL_OK=false
fi
echo ""

if [[ "${ALL_OK}" == false ]]; then
	echo -e "${RED}Sanity checks failed. Do not commit.${NC}"
	exit 1
fi

if [[ "${DO_COMMIT}" == true ]]; then
	echo -e "${YELLOW}Committing to WordPress.org SVN...${NC}"
	cd "${SVN_DIR}"
	svn commit -m "Deploy version ${VERSION}"
	echo -e "${GREEN}Deployed ${VERSION} to WordPress.org.${NC}"
else
	echo -e "${GREEN}Ready to commit after review.${NC}"
	echo ""
	echo "Review the changes above, then commit with:"
	echo "  cd ${SVN_DIR} && svn commit -m \"Deploy version ${VERSION}\""
	echo ""
	echo "Or re-run:"
	echo "  bin/deploy-svn.sh --skip-build --commit"
fi
