# Code Audit Report

Date: 2026-02-20  
Project: `an-another-php-image-uploader`

## Scope

- `src/upload.php`
- `src/index.htm`
- `README.md`

## Executive Summary

The project is a minimal PHP image uploader with basic MIME and size checks. The implementation works for simple personal usage, but it has several security weaknesses that make it unsafe for internet exposure in its current state.

Overall risk posture: **High** for public deployment.

## Findings

### 1) Hardcoded default password and weak authentication flow (High)

**Where:** `src/upload.php` line 3, plus `README.md` line 12.

The upload gate relies on a hardcoded password (`123456`) checked directly in request data. This creates multiple risks:

- Weak/guessable default secret.
- Secret stored in source code and potentially committed/shared.
- Comparison is not constant-time (`!=`), which is not ideal for secrets.
- No rate limiting / lockout / CAPTCHA.

**Impact:** Unauthorized users can brute-force or discover the password and upload arbitrary content.

**Recommendation:**

- Move secret to environment/config, never hardcode defaults.
- Use `hash_equals()` with a strong stored hash.
- Add rate limiting per IP and request throttling.
- Consider replacing password gate with real authentication.

---

### 2) Uploaded files are stored inside web root and become directly executable/accessible (High)

**Where:** `src/upload.php` lines 39-47 and output URL lines 64-66.

Uploads are written under a year-based folder in the same directory as the PHP scripts and then served directly by URL. If server MIME/handler configuration is permissive or misconfigured, this can expose the application to content abuse and, in worst cases, code execution via crafted files.

**Impact:** Potential for malicious file hosting, abuse, and elevated risk under misconfigured web servers.

**Recommendation:**

- Store files outside the web root.
- Serve via controlled download endpoint with strict content headers.
- Harden web server rules to disable script execution in upload folders.

---

### 3) Use of `copy()` instead of `move_uploaded_file()` (Medium)

**Where:** `src/upload.php` lines 49-52.

PHP provides `move_uploaded_file()` specifically for uploaded files and performs additional checks that `copy()` does not.

**Impact:** Reduced assurance that the source is a valid uploaded file and weaker defense-in-depth.

**Recommendation:** Replace `copy()` + `unlink()` with `move_uploaded_file()`.

---

### 4) Overly permissive directory permissions recommendation (High)

**Where:** `README.md` line 10, and `src/upload.php` line 40 (`mkdir(..., 0777, ...)`).

The project recommends and creates world-writable directories (`777`). This is generally unsafe in multi-user/shared environments and increases tampering risk.

**Impact:** Other processes/users on the host may alter uploaded files or folder structure.

**Recommendation:**

- Use least privilege permissions (e.g., `0750`/`0755` depending on setup).
- Ensure ownership is properly set for the web server user.

---

### 5) URL construction uses unsanitized server variables in HTML output (Medium)

**Where:** `src/upload.php` lines 64-66.

The response embeds values derived from `$_SERVER` (`SERVER_NAME`, `PHP_SELF`) directly into HTML attributes without escaping.

**Impact:** In some deployments/proxy setups, this can enable reflected XSS or HTML injection.

**Recommendation:**

- Build URLs from trusted config (`APP_BASE_URL`) where possible.
- Escape output with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

---

### 6) Missing CSRF protection on upload endpoint (Medium)

**Where:** `src/index.htm` lines 4-8 and `src/upload.php` entire POST handling.

The form and endpoint do not include CSRF tokens.

**Impact:** If a user is authenticated (or password auto-filled), malicious pages could trigger unintended uploads.

**Recommendation:**

- Add CSRF token generation and verification.
- Enforce same-site cookie and origin/referrer checks when applicable.

---

### 7) Denial-of-service exposure from unbounded upload attempts (Medium)

**Where:** `src/upload.php` authentication and upload flow.

While single-file size is limited to 3 MB, there is no request-level throttling, no per-IP quotas, and no cleanup/retention policy.

**Impact:** Storage exhaustion and service degradation via repeated uploads.

**Recommendation:**

- Add rate limiting and quota controls.
- Add lifecycle management (retention, cleanup jobs).
- Monitor failed/successful upload patterns.

## Positive Observations

- Validates that a file was supplied before processing.
- Applies file size limit.
- Uses MIME detection via `finfo_file` and image verification via `exif_imagetype`.
- Restricts to a small allowlist of image MIME types.

## Priority Remediation Plan

1. Replace hardcoded password with environment-based secret + stronger auth flow.
2. Move upload storage outside web root; enforce strict serving behavior.
3. Switch to `move_uploaded_file()` and tighten directory permissions.
4. Escape all HTML output and avoid direct trust in `$_SERVER` values.
5. Add CSRF protection and operational controls (rate limits, quotas, retention).

## Suggested Verification Commands

- `php -l src/upload.php`
- `php -r "echo function_exists('finfo_open') ? 'finfo ok' : 'finfo missing';"`
- Manual upload tests for valid/invalid MIME, oversized files, and malicious payloads.
