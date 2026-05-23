#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="gls-shipping-for-woocommerce"
VERSION_FILE="${ROOT_DIR}/VERSION"
DISTIGNORE_FILE="${ROOT_DIR}/.distignore"
BUILD_DIR="${ROOT_DIR}/build"
STAGING_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

if [[ ! -f "${VERSION_FILE}" ]]; then
	echo "Missing VERSION file: ${VERSION_FILE}" >&2
	exit 1
fi

VERSION="$(tr -d '[:space:]' < "${VERSION_FILE}")"
ZIP_FILE="${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"

rm -rf "${STAGING_DIR}"
mkdir -p "${STAGING_DIR}"
mkdir -p "${BUILD_DIR}"

RSYNC_EXCLUDES=()

if [[ -f "${DISTIGNORE_FILE}" ]]; then
	while IFS= read -r line; do
		[[ -z "${line}" ]] && continue
		RSYNC_EXCLUDES+=("--exclude=${line}")
	done < "${DISTIGNORE_FILE}"
fi

rsync -a "${RSYNC_EXCLUDES[@]}" "${ROOT_DIR}/" "${STAGING_DIR}/"

if [[ -f "${STAGING_DIR}/composer.json" ]]; then
	if ! command -v composer >/dev/null 2>&1; then
		echo "Composer is required to build ${PLUGIN_SLUG}." >&2
		exit 1
	fi

	(
		cd "${STAGING_DIR}"
		composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction
	)
	find "${STAGING_DIR}/vendor" -type f -name '*.php' -print0 | xargs -0 -n1 php -l
	find "${STAGING_DIR}/vendor" -type d -name '.git' -prune -exec rm -rf {} + 2>/dev/null || true
	find "${STAGING_DIR}/vendor" -type f \( -name '.gitignore' -o -name '.gitattributes' \) -delete 2>/dev/null || true
fi

rm -f "${ZIP_FILE}"
(
	cd "${BUILD_DIR}"
	zip -rq "${ZIP_FILE}" "${PLUGIN_SLUG}"
)

echo "Created package: ${ZIP_FILE}"
