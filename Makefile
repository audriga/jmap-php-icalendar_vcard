build_tools_directory=build/tools
composer=$(shell ls $(build_tools_directory)/composer_fresh.phar 2> /dev/null)
composer_lts=$(shell ls $(build_tools_directory)/composer_lts.phar 2> /dev/null)

all: init

# Remove all temporary build files
.PHONY: clean
clean:
	rm -rf build/ vendor/

# Installs composer from web if not already installed
.PHONY: composer
composer:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	./get_composer.sh
	mv composer.phar $(build_tools_directory)/composer_fresh.phar
endif
	@test -e $(build_tools_directory)/composer.phar || ln -s composer_fresh.phar $(build_tools_directory)/composer.phar

# Installs composer LTS version from web if not already installed.
# TODO Switch from pinning specific version to LTS pinning see
#   https://github.com/composer/composer/issues/10682
.PHONY: composer_lts
composer_lts:
ifeq (, $(composer_lts))
	@echo "No composer LTS command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	./get_composer.sh --2.2
	mv composer.phar $(build_tools_directory)/composer_lts.phar
endif

# Initialize project. Run this before any other target.
.PHONY: init
init: composer
	rm $(build_tools_directory)/composer.phar || true
	ln $(build_tools_directory)/composer_fresh.phar $(build_tools_directory)/composer.phar
	php $(build_tools_directory)/composer.phar install --prefer-dist --no-dev

# Update dependencies and make dev tools available for development
.PHONY: update
update: composer
	php $(build_tools_directory)/composer.phar update --prefer-dist

# Switch to PHP 8 mode. In case you need to build for PHP 8
# WARNING this will change the composer.json file
.PHONY: php81_mode
php81_mode: composer
	git checkout composer.json composer.lock
	make init
	php $(build_tools_directory)/composer.phar update --prefer-dist --no-dev

	# Lint for installed PHP version (should be 8.1)
	sh -c "! (find . -type f -name \"*.php\" -not -path \"./build/*\" $1 -exec php -l -n {} \; | grep -v \"No syntax errors detected\")"

# Linting with PHP-CS
.PHONY: lint
lint: composer
	php $(build_tools_directory)/composer.phar install --prefer-dist
	vendor/bin/phpcs src/

.PHONY: unit_test
unit_test: composer
	php $(build_tools_directory)/composer.phar install --prefer-dist
	vendor/bin/phpunit -c tests/phpunit.xml --testdox

.PHONY: fulltest
fulltest: lint unit_test
