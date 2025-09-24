## API

### Base URL

- All endpoints are routed via `public/index.php`.

### Authentication

- Endpoints marked with Auth: Required require a bearer token header:
    - Header: `Authorization: Bearer <AUTH_TOKEN>`

### Content type

- For POST endpoints, send `Content-Type: application/json` and a valid JSON body.
    - Invalid/malformed JSON will cause a decoding error (JSON exception) and the request will fail.

### Errors

- 200 OK: Successful request (body may be empty).
- 400 Bad Request: Validation failed. Response body starts with `Invalid request:` and includes per-field errors.
- 401 Unauthorized: Missing/invalid bearer token on protected endpoints.
- 404 Not Found: No route matches the requested path.
- 405 Method Not Allowed: Path exists but HTTP method is not allowed; response lists allowed methods.
- 500 Internal Server Error: Uncaught errors (e.g., download failure, malformed JSON, or other exceptions).

---

### POST /api/php

- Auth: Required
- Purpose: Upload PHP build artifact from a URL.
- Request body (JSON):
    - `url` (string, required, URL): Direct download URL of the artifact.
    - `token` (string, required): Used as a GitHub token if the URL points to `api.github.com`.
- Success: `200 OK`.
- Errors:
    - `400` with validation details if `url` or `token` are missing/invalid.
    - `500` with `Error: ...` if the file could not be fetched or is not a ZIP.

Example

```bash
curl -i -X POST \
    -H "Authorization: Bearer $AUTH_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
            "url": "https://api.github.com/repos/org/repo/actions/artifacts/123/zip",
            "token": "ghp_..."
        }' \
    https://downloads.php.net/api/php
```

---

### POST /api/pecl

- Auth: Required
- Purpose: Upload a PECL extension artifact.
- Request body (JSON):
    - `url` (string, required, URL): Direct download URL of the artifact.
    - `extension` (string, required): Extension name (used in filename).
    - `ref` (string, required): Reference (e.g., tag/commit) used in filename.
    - `token` (string, optional): Used as a GitHub token if the URL points to `api.github.com`.
- Success: `200 OK`.
- Errors:
    - `400` with validation details if required fields are missing/invalid.
    - `500` with `Error: ...` if the file could not be fetched or is not a ZIP.

Example

```bash
curl -i -X POST \
    -H "Authorization: Bearer $AUTH_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
            "url": "https://api.github.com/repos/org/repo/actions/artifacts/456/zip",
            "extension": "xdebug",
            "ref": "3.3.2",
            "token": "ghp_..."
        }' \
    https://downloads.php.net/api/pecl
```

---

### POST /api/winlibs

- Auth: Required
- Purpose: Record metadata and fetch all artifacts for a specific GitHub Actions workflow run of [`winlibs/winlib-builder`](https://github.com/winlibs/winlib-builder).
- Request body (JSON):
    - `library` (string, required)
    - `ref` (string, required)
    - `workflow_run_id` (string, required)
    - `php_versions` (string, required): Comma-separated list matching `^(?:\d+\.\d+|master)(?:,\s*(?:\d+\.\d+|master))*$`.
    - `vs_version_targets` (string, required): Comma-separated list matching `^(v[c|s]\d{2})(,v[c|s]\d{2})*$`.
    - `stability` (string, required): `stable`, `staging`, or both (comma-separated) matching `^(stable|staging)(,(stable|staging))?$`.
    - `token` (string, required): GitHub token used to download artifacts.
- Success: `200 OK`, empty body.
- Errors:
    - `400` with validation details if input is invalid.
    - Other failures are printed as cURL errors; HTTP status may still be 200 if not explicitly set.

Example

```bash
curl -i -X POST \
    -H "Authorization: Bearer $AUTH_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
            "library": "icu",
            "ref": "main",
            "workflow_run_id": "1234567890",
            "php_versions": "8.1,8.2,8.3",
            "vs_version_targets": "vc15,vs16",
            "stability": "stable",
            "token": "ghp_..."
        }' \
    https://downloads.php.net/api/winlibs
```

---

### POST /api/series-init

- Auth: Required
- Purpose: Initialize a series configuration by writing a JSON file into `BUILDS_DIRECTORY/series`.
- Request body (JSON):
    - `series` (string, required)
    - `series_vs` (string, required): Matches `^v[c|s]\d{2}$`.
    - `target_vs` (string, required): Matches `^v[c|s]\d{2}$`.
    - `token` (string, required)
- Success: `200 OK`, empty body.
- Errors: `400` with validation details if input is invalid.

Example

```bash
curl -i -X POST \
    -H "Authorization: Bearer $AUTH_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
            "series": "8.4",
            "series_vs": "vc15",
            "target_vs": "vs16",
            "token": "ghp_..."
        }' \
    https://downloads.php.net/api/series-init
```
