Review my current changes against the project coding standards.

The user may provide a base branch name as an argument. If not provided, ask which base branch to diff against before proceeding.

1. Run `git diff <base-branch>...HEAD` to see all committed changes on this branch
2. Also run `git diff` and `git diff --cached` to catch any uncommitted/staged changes
3. Apply the review checklist from .ai/ai-code-review.md
4. Flag issues by severity: BLOCKER, WARNING, SUGGESTION, QUESTION
5. Reference specific file paths and line numbers

$ARGUMENTS
