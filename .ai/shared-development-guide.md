<!-- docs/shared-development-guide.md v1.5 | Last updated: 2026-01-28 -->
<!-- Shared development standards for all AI tools and developers. -->
<!-- Tool-specific instructions: CLAUDE.md, AGENTS.md, GEMINI.md -->
<!-- Framework-specific: .ai/civicrm.md -->

# Development Standards

---

## 1. Quick Reference

| Resource | Location |
|----------|----------|
| Project Overview | [README.md](../README.md) |
| PR Template | `.github/PULL_REQUEST_TEMPLATE.md` |
| Linting Rules | `phpcs-ruleset.xml` |
| PHPStan Config | `phpstan.neon` |
| CiviCRM Patterns | [.ai/civicrm.md](civicrm.md) |
| Extension Development | [.ai/extension.md](extension.md) |
| AI Code Review | [.ai/ai-code-review.md](ai-code-review.md) |

---

## 2. Development Environment

### Running Tests, Linter, PHPStan

```bash
# Setup Docker environment (one-time)
./scripts/run.sh setup

# Run all tests
./scripts/run.sh tests

# Run specific test
./scripts/run.sh test tests/phpunit/path/to/Test.php

# Run linter on changed files
./scripts/lint.sh check

# Auto-fix linting issues
./scripts/lint.sh fix

# Run PHPStan on changed files
./scripts/run.sh phpstan-changed

# Run PHPStan on full codebase
./scripts/run.sh phpstan

# Regenerate DAO files after schema changes
./scripts/run.sh civix
```

---

## 3. Pull Request Guidelines

### PR Title Format
```
COMCL-###: Short description
```

### Required Sections (from template)
- **Overview**: Non-technical description
- **Before**: Current state with screenshots/gifs where appropriate
- **After**: What changed with screenshots/gifs where appropriate
- **Technical Details**: Noteworthy technical changes, code snippets
- **Core overrides**: If any CiviCRM core files are patched
- **Comments**: Any additional notes for reviewers

### When Drafting PRs
- Reference the ticket ID in the PR title
- Fill all required template sections
- Keep summaries factual — avoid assumptions
- Include before/after screenshots for UI changes

---

## 4. Handling Pull Request Review Feedback

**NEVER blindly implement feedback.** Always think critically and ask questions.

### Required Process

1. **Analyze Each Suggestion:**
   - Does this suggestion make technical sense?
   - What are the implications (database constraints, type safety, performance)?
   - Could this break existing functionality?
   - Is this consistent with the project's architecture?

2. **Ask Clarifying Questions:**
   - If unsure about the reasoning, ask: "Why is this change recommended?"
   - If there are trade-offs, present them: "This suggestion would fix X but might break Y - which is preferred?"
   - If the suggestion seems incorrect, explain why: "I think this might cause issues because..."

3. **Explain Your Analysis:**
   - For each change, explain WHY you're making it (or not making it)
   - Present technical reasoning
   - Highlight potential issues

4. **Get Approval Before Implementing:**
   - Show what you plan to change
   - Wait for explicit confirmation before committing
   - Never batch commit multiple review changes without review

### Red Flags - Stop and Ask Questions
- Changes that affect database constraints (NOT NULL, foreign keys)
- Changes to type checking logic (null checks, empty checks, isset)
- Suggestions that seem to contradict architectural decisions
- "Consistency" arguments without technical justification
- Changes that would alter existing behavior
- Automated tool suggestions without context

---

## 5. Commit Message Convention

```
COMCL-###: Short imperative description
```

**Rules:**
- Keep under 72 characters
- Use present tense ("Add", "Fix", "Refactor")
- Include the correct issue key when committing
- Be specific and descriptive
- **DO NOT add any AI attribution or co-authorship lines**

**Examples:**
```
COMCL-456: Add null check for membership expiry date
COMCL-789: Fix fee calculation for recurring contributions
COMCL-101: Refactor PaymentService to use dependency injection
```

---

## 6. Code Quality Standards

### Unit Testing
- **Mandatory** for all new features and bug fixes
- Never modify or skip tests just to make them pass. Fix the underlying code.
- Test positive, negative, and edge cases
- See framework-specific docs for test base classes and patterns

### Linting (PHPCS)
- Follow project coding standards (`phpcs-ruleset.xml`)
- All files must end with a newline
- Run `./scripts/lint.sh fix` to auto-fix issues

### Static Analysis (PHPStan)
- Never regenerate baseline to "fix" errors - fix the code
- Goal is to **reduce** the baseline over time, not grow it
- Use `@phpstan-param` for generic types that linter doesn't support

