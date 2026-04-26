#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHPUNIT_PHAR="${ROOT_DIR}/tests/phpunit.phar"

if [[ ! -f "${PHPUNIT_PHAR}" ]]; then
  echo "phpunit.phar not found at ${PHPUNIT_PHAR}" >&2
  echo "Download it with:" >&2
  echo "curl -L https://phar.phpunit.de/phpunit-10.phar -o ${PHPUNIT_PHAR}" >&2
  exit 1
fi

php "${PHPUNIT_PHAR}" -c "${ROOT_DIR}/phpunit.xml.dist"
