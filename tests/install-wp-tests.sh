#!/usr/bin/env bash
#
# Install the WordPress test library for the Open Accessibility test suite.
#
# Adapted from the WordPress core scaffold (wp-cli/scaffold-command). Differences:
#   - credentials can come from the environment, so `composer test:setup` works
#     non-interactively against the project's docker-compose MariaDB;
#   - defaults match this project's docker-compose.yml (host, port, table prefix).
#
# Usage:
#   tests/install-wp-tests.sh [<db-name> <db-user> <db-pass> [<db-host>] [<wp-version>] [<skip-db-create>]]
#
# Environment overrides (used when positional args are omitted):
#   WP_TESTS_DIR            where to install the library (default: /tmp/wordpress-tests-lib)
#   WP_CORE_DIR             where to install WordPress   (default: /tmp/wordpress)
#   WP_TESTS_DB_NAME        default: wordpress_test
#   WP_TESTS_DB_USER        the user the suite runs as (default: wp_test)
#   WP_TESTS_DB_PASS        default: wp_test_pw
#   WP_TESTS_DB_HOST        default: 127.0.0.1:3309
#   WP_TESTS_DB_ROOT_USER   used only to CREATE the test database (default: root)
#   WP_TESTS_DB_ROOT_PASS   default: root
#   WP_TESTS_TABLE_PREFIX   default: wptests_
#
# The root credentials are used only for the one-off CREATE DATABASE and GRANT.
# The suite itself connects as WP_TESTS_DB_USER, so the tests never run with
# more privilege than they need.

set -euo pipefail

DB_NAME="${1:-${WP_TESTS_DB_NAME:-wordpress_test}}"
DB_USER="${2:-${WP_TESTS_DB_USER:-wp_test}}"
DB_PASS="${3:-${WP_TESTS_DB_PASS:-wp_test_pw}}"
DB_HOST="${4:-${WP_TESTS_DB_HOST:-127.0.0.1:3309}}"
WP_VERSION="${5:-${WP_TESTS_WP_VERSION:-latest}}"
SKIP_DB_CREATE="${6:-${WP_TESTS_SKIP_DB_CREATE:-false}}"

DB_ROOT_USER="${WP_TESTS_DB_ROOT_USER:-root}"
DB_ROOT_PASS="${WP_TESTS_DB_ROOT_PASS:-root}"

WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"
WP_TESTS_TABLE_PREFIX="${WP_TESTS_TABLE_PREFIX:-wptests_}"

# Keep these defaults in sync with tests/bootstrap.php, which falls back to the
# same literal path instead of sys_get_temp_dir() — that resolves per-user on
# macOS, which would point the bootstrap at a different directory than this script.

# WordPress core is fetched from the same host that serves the plugin's updates.
WP_DOWNLOAD_HOST="${WP_TESTS_DOWNLOAD_HOST:-https://wordpress.org}"

command -v curl >/dev/null 2>&1 || { echo 'curl is required.' >&2; exit 1; }
command -v svn >/dev/null 2>&1 || { echo 'svn is required (WordPress distributes the test library over SVN).' >&2; exit 1; }

# ---------------------------------------------------------------------------
# Database
#
# The test user needs its own database plus the CREATE privilege the WordPress
# test suite uses to build its tables. Rather than granting that to the test
# user globally, the database and grant are created here with the root
# credentials, and the grant is scoped to this database only.
#
# Prefer a local `mysql` client; fall back to the Docker container, which is
# where the compose example runs MariaDB.
# ---------------------------------------------------------------------------
if [ "${SKIP_DB_CREATE}" != "true" ]; then
	SQL="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;"

	echo "Ensuring database ${DB_NAME} exists and ${DB_USER} has access..."
	if command -v mysql >/dev/null 2>&1; then
		if ! mysql --user="${DB_ROOT_USER}" --password="${DB_ROOT_PASS}" --host="${DB_HOST}" \
			--protocol=tcp -e "${SQL}" 2>/dev/null; then
			echo "  Could not reach the database as ${DB_ROOT_USER} at ${DB_HOST}." >&2
			echo "  Set WP_TESTS_DB_ROOT_USER / WP_TESTS_DB_ROOT_PASS, or create the database manually" >&2
			echo "  and re-run with the skip-db-create argument set to true." >&2
			exit 1
		fi
		echo "  OK"
	elif command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
		# The compose example names its database service mariadb.
		CONTAINER=$(docker ps --filter "ancestor=mariadb:11.4" --format '{{.Names}}' | head -1)
		if [ -z "${CONTAINER}" ]; then
			CONTAINER=$(docker ps --filter "name=mariadb" --format '{{.Names}}' | head -1)
		fi

		if [ -n "${CONTAINER}" ] && docker exec -i "${CONTAINER}" \
			mariadb --user="${DB_ROOT_USER}" --password="${DB_ROOT_PASS}" -e "${SQL}" 2>/dev/null; then
			echo "  OK (via container ${CONTAINER})"
		else
			echo "  Could not reach the database as ${DB_ROOT_USER}." >&2
			echo "  Is the stack running? Try: docker compose up -d" >&2
			exit 1
		fi
	else
		echo "  Neither a mysql client nor a running Docker daemon was found." >&2
		echo "  Create the ${DB_NAME} database manually, grant ${DB_USER} access to it," >&2
		echo "  then re-run with the skip-db-create argument set to true." >&2
		exit 1
	fi