### PHPStan Baseline Management

- The baseline (`phpstan-baseline.neon`) tracks pre-existing errors
- **Never add new entries** to work around errors — fix the code or add stubs
- Regenerate baseline after fixing code:
  ```bash
  ./scripts/run.sh shell
  cd /path/to/extension
  php /tmp/phpstan.phar analyse -c phpstan.neon --generate-baseline=phpstan-baseline.neon
  ```
- `reportUnmatchedIgnoredErrors: false` is set because local and CI scan different paths

### PHPStan CI vs Local Configuration

- **Local**: `phpstan.neon` with Docker paths
- **CI**: `phpstan-ci.neon` generated in CI workflow with CI paths
- Both must stay in sync for `level`, `stubFiles`, `excludePaths`, `reportUnmatchedIgnoredErrors`, and `includes`
- When changing `phpstan.neon`, always check if the CI workflow needs the same change

### Linter + PHPStan Compatibility Pattern
```php
/**
 * @param array $params           // Linter sees this
 * @phpstan-param array<string, mixed> $params  // PHPStan sees this
 */
```

---

## 7. Critical Coding Guidelines

### Security
- Never log or expose API keys, tokens, or sensitive data
- Validate all input amounts and parameters before processing
- Use parameterized queries (prevent SQL injection)
- Sanitize all user input before rendering (XSS prevention)
- Verify webhook signatures
- Ensure proper authentication/authorization for API endpoints

### Performance
- Identify N+1 query issues
- Detect inefficient loops or algorithms
- Avoid unnecessary API calls (use cached records)
- Recommend caching for expensive operations
- Review database queries for optimization

### Code Quality
- Services should follow single responsibility principle
- Use meaningful, descriptive names
- Handle exceptions properly (use custom exception classes)
- All service methods should have proper return type declarations
- Use dependency injection for service dependencies
- **Use proper types instead of `mixed`** in PHPDoc annotations
- **Never use `assert()` in production code** - use proper type checks with exceptions

---

## 8. CI Requirements

All code must pass before merging:

| Check | Command | CI Workflow |
|-------|---------|-------------|
| Tests | `./scripts/run.sh tests` | `unit-test.yml` |
| Linting | `./scripts/lint.sh check` | `linters.yml` |
| PHPStan | `./scripts/run.sh phpstan-changed` | `phpstan.yml` |

---

## 9. Git Discipline

**NEVER use `git add -A` or `git add .`** - always add specific files:
```bash
# BAD
git add -A
git add .

# GOOD
git add Civi/Service/MyService.php tests/phpunit/Civi/Service/MyServiceTest.php
```

**Always run linter before committing:**
```bash
./scripts/lint.sh check
git add <specific-files>
git commit -m "COMCL-###: Description"
```

**Always run tests before committing code changes:**
- When modifying source files, run `./scripts/run.sh tests` BEFORE committing
- When modifying error messages, verify affected tests expect the new message

---

## 10. Safety Rules

- Never commit code without running **tests** and **linting**
- Never remove or weaken tests to make them pass
- Never push commits automatically without human review
- If unsure, stop and consult a senior developer

### Sensitive Files (Never Commit)
- `civicrm.settings.php` (contains API keys)
- `.env` files
- Any files with credentials or secrets

### Auto-Generated Files (Do Not Edit Manually)
- `*.civix.php` (regenerate with `civix`)
- `CRM/*/DAO/*.php` (regenerate from XML schemas)
- Files in `xml/schema/*.entityType.php` (auto-generated)

---

## 11. Pre-Merge Validation Checklist

| Check | Requirement |
|-------|-------------|
| Tests pass | All green in CI |
| Linting passes | No violations |
| PHPStan passes | Static analysis clean on changed files |
| Commit prefix | Uses COMCL-### format |
| PR template used | `.github/PULL_REQUEST_TEMPLATE.md` completed |
| No sensitive data | No API keys or credentials in code |
| Code reviewed | At least one approval from team member |

---

## 12. Common Commands

```bash
# Git
git status && git diff

# Testing
./scripts/run.sh setup    # One-time Docker setup
./scripts/run.sh tests    # Run all tests
./scripts/run.sh test path/to/Test.php  # Run specific test

# Code Quality
./scripts/lint.sh check   # Check linting
./scripts/lint.sh fix     # Auto-fix linting
./scripts/run.sh phpstan-changed  # PHPStan on changed files

# CiviCRM
./scripts/run.sh civix    # Regenerate DAO files
./scripts/run.sh shell    # Shell into container
```
