<!-- AGENTS.md v1.5 | Last updated: 2026-01-28 -->

# Codex Development Guide

> **Shared standards:** Read [.ai/shared-development-guide.md](.ai/shared-development-guide.md) for all coding standards, CI requirements, commit conventions, and best practices.
>
> **CiviCRM reference:** See [.ai/civicrm.md](.ai/civicrm.md) for CiviCRM core patterns and [.ai/extension.md](.ai/extension.md) for extension structure and testing.
>
> **Code review:** See [.ai/ai-code-review.md](.ai/ai-code-review.md) for review checklist and process.

This file contains **Codex-specific** instructions only.

---

## Codex Environment Constraints

Codex runs in a **sandboxed environment** with limited capabilities:

- No network access during execution
- Cannot run Docker commands (`./scripts/run.sh` will not work)
- Cannot access CI workflows directly
- Can read all files in the repository
- Can write and modify files

---

## Codex-Specific Rules

- **DO NOT add any AI attribution** to commits
- Follow the `COMCL-###:` commit format from the shared guide
- Since you cannot run tests, ensure code is correct by careful analysis
- Write tests alongside code changes
- Always flag verification steps the developer must run

---

## Codex Workflow

1. **Plan** -- Outline the approach before making changes
2. **Edit** -- Apply changes following all shared standards
3. **Flag** -- Note what the developer must verify

### Always Remind the Developer To
```bash
./scripts/run.sh tests           # Run tests before committing
./scripts/lint.sh check          # Check linting before committing
./scripts/run.sh phpstan-changed # Check static analysis
```

---

## Codex for Code Review

Codex can review code changes using the process in [.ai/ai-code-review.md](.ai/ai-code-review.md):

- Feed `git diff master...HEAD` output for pre-push review
- Review PRs when integrated with GitHub
- Apply the same checklist and severity levels as any other tool
- **Never auto-approve** -- provide actionable feedback
- Think critically about suggestions (shared guide, Section 4)
