# Security Policy

## Supported versions

| Version | Supported |
| ------- | --------- |
| 0.1.x   | ✅        |

## Reporting a vulnerability

Please **do not** open a public issue for security problems.

Report vulnerabilities privately through GitHub:
**Security → Report a vulnerability** on
<https://github.com/AndrewProd/laravel-ai-blueprint/security/advisories/new>.

Include the affected version, a minimal reproduction and the impact. You will receive an
acknowledgement within a few days. Please give us reasonable time to release a fix before
public disclosure.

Areas of particular interest:

- reading files that match sensitive patterns (`.env`, keys, credentials);
- writing outside the project root (path traversal, symlinks);
- executing project code or shell commands;
- leaking file contents into generated documents, caches or console output.

Never include real secrets or private source code in reports.
