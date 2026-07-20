#!/usr/bin/env bash
#
# Test runner for ispconfig-cli.
#   ./tests/run.sh
#
# Phase 1: lint every *.php in the project root (php -l).
# Phase 2: unit tests for the pure library functions (tests/unit_functions.php).
# Exit code is non-zero if anything fails (suitable for CI / pre-commit).

set -u
cd "$(dirname "$0")/.."

fail=0

echo "== Phase 1: lint =="
for f in *.php; do
	if php -l "$f" >/dev/null 2>&1; then
		echo "ok   lint $f"
	else
		echo "NOT OK lint $f"
		php -l "$f"
		fail=1
	fi
done

echo
echo "== Phase 2: unit tests =="
if ! php tests/unit_functions.php; then
	fail=1
fi

echo
if [ "$fail" -eq 0 ]; then
	echo "RESULT: all tests passed"
else
	echo "RESULT: FAILURES"
fi
exit "$fail"
