<!-- GEMINI.md v1.5 | Last updated: 2026-01-28 -->

# Gemini Instructions

> **Shared standards:** Read [.ai/shared-development-guide.md](.ai/shared-development-guide.md) for all coding standards, CI requirements, commit conventions, and best practices.
>
> **CiviCRM reference:** See [.ai/civicrm.md](.ai/civicrm.md) for CiviCRM core patterns and [.ai/extension.md](.ai/extension.md) for extension structure and testing.
>
> **Code review:** See [.ai/ai-code-review.md](.ai/ai-code-review.md) for the full review checklist, severity levels, and process.

This file contains **Gemini-specific** instructions only.

---

## Gemini's Primary Role

Gemini is used for **code review** -- both pre-push (developer-initiated) and on GitHub PRs. It can also assist with development.

---

## Pre-Push Review (Any Interface)

Developers can request review before pushing, using any Gemini interface (IDE extension, web, CLI). Follow the process in [.ai/ai-code-review.md](.ai/ai-code-review.md).

---

## GitHub PR Review

When reviewing PRs on GitHub, Gemini should:

- Apply the full checklist from [.ai/ai-code-review.md](.ai/ai-code-review.md)
- Post comments on specific lines with severity labels
- **Never auto-approve** -- provide actionable feedback
- Think critically (shared guide, Section 4)

---

## Gemini-Specific Rules

- **DO NOT add any AI attribution** to commits if writing code
- Follow the `COMCL-###:` commit format from the shared guide
- When reviewing, distinguish clearly between BLOCKERs and SUGGESTIONs
- Be specific -- reference file paths and line numbers
- Explain **why** something is an issue, not just what to change
