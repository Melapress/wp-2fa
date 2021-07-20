#!/usr/bin/env bash

set -e

if [ ! -f wp-2fa.php  ]; then
    echo 'This script must be run from the repository root.'
    exit 1
fi

log_step() {
    echo ${1}
}

installComposer() {
  php -r "copy('https://getcomposer.org/download/1.10.22/composer.phar', 'composer.phar');"
}

COMPOSER_COMMAND='composer'

if ! [ -x "$(command -v $COMPOSER_COMMAND)" ];
then
	installComposer
	log_step 'composer.phar is installed in the root, will be removed later on'
	COMPOSER_COMMAND='php composer.phar'
fi

REPO_ROOT=${PWD}

echo $REPO_ROOT

cd "$REPO_ROOT"

log_step "Initial clean-up"
rm -Rf vendor
rm -Rf third-party
rm -Rf third-party/vendor
rm -Rf php-scoper/vendor

log_step "Installing composer dependencies"
$COMPOSER_COMMAND install --no-dev

log_step "Removing unnecessary assets from the vendor folder"
cd "$REPO_ROOT"/vendor

find . -type d -iname .github -exec rm -rf {} +
find . -type f -iname .gitignore -exec rm {} +
find . -type f -iname LICENSE -exec rm {} +
find . -type f -iname LICENSE.txt -exec rm {} +
find . -type f -iname *.md -exec rm {} +
find . -type f -iname README.* -exec rm {} +
find . -type f -iname package.json -exec rm {} +
find . -type f -iname composer.json -exec rm {} +
find . -type f -iname phpunit.xml* -exec rm {} +
find . -type f -iname phpstan.* -exec rm {} +
find . -type f -iname phpdox.* -exec rm {} +
find . -type f -iname Dockerfile* -exec rm {} +

# Remove tests & docs
find . -type d -iname tests -exec rm -rf {} +
find . -type d -iname docs -exec rm -rf {} +

cd "$REPO_ROOT"

log_step "Running PHP Scoper"

rm -rf ../php-scoper
mkdir ../php-scoper
cp ./php-scoper/composer.json ../php-scoper
cp ./php-scoper/composer.lock ../php-scoper

$COMPOSER_COMMAND --working-dir=../php-scoper install

php -d memory_limit=1024M ../php-scoper/vendor/bin/php-scoper add-prefix --prefix=WP2FA_Vendor --output-dir=$REPO_ROOT/third-party/vendor --force

$COMPOSER_COMMAND run autoload-third-party

cp -R vendor/freemius third-party

find vendor -type d -not -name 'composer' -print0 | xargs -0 -I {} rm -Rf {}

$COMPOSER_COMMAND dump-autoload

php -r "unlink('composer.phar');"
log_step 'composer.phar has been removed'

log_step "Done!"
