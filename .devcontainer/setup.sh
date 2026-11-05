#!/bin/bash
# Installs what the tests need in the codespace, once: PHP 8.3, Composer and moodle-plugin-ci. The databases are the
# Docker images that GitHub Actions uses (PostgreSQL 17, MariaDB 11), started by .devcontainer/databases.sh.
set -e

sudo apt-get update -q
sudo DEBIAN_FRONTEND=noninteractive apt-get install -yq --no-install-recommends \
    php8.3-cli php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-mysql php8.3-pgsql php8.3-soap \
    php8.3-xml php8.3-zip php8.3-bcmath php8.3-opcache \
    postgresql-client mariadb-client default-jre-headless locales unzip git
sudo locale-gen en_AU.UTF-8
echo "max_input_vars=5000" | sudo tee /etc/php/8.3/cli/conf.d/99-moodle.ini > /dev/null

bash "$(dirname "$0")/databases.sh"

# Composer and moodle-plugin-ci.
if [ ! -x /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- --quiet --install-dir=/tmp
    sudo mv /tmp/composer.phar /usr/local/bin/composer
fi
if [ ! -d "$HOME/ci" ]; then
    composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci "$HOME/ci" ^4
fi
echo "Ready: run .devcontainer/run-tests.sh"
