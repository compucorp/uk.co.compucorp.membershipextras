# Code Review Style Guide

This style guide defines how Gemini Code Assist should review Pull Requests.

## Standards

Follow all standards in [.ai/shared-development-guide.md](../.ai/shared-development-guide.md).

For CiviCRM extensions, also follow [.ai/civicrm.md](../.ai/civicrm.md) and [.ai/extension.md](../.ai/extension.md).

For the full review checklist and severity definitions, see [.ai/ai-code-review.md](../.ai/ai-code-review.md).

## Review Focus

### Must Check (BLOCKER if violated)
- No hardcoded secrets, API keys, or credentials in code
- No SQL injection (use parameterized queries)
- No XSS (sanitize user input before rendering)
- Webhook signatures verified
- No auto-generated files edited manually (`*.civix.php`, `CRM/*/DAO/*.php`)
- No sensitive files committed (`civicrm.settings.php`, `.env`)
- Tests not removed or weakened to pass

### Should Check (WARNING)
- Commit messages follow `COMCL-###:` format
- New features and bug fixes include unit tests
- PHPStan level 9 compliance (proper types, no `mixed` where avoidable)
- `is_array()` guard on API4 `->first()` results
- `@phpstan-param` / `@phpstan-var` used where linter and PHPStan conflict
- No `assert()` in production code
- No N+1 query issues
- Services follow single responsibility principle
- Dependency injection used for service dependencies
- No AI attribution in commits

### Nice to Have (SUGGESTION)
- Code readability improvements
- Better variable naming
- Additional edge case test coverage
- Documentation improvements

## Severity Labels

Use these in review comments:
- **BLOCKER**: Must fix before merge (security, data loss, broken functionality)
- **WARNING**: Should fix (quality, performance, maintainability)
- **SUGGESTION**: Optional improvement
- **QUESTION**: Needs clarification from the author

## Review Style

- Be specific -- reference file paths and line numbers
- Explain **why** something is an issue, not just what to change
- Think critically -- don't suggest changes that contradict architectural decisions
- Consider implications of type changes, database constraints, performance trade-offs
- Distinguish severity clearly -- not everything is a blocker
