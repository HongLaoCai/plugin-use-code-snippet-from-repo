# Repo Code Snippets

WordPress plugin to manage **PHP / CSS / JS / HTML** snippets as files on disk, with activate/deactivate, crash auto-disable, run-location control, and **one-way Git sync** (Diff + Pull only).

Inspired by FluentSnippets (file storage + safety) and WPCode (scopes / safe mode).

## Features

- Snippets saved under `wp-content/repo-code-snippets/snippets/{slug}/`
- Types: PHP, CSS, JavaScript, HTML
- Active / Inactive toggle — only active snippets run
- Scope: Everywhere · Frontend only · Admin only
- If PHP fatals while a snippet runs, that snippet is auto-deactivated and the error is stored
- Safe Mode URL + `REPO_CODE_SNIPPETS_SAFE_MODE` constant
- Settings: Git URL, folder path, branch, optional token → **Diff** and **Pull** (no Push)

## Install

1. Copy this folder into `wp-content/plugins/repo-code-snippets/` (or symlink / clone there)
2. Activate **Repo Code Snippets** in Plugins
3. Open **Repo Snippets** in the admin menu

## Snippet layout (WordPress & Git)

Each snippet is a directory:

```text
snippets/
  my-snippet/
    meta.json
    code.php          # or code.css / code.js / code.html
```

Example `meta.json`:

```json
{
  "name": "My Snippet",
  "slug": "my-snippet",
  "type": "php",
  "status": "active",
  "scope": "everywhere",
  "priority": 10,
  "description": "Optional note"
}
```

See `examples/snippets/` in this repository for a ready-made Git folder.

## Git sync workflow

1. Put snippets in a Git repo under a folder (e.g. `snippets/`)
2. In **Repo Snippets → Settings**, set:
   - Git repository URL
   - Folder in repo
   - Branch (default `main`)
   - Access token if the repo is private
3. **Diff** — compare WordPress vs Git (detects CMS-only edits before overwrite)
4. **Pull** — replace all local snippets with the Git folder

Fetch uses `git` CLI when available; otherwise ZIP download for GitHub / GitLab HTTPS URLs.

## Safety

- Auto-disable on fatal error (can be turned off in Settings)
- Safe Mode URL (shown in Settings) — stops all snippets for 24h via cookie
- Or in `wp-config.php`:

```php
define( 'REPO_CODE_SNIPPETS_SAFE_MODE', true );
```

## Requirements

- WordPress 5.8+
- PHP 7.4+
- Optional: `git` on the server; or ZipArchive + GitHub/GitLab for ZIP fallback
