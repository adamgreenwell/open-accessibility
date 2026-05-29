# Makefile for Open Accessibility plugin release tooling.

.PHONY: help check i18n pot pot-shell build build-zip deploy-svn clean install-wpcli check-tools

# Default target
help:
	@echo "Available commands:"
	@echo "  make check       - Run release checks and validate the package"
	@echo "  make i18n        - Generate languages/open-accessibility.pot"
	@echo "  make pot         - Alias for make i18n"
	@echo "  make build       - Generate POT and build a release zip"
	@echo "  make build-zip   - Alias for make build"
	@echo "  make deploy-svn  - Sync build output to WordPress.org SVN for review"
	@echo "  make clean       - Remove build and dist output"
	@echo "  make help        - Show this help message"

check:
	@./bin/check-release.sh

i18n pot pot-shell:
	@./bin/generate-pot.sh

build build-zip:
	@./bin/generate-pot.sh
	@./bin/build-release.sh

deploy-svn:
	@./bin/deploy-svn.sh

clean:
	@rm -rf build dist

# Install WP-CLI if not present
install-wpcli:
	@echo "Installing WP-CLI..."
	@if command -v wp >/dev/null 2>&1; then \
		echo "WP-CLI is already installed."; \
	else \
		curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar; \
		chmod +x wp-cli.phar; \
		sudo mv wp-cli.phar /usr/local/bin/wp; \
		echo "WP-CLI installed successfully."; \
	fi

# Check if all required tools are available
check-tools:
	@echo "Checking required tools..."
	@if command -v wp >/dev/null 2>&1; then \
		echo "OK WP-CLI is available"; \
	else \
		echo "MISSING WP-CLI is not available"; \
	fi
	@if command -v node >/dev/null 2>&1; then \
		echo "OK Node.js is available"; \
	else \
		echo "MISSING Node.js is not available"; \
	fi
	@for script in bin/generate-pot.sh bin/build-release.sh bin/check-release.sh bin/deploy-svn.sh; do \
		if [ -x "$$script" ]; then \
			echo "OK $$script is executable"; \
		else \
			echo "MISSING $$script is not executable"; \
		fi; \
	done
