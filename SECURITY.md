# Security Policy

## Supported Versions

The following versions of this project are currently being supported with security updates.

| Version | Supported          |
| ------- | ------------------ |
| 5.1.2   | :white_check_mark: |
| <5.1.1  | :x:                |

## Reporting a Vulnerability

You can report any security bugs found in the source code of ElasticPress through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/elasticpress). The Patchstack team will assist you with verification, CVE assignment and take care of notifying the developers of this plugin.

## Responding to Vulnerability Reports

10up takes security bugs seriously.  We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

Your email will be acknowledged within ONE business day, and you will receive a more detailed response to your email within SEVEN days indicating the next steps in handling your report.  After the initial reply to your report, 10up will keep you informed of the progress being made towards a fix and announcement.  If your vulnerability report is accepted, then we will work with you on a fix and follow the process noted below on [disclosing vulnerabilities](#disclosing-a-vulnerability).  If your vulnerability report is declined, then we will provide you with a reasoning as to why we came to that conclusion.

## Disclosing a Vulnerability

Once an issue is reported, 10up uses the following disclosure process:

- When a report is received, we confirm the issue and determine its severity.
- If we know of specific third-party services or software that require mitigation before publication, those projects will be notified.
- An advisory is prepared (but not published) which details the problem and steps for mitigation.
- Wherever possible, fixes are prepared for the last minor release of the two latest major releases, as well as the trunk branch.  We will attempt to commit these fixes as soon as possible, and as close together as possible.
- Patch releases are published for all fixed released versions and the advisory is published.
- Release notes and our CHANGELOG.md will include a `Security` section with a link to the advisory.

We credit reporters for identifying vulnerabilities, although we will keep your name confidential if you request it.

## Known Vulnerabilities

Past security advisories, if any, are listed below.

| Advisory Number | Type               | Versions affected | Reported by           | Additional Information      |
|-----------------|--------------------|:-----------------:|-----------------------|-----------------------------|
| CVE-2024-35684 | CSRF | n/a - 5.1.1 | Patchstack Team | [CVE link](https://www.cve.org/CVERecord?id=CVE-2024-35684) |
| EP-2021-02-11 | CSRF Nonce Bypass | 3.5.2 - 3.5.3 | WordPress.org Plugin Review Team | [WPScan link](https://wpscan.com/vulnerability/ce655810-bd08-4042-ac3d-63def5c76994) |
