# Agent Rules

## Code Style

- **KISS** — Keep It Simple, Stupid. No unnecessary abstractions, helpers, or wrappers.
- Reuse existing functions instead of creating new ones. Check what already exists in `soap_functions.php` before adding anything.
- Do not create helper/utility functions when the same thing can be easy done inline with existing code.
- CLI scripts should follow the same patterns as existing ones (see `sites_web_domain_get.php` as reference).
- Remove unused code immediately. Do not leave dead functions.

## Editing workflow

- **One file, one edit.** Apply all changes to a given file in a single edit
  (rewrite the whole file if needed) instead of many small line-by-line edits —
  the user should confirm one change per file, not dozens.
- Exception: if a change is genuinely complex, it may be split into a few
  passes (still keep the number of edits small and deliberate).

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

## Output contract (stdout = NDJSON)

Every command prints an NDJSON event stream: one JSON object per line, each with
a `type` ("progress", "notice", "result"). The last line is always the terminal
`result`. No human-decorated text on stdout — presentation is the consumer's job.
See the "Output Format" section in `README.md` for the full contract.

- Emit the terminal result with `emitResult($result)` (never `echo $result`).
- Emit a hard failure (bad args, caught exception) with `failResult('message')`,
  which prints `{"type":"result","success":false,"error":…}` and `exit(1)`
  (never `die('...')`).
- Long-running (job-queue) waits emit `progress`/`notice` via `emitEvent()`;
  keep the human rendering (bars, ✓/✗) out of the library — the consumer draws it.

## Testing & Docs

Before finishing any change:

- Run the test suite and make sure it passes: `./tests/run.sh`
- When adding testable (pure) logic, add a case to `tests/unit_functions.php`.
- After changing anything in `soap_functions.php`, regenerate the function reference:
  `./functions_help.php --markdown > README-FUNCTIONS.md`
