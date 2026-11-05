#!/bin/bash
# Runs the checks of the GitHub Actions workflow (.github/workflows/ci.yml) in the codespace, for one Moodle version.
#
# Usage: .devcontainer/run-tests.sh [MOODLE_BRANCH] [pgsql|mariadb] [--no-behat]
# Default: MOODLE_503_STABLE with PostgreSQL, Behat included. Each run installs a fresh Moodle in ~/run.
# Output ends with one line per step, "PASS step" or "FAIL step", and the exit code is the number of failed steps.

BRANCH=${1:-MOODLE_503_STABLE}
DB=${2:-pgsql}
BEHAT=1
[ "$3" = "--no-behat" ] && BEHAT=0
PLUGIN=$(cd "$(dirname "$0")/.." && pwd)

export PATH="$HOME/ci/bin:$HOME/ci/vendor/bin:$PATH"
export NVM_DIR=${NVM_DIR:-/usr/local/share/nvm}
export MOODLE_BRANCH=$BRANCH
export DB
if [ "$DB" = "mariadb" ]; then
    DBARGS=(--db-user=root --db-pass=root)
else
    DBARGS=(--db-user=postgres --db-pass=postgres)
fi

bash "$(dirname "$0")/databases.sh"

# A fresh start: the databases and the Behat browser of an earlier run are removed.
PGPASSWORD=postgres psql -q -h 127.0.0.1 -U postgres -d postgres -c 'DROP DATABASE IF EXISTS moodle' 2> /dev/null
mariadb -h 127.0.0.1 -uroot -proot -e 'DROP DATABASE IF EXISTS moodle' 2> /dev/null
docker rm -f selenium > /dev/null 2>&1

rm -rf "$HOME/run"
mkdir -p "$HOME/run"
cd "$HOME/run" || exit 1

RESULTS=()
FAILED=0
step() {
    local name=$1
    shift
    echo "::: $name"
    if "$@"; then
        RESULTS+=("PASS $name")
    else
        RESULTS+=("FAIL $name")
        FAILED=$((FAILED + 1))
    fi
}

step "Install ($BRANCH, $DB)" moodle-plugin-ci install --plugin "$PLUGIN" --db-host=127.0.0.1 "${DBARGS[@]}"
if [ "$FAILED" -eq 0 ]; then
    step "PHP Lint" moodle-plugin-ci phplint
    step "Moodle Code Checker" moodle-plugin-ci phpcs --max-warnings 0
    step "Moodle PHPDoc Checker" moodle-plugin-ci phpdoc --max-warnings 0
    step "Validating" moodle-plugin-ci validate
    step "Check upgrade savepoints" moodle-plugin-ci savepoints
    step "Mustache Lint" moodle-plugin-ci mustache
    step "Grunt" moodle-plugin-ci grunt --max-lint-warnings 0
    step "PHPUnit tests" moodle-plugin-ci phpunit --fail-on-warning
    if [ "$BEHAT" -eq 1 ]; then
        # The browser image is pulled first: the tool gives Selenium only a few seconds to start.
        docker pull -q selenium/standalone-chrome:4 > /dev/null
        step "Behat features" moodle-plugin-ci behat --profile chrome --scss-deprecations --start-servers
    fi
fi

echo
echo "=== Results: $BRANCH, $DB"
printf '%s\n' "${RESULTS[@]}"
exit "$FAILED"
