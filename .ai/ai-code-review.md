<!-- docs/ai-code-review.md v1.5 | Last updated: 2026-01-28 -->

# AI Code Review Guide

This document defines how **any AI tool** (Claude, Gemini, Codex, or others) should review code changes. The review process is tool-agnostic -- the diff is the universal input, the standards are shared.

> **Standards reference:** [shared-development-guide.md](shared-development-guide.md)

---

## 1. Review Modes

### Pre-Push Review (Developer-Initiated)

Review changes **before** pushing to remote. Works with any AI tool or interface (IDE, CLI, web chat).

**Step 1: Generate the diff**

Determine your base branch first (e.g., `master`, `main`, `develop`). Use the base branch your PR will target.

```bash
# All committed changes on current branch vs base branch
git diff <base-branch>...HEAD

# Only staged changes (not yet committed)
git diff --cached

# Only unstaged changes
git diff

# Specific files
git diff <base-branch>...HEAD -- path/to/file.php

# Example with master as base
git diff master...HEAD
```

> **Tip:** For a complete review, check both committed changes (`git diff <base-branch>...HEAD`) and any uncommitted work (`git diff`, `git diff --cached`).

**Step 2: Request the review**

Use this prompt template (adapt wording for your tool):

```
Review the following code changes against our project's coding standards
in SHARED_DEVELOPMENT_GUIDE.md.

Check for:
- Security issues (SQL injection, XSS, hardcoded secrets)
- Performance problems (N+1 queries, inefficient loops)
- Code quality (SRP, naming, error handling, type safety)
- Test coverage (are new features/fixes tested?)
- PHPStan compliance (proper types, no mixed where avoidable)
- Linting compliance (CiviCRM Drupal standards)

For each issue, provide:
- Severity: BLOCKER / WARNING / SUGGESTION / QUESTION
- File and line reference
- What the issue is and why it matters
- Suggested fix

<paste diff here>
```

**Step 3: Act on feedback**

- Fix BLOCKERs before pushing
- Address WARNINGs where practical
- Consider SUGGESTIONs for follow-up
- Answer QUESTIONs with code comments or commit messages

### GitHub PR Review (Automated)

Review changes **after** pushing, on the Pull Request. The AI tool reads the PR diff automatically (base branch is known from the PR target).

Same checklist applies. The AI tool should post comments on specific lines.

---

## 2. Review Checklist

### Standards Compliance
- [ ] Commit messages follow `COMCL-###:` format
- [ ] PR uses `.github/PULL_REQUEST_TEMPLATE.md`
- [ ] All required sections filled (Overview, Before, After, Technical Details)
- [ ] No AI attribution in commits

### Security
- [ ] No hardcoded secrets, API keys, or credentials
- [ ] Parameterized queries (no SQL injection)
- [ ] User input sanitized before rendering (no XSS)
- [ ] Webhook signatures verified
- [ ] Authentication/authorization on API endpoints
- [ ] Sensitive files not committed (`civicrm.settings.php`, `.env`)

### Performance
- [ ] No N+1 query issues
- [ ] No inefficient loops over large datasets
- [ ] Unnecessary API calls avoided
- [ ] Database queries optimized

### Code Quality
- [ ] Single responsibility principle followed
- [ ] Meaningful names following project conventions
- [ ] Proper exception handling
- [ ] Return type declarations on service methods
- [ ] Dependency injection used
- [ ] Proper types in PHPDoc (no `mixed` where avoidable)
- [ ] No `assert()` in production code

### Testing
- [ ] New features and bug fixes include tests
- [ ] Tests cover positive, negative, and edge cases
- [ ] No tests removed or weakened
- [ ] Error message changes reflected in test assertions

### Static Analysis & Linting
- [ ] Code passes PHPStan level 9
- [ ] Coding standards followed
- [ ] Files end with newlines
- [ ] `@phpstan-param` / `@phpstan-var` used where linter and PHPStan conflict

---

## 3. Review Severity Levels

| Level | Meaning | Action Required |
|-------|---------|-----------------|
| **BLOCKER** | Security vulnerability, data loss risk, broken functionality | Must fix before merge |
| **WARNING** | Affects quality, performance, or maintainability | Should fix before merge |
| **SUGGESTION** | Optional improvement | Nice to have, can be follow-up |
| **QUESTION** | Needs clarification from the author | Author must respond |

---

## 4. Review Feedback Principles

- **Be specific** -- reference file paths and line numbers
- **Explain why** -- not just what to change, but why it matters
- **Think critically** -- don't suggest changes that contradict architectural decisions
- **Consider implications** -- type changes, database constraints, performance trade-offs
- **Distinguish severity** -- not everything is a blocker
- **Be constructive** -- suggest fixes, not just problems

---

## 5. Cross-Tool Review Workflow

Any combination works:

| Developer Tool | Reviewer Tool | How |
|---------------|---------------|-----|
| Claude | Gemini | Push branch, Gemini reviews PR or diff |
| Gemini | Claude | Feed diff to Claude in new session |
| Human | Any AI | Feed diff or create PR |
| Codex | Claude/Gemini | Push branch, other tool reviews |
| Any tool | Same tool (new session) | Feed diff to fresh session for unbiased review |

**Best practice:** Use a **different tool or session** for review than was used for development. Fresh context catches more issues.
