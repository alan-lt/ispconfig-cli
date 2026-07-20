# Agent Rules

## Code Style

- **KISS** — Keep It Simple, Stupid. No unnecessary abstractions, helpers, or wrappers.
- Reuse existing functions instead of creating new ones. Check what already exists in `soap_functions.php` before adding anything.
- Do not create helper/utility functions when the same thing can be easy done inline with existing code.
- CLI scripts should follow the same patterns as existing ones (see `sites_web_domain_get.php` as reference).
- Remove unused code immediately. Do not leave dead functions.

## Security

- No real credentials, IPs, hostnames, or domain names in code or documentation.
- All sensitive config goes in `.env` (which is in `.gitignore`).
- Always check for data leaks before committing.

## Language

- All code, comments, commit messages, and documentation must be in **English only**.

## Project Structure

- `soap_functions.php` — all shared SOAP wrapper functions
- `soap_env.php` — connection config and env loading
- `*.php` — CLI scripts, one per action
- CLI scripts are thin: parse args, call function, print result

## Testing & Docs

Before finishing any change:

- Run the test suite and make sure it passes: `./tests/run.sh`
- When adding testable (pure) logic, add a case to `tests/unit_functions.php`.
- After changing anything in `soap_functions.php`, regenerate the function reference:
  `./functions_help.php --markdown > README-FUNCTIONS.md`
