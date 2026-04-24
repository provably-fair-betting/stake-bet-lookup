# Contributing

## Local setup

```bash
make setup      # Create app/, generate APP_KEY and admin token, install Node deps
make up         # Start Docker services
make migrate    # Run migrations
make capture    # Capture Cloudflare clearance and sync into the running app
```

See [docs/local-development.md](docs/local-development.md) for the full command reference.

## Running tests

```bash
make test       # Run the full PHPUnit suite
make coverage   # Run with HTML coverage report (requires PCOV or Xdebug)
```

## Commit format

This project uses [Conventional Commits](https://www.conventionalcommits.org/). Every commit message must follow this format:

```
<type>: <short description>

[optional body]

[optional footer — BREAKING CHANGE: <description>]
```

### Types

| Type | When to use | Version bump |
|------|-------------|--------------|
| `feat` | New feature or behaviour visible to consumers | Minor |
| `fix` | Bug fix | Patch |
| `chore` | Maintenance, dependency updates, tooling | None |
| `docs` | Documentation only | None |
| `refactor` | Internal restructure, no behaviour change | None |
| `test` | Adding or updating tests | None |
| `perf` | Performance improvement | Patch |

A `!` after the type (e.g. `feat!:`) or a `BREAKING CHANGE:` footer triggers a **major** version bump.

### Examples

```
feat: add probeWith method to StakeApiService
fix: return 503 when clearance credentials not configured
chore: update guzzlehttp/guzzle to 7.9
docs: document day-to-day commit flow
refactor: extract StakeHttpClientFactory from StakeApiService
feat!: remove config-based credential fallback

BREAKING CHANGE: clearance credentials must now be set via the admin
API or Artisan command. STAKE_CLEARANCE_COOKIE env var is no longer read.
```

## Release process

Releases are fully automated via [release-please](https://github.com/googleapis/release-please-action).

1. Merge your feature/fix branch into `main` using conventional commits
2. release-please opens a **Release PR** automatically, bumping `composer.json` and updating `CHANGELOG.md`
3. Review the Release PR — the version bump and changelog are derived from commits since the last release
4. **Merge the Release PR** — release-please tags the commit (e.g. `v1.2.0`) and creates the GitHub Release

You never manually edit `CHANGELOG.md` or bump the version — release-please owns both.
