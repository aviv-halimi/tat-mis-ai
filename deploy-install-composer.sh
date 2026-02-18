#!/bin/bash
# Run this on the Plesk server (e.g. via SSH) from the project root to install Composer deps (QBO SDK).
# Usage: bash deploy-install-composer.sh   OR   chmod +x deploy-install-composer.sh && ./deploy-install-composer.sh

set -e
cd "$(dirname "$0")"

if [ ! -f composer.json ]; then
  echo "Error: composer.json not found. Run this script from the project root."
  exit 1
fi

echo "Installing Composer dependencies (QBO SDK, etc.)..."
if command -v composer &>/dev/null; then
  composer install --no-dev
elif [ -n "$(which php)" ]; then
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php composer-setup.php --quiet
  php composer.phar install --no-dev
  rm -f composer-setup.php composer.phar
else
  echo "Error: Neither 'composer' nor 'php' found. Install PHP and Composer first."
  exit 1
fi

echo "Done. Checking QBO SDK..."
if [ -d vendor/quickbooks/v3-php-sdk ]; then
  echo "QBO SDK installed at vendor/quickbooks/v3-php-sdk"
else
  echo "Warning: vendor/quickbooks/v3-php-sdk not found. Check composer.json and run again."
  exit 1
fi
