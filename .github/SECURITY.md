# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability within this project, please send an email to **security@bagart.dev**. All security vulnerabilities will be promptly addressed.

Please include the following information in your report:
- Type of issue (e.g., buffer overflow, SQL injection, cross-site scripting, etc.)
- Full paths of source file(s) related to the manifestation of the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

## Response Timeline

- **Acknowledgment**: within 48 hours
- **Initial assessment**: within 1 week
- **Fix or mitigation**: depends on severity, typically within 2 weeks for critical issues

## Scope

The following are in scope:
- The Telegram bot platform application
- All modules under `misc/BAGArt/`
- CI/CD pipeline security
- Docker configurations

The following are out of scope:
- Third-party dependencies (report upstream)
- Social engineering attacks

## Disclosure Policy

- We follow coordinated disclosure
- We will not take legal action against researchers who report vulnerabilities in good faith
- We request reasonable time to address issues before public disclosure

## Security Hardening

This project implements:
- **Secret scanning** via baseline tooling (`.github/secret-scan.php`)
- **Dependency review** via GitHub's `dependency-review.yml` workflow
- **CI least-privilege** — all workflows declare minimal `permissions`
- **Action pinning** — third-party GitHub Actions pinned to full commit SHAs
- **CODEOWNERS** — mandatory review for security-sensitive paths
- **Encrypted secrets** — Telegram bot tokens stored in DB, not in `.env`
- **SSRF protection** — fixed judges, private/metadata denylist, anti-DNS-rebinding
