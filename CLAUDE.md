<!-- CLAUDE.md v1.5 | Last updated: 2026-01-28 -->

# Claude Code Instructions

> **Shared standards:** Read [.ai/shared-development-guide.md](.ai/shared-development-guide.md) for all coding standards, CI requirements, commit conventions, and best practices.
>
> **CiviCRM reference:** See [.ai/civicrm.md](.ai/civicrm.md) for CiviCRM core patterns and [.ai/extension.md](.ai/extension.md) for extension structure and testing.
>
> **Code review:** See [.ai/ai-code-review.md](.ai/ai-code-review.md) for review checklist and process.

This file contains **Claude Code-specific** instructions only.

---

## Claude Code Workflow

### Plan Mode and Execution Mode

1. **Explain** -- Ask Claude to describe the issue in its own words
2. **Plan** -- Use Plan Mode (`Shift + Tab` twice) for complex tasks
3. **Review** -- Verify and edit the plan before implementation
4. **Implement** -- Disable Plan Mode and apply changes
5. **Verify** -- Run tests and linting

### Request Confirmation Before
- Deleting or overwriting files
- Database migrations
- Modifying auto-generated files (see shared guide, Section 10)


---

## Environment Constraints

- Cannot run tests or PHPStan directly without Docker environment
- Can write test files following existing patterns
- Can fix errors based on CI output
- Suggest: "Push changes to trigger CI" or "Run tests via Docker"

---

## Claude-Specific Rules

- **DO NOT add `Co-Authored-By: Claude` or any AI attribution** to commits
- Never push commits automatically without human review
- When proposing commits, use the `COMCL-###:` format from the shared guide
- Always read files before editing them

---

## Pre-Push Self-Review

Before proposing a commit, Claude can review its own changes in the same session:

```bash
# Replace <base-branch> with your PR target (master, main, develop, etc.)
git diff <base-branch>...HEAD
```

For unbiased review, use a **separate Claude session** and follow [.ai/ai-code-review.md](.ai/ai-code-review.md).

---

## Developer Prompts (Examples)

| Task | Prompt |
|------|--------|
| Generate tests | "Create PHPUnit tests for `PaymentHandler::process()` covering success and error cases." |
| Summarize PR | "Summarize commits into PR description using template for COMCL-123." |
| Fix linting | "Fix PHPCS violations in `ContributionService.php`." |
| Fix PHPStan | "PHPStan reports type mismatch in `Contribution.php:132`. Suggest a safe fix." |
| Review changes | "Review my changes against our coding standards: `git diff master...HEAD`" |