fi

# ---------------------------------------------------------------------------
# WordPress core
# ---------------------------------------------------------------------------
install_wp() {
	local archive_name version_url

	if [ -d "${WP_CORE_DIR}/wp-includes" ]; then
		echo "WordPress core already present at ${WP_CORE_DIR}."
		return
	fi

	mkdir -p "${WP_CORE_DIR}"

	if [ "${WP_VERSION}" = "latest" ]; then
		archive_name='latest.tar.gz'
		version_url="${WP_DOWNLOAD_HOST}/latest.tar.gz"
	else
		archive_name="wordpress-${WP_VERSION}.tar.gz"
		version_url="${WP_DOWNLOAD_HOST}/wordpress-${WP_VERSION}.tar.gz"
	fi

	echo "Downloading WordPress ${WP_VERSION}..."
	curl -sS -L -o "/tmp/${archive_name}" "${version_url}"

	tar --strip-components=1 -C "${WP_CORE_DIR}" -xzf "/tmp/${archive_name}"
	rm -f "/tmp/${archive_name}"

	echo "WordPress core installed to ${WP_CORE_DIR}."
}

# ---------------------------------------------------------------------------
# Test library
# ---------------------------------------------------------------------------
install_test_suite() {
	mkdir -p "${WP_TESTS_DIR}"

	# The test library tracks the core branch, not the release tag.
	local tag
	if [ "${WP_VERSION}" = "latest" ]; then
		# Discover the current stable branch from the downloaded core.
		tag="trunk"
		if [ -f "${WP_CORE_DIR}/wp-includes/version.php" ]; then
			local series
			series="$(grep -oE "\\\$wp_version = '[0-9]+\\.[0-9]+" "${WP_CORE_DIR}/wp-includes/version.php" | grep -oE '[0-9]+\.[0-9]+' || true)"
			if [ -n "${series}" ]; then
				tag="branches/${series}"
			fi
		fi
	else
		tag="tags/${WP_VERSION}"
	fi

	echo "Installing the test library from ${tag}..."
	svn export --quiet --force "https://develop.svn.wordpress.org/${tag}/tests/phpunit/includes/" "${WP_TESTS_DIR}/includes"
	svn export --quiet --force "https://develop.svn.wordpress.org/${tag}/tests/phpunit/data/" "${WP_TESTS_DIR}/data"

	if [ ! -f "${WP_TESTS_DIR}/wp-tests-config-sample.php" ]; then
		svn export --quiet --force "https://develop.svn.wordpress.org/${tag}/wp-tests-config-sample.php" "${WP_TESTS_DIR}/wp-tests-config-sample.php"
	fi

	cat > "${WP_TESTS_DIR}/wp-tests-config.php" <<-CONFIG
	<?php
	/* Generated by tests/install-wp-tests.sh — do not edit by hand. */
	define( 'ABSPATH', '${WP_CORE_DIR}/' );
	define( 'WP_DEFAULT_THEME', 'default' );
	define( 'WP_TESTS_DOMAIN', 'example.org' );
	define( 'WP_TESTS_EMAIL', 'admin@example.org' );
	define( 'WP_TESTS_TITLE', 'Open Accessibility Test Suite' );
	define( 'WP_PHP_BINARY', 'php' );
	define( 'WPLANG', '' );

	define( 'DB_NAME', '${DB_NAME}' );
	define( 'DB_USER', '${DB_USER}' );
	define( 'DB_PASSWORD', '${DB_PASS}' );
	define( 'DB_HOST', '${DB_HOST}' );
	define( 'DB_CHARSET', 'utf8' );
	define( 'DB_COLLATE', '' );

	\$table_prefix = '${WP_TESTS_TABLE_PREFIX}';
	CONFIG

	echo "Test library installed to ${WP_TESTS_DIR}."
}

install_wp
install_test_suite

cat <<-DONE

Setup complete.

  WP_TESTS_DIR=${WP_TESTS_DIR}
  WP_CORE_DIR=${WP_CORE_DIR}
  database=${DB_NAME} at ${DB_HOST}

Run the suite with:

  composer test

DONE
