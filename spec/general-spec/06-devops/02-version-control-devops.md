# Version Control Conventions

> Version: 1.0.0 | Last Updated: 2026-01-26

## Overview

This specification defines Git workflow, branching strategies, commit conventions, and collaboration practices.

---

## 1. Branching Strategy

### 1.1 Branch Types

| Branch Type | Pattern | Purpose | Lifetime |
|-------------|---------|---------|----------|
| Main | `main` | Production-ready code | Permanent |
| Development | `develop` | Integration branch | Permanent |
| Feature | `feature/<ticket>-<description>` | New features | Until merged |
| Bugfix | `bugfix/<ticket>-<description>` | Bug fixes | Until merged |
| Hotfix | `hotfix/<ticket>-<description>` | Production fixes | Until merged |
| Release | `release/<version>` | Release preparation | Until merged |

### 1.2 Branch Naming

```bash
# Feature branches
feature/EQM-123-user-authentication
feature/EQM-456-export-csv-reports

# Bugfix branches
bugfix/EQM-789-login-timeout
bugfix/EQM-012-null-pointer-exception

# Hotfix branches (from main)
hotfix/EQM-345-security-patch
hotfix/EQM-678-critical-data-loss

# Release branches
release/2.1.0
release/3.0.0-beta.1
```

### 1.3 Git Flow Diagram

```
main      ─────●─────────────────●─────────────●───────
               │                 ↑             ↑
hotfix         │     ○───────────┘             │
               │                               │
release        │         ○─────────────────────┘
               ↓         ↑
develop   ─────●─────────●─────────────────────────────
               │         ↑
feature        ○─────────┘
```

---

## 2. Commit Conventions

### 2.1 Conventional Commits

All commits MUST follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

### 2.2 Commit Types

| Type | Description | Example |
|------|-------------|---------|
| `feat` | New feature | `feat(auth): add two-factor authentication` |
| `fix` | Bug fix | `fix(api): handle null response from payment gateway` |
| `docs` | Documentation only | `docs(readme): update installation instructions` |
| `style` | Code style (no logic change) | `style(lint): fix eslint warnings` |
| `refactor` | Code change (no new feature/fix) | `refactor(user): extract validation logic` |
| `perf` | Performance improvement | `perf(query): add index for user lookup` |
| `test` | Adding/updating tests | `test(auth): add login failure scenarios` |
| `build` | Build system changes | `build(deps): upgrade typescript to 5.3` |
| `ci` | CI configuration | `ci(github): add code coverage reporting` |
| `chore` | Other changes | `chore: update .gitignore` |
| `revert` | Revert previous commit | `revert: feat(auth): add two-factor authentication` |

### 2.3 Commit Message Rules

```bash
# GOOD: Clear, descriptive, properly scoped
feat(exam): add deadline extension request form
fix(api): return 404 instead of 500 for missing user
refactor(auth): extract token validation to separate service
docs(api): add authentication examples to OpenAPI spec

# BAD: Vague, no scope, imperative missing
updated code
fix bug
WIP
asdf
Fixed the thing that was broken
```

### 2.4 Commit Body Guidelines

```bash
feat(notification): implement email queue with retry logic

Add a robust email queue system that handles transient failures
gracefully. Emails are queued in the database and processed by
a background worker.

Key changes:
- Add email_queue table with status tracking
- Implement exponential backoff (1m, 5m, 15m, 1h)
- Add dead letter queue for failed emails after 5 attempts
- Include comprehensive logging for debugging

Closes #234
```

### 2.5 Breaking Changes

```bash
feat(api)!: change user response format

BREAKING CHANGE: The user API now returns `id` instead of `userId`.
All API consumers must update their integrations.

Migration guide:
- Before: response.data.userId
- After: response.data.id

Closes #567
```

---

## 3. Pull Request Guidelines

### 3.1 PR Title Format

Follow the same convention as commits:

```
feat(auth): implement OAuth2 login flow
fix(export): handle large file downloads correctly
```

### 3.2 PR Description Template

```markdown
## Summary

Brief description of what this PR does.

## Related Issues

Closes #123
Related to #456

## Changes

- Added user authentication service
- Created login and registration endpoints
- Implemented JWT token refresh logic

## Testing

- [ ] Unit tests added/updated
- [ ] Integration tests added/updated
- [ ] Manual testing performed

### Test Instructions

1. Run `npm test` to execute all tests
2. Start the dev server and navigate to /login
3. Test login with valid/invalid credentials

## Screenshots

(If applicable)

## Checklist

- [ ] Code follows project style guidelines
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No console.log or debug statements
- [ ] Migrations are reversible
```

### 3.3 PR Size Guidelines

| Size | Lines Changed | Review Time | Recommendation |
|------|---------------|-------------|----------------|
| Small | < 100 | < 30 min | Ideal |
| Medium | 100-300 | 30-60 min | Acceptable |
| Large | 300-500 | 1-2 hours | Split if possible |
| XL | > 500 | > 2 hours | Must split |

---

## 4. Code Review Standards

### 4.1 Review Checklist

**Functionality:**
- [ ] Code does what the PR claims
- [ ] Edge cases handled
- [ ] Error handling appropriate

**Code Quality:**
- [ ] Follows project conventions
- [ ] No code duplication
- [ ] Appropriate abstractions

**Testing:**
- [ ] Tests cover new functionality
- [ ] Tests cover edge cases
- [ ] No flaky tests introduced

**Security:**
- [ ] No sensitive data exposed
- [ ] Input validation present
- [ ] Authorization checks in place

**Performance:**
- [ ] No obvious performance issues
- [ ] Database queries optimized
- [ ] No N+1 queries

### 4.2 Review Comments

```markdown
# Constructive feedback examples

## Suggestion (optional improvement)
**suggestion:** Consider using `Array.find()` instead of 
`filter()[0]` for better readability.

## Question (needs clarification)
**question:** What happens if `userId` is undefined here? 
Should we add a guard clause?

## Issue (must fix)
**issue:** This SQL query is vulnerable to injection. 
Please use parameterized queries.

## Nitpick (minor, non-blocking)
**nit:** Typo in variable name: `recieve` → `receive`

## Praise (acknowledge good work)
**praise:** Great refactoring here! Much cleaner than before.
```

### 4.3 Review Response Time

| Priority | First Review | Resolution |
|----------|--------------|------------|
| Critical (hotfix) | < 2 hours | < 4 hours |
| High (blocking) | < 4 hours | < 1 day |
| Normal | < 1 day | < 3 days |
| Low | < 2 days | < 1 week |

---

## 5. Git Configuration

### 5.1 Required Git Settings

```bash
# User identity (required)
git config user.name "Your Name"
git config user.email "your.email@company.com"

# Line endings (cross-platform)
git config core.autocrlf input  # macOS/Linux
git config core.autocrlf true   # Windows

# Default branch
git config init.defaultBranch main

# Pull strategy
git config pull.rebase true

# Push behavior
git config push.autoSetupRemote true
```

### 5.2 Recommended .gitignore

```gitignore
# Dependencies
node_modules/
vendor/
__pycache__/
*.pyc

# Build outputs
dist/
build/
*.js.map

# Environment
.env
.env.local
.env.*.local

# IDE
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Logs
*.log
logs/

# Testing
coverage/
.nyc_output/

# Temporary
tmp/
temp/
*.tmp
```

### 5.3 Git Hooks (Husky)

```json
// package.json
{
  "scripts": {
    "prepare": "husky install"
  },
  "lint-staged": {
    "*.{ts,tsx}": ["eslint --fix", "prettier --write"],
    "*.{json,md}": ["prettier --write"]
  }
}
```

```bash
# .husky/pre-commit
#!/bin/sh
. "$(dirname "$0")/_/husky.sh"

npx lint-staged
```

```bash
# .husky/commit-msg
#!/bin/sh
. "$(dirname "$0")/_/husky.sh"

npx --no -- commitlint --edit "$1"
```

---

## 6. Merge Strategies

### 6.1 Strategy by Branch Type

| Source | Target | Strategy | Rationale |
|--------|--------|----------|-----------|
| feature/* | develop | Squash | Clean history |
| bugfix/* | develop | Squash | Clean history |
| develop | release/* | Merge | Preserve commits |
| release/* | main | Merge | Preserve commits |
| hotfix/* | main | Merge | Preserve commits |
| main | develop | Merge | Sync changes |

### 6.2 Squash Merge Example

```bash
# Feature branch with messy history
$ git log --oneline feature/EQM-123-auth
a1b2c3d WIP
d4e5f6g fix typo
g7h8i9j more fixes
j0k1l2m initial implementation

# After squash merge to develop
$ git log --oneline develop
x9y8z7w feat(auth): implement login flow (#123)
```

### 6.3 Rebase Rules

```bash
# ALLOWED: Rebase feature branch onto develop
git checkout feature/my-feature
git rebase develop

# FORBIDDEN: Never rebase shared branches
git checkout develop
git rebase main  # DON'T DO THIS
```

---

## 7. Release Process

### 7.1 Semantic Versioning

Follow [SemVer 2.0.0](https://semver.org/):

```
MAJOR.MINOR.PATCH[-PRERELEASE][+BUILD]

Examples:
1.0.0        # Initial release
1.0.1        # Patch: bug fixes only
1.1.0        # Minor: new features, backward compatible
2.0.0        # Major: breaking changes
2.0.0-beta.1 # Pre-release
2.0.0-rc.1   # Release candidate
```

### 7.2 Version Bump Rules

| Change Type | Version Bump |
|-------------|--------------|
| Bug fix, no API change | PATCH |
| New feature, backward compatible | MINOR |
| Breaking change | MAJOR |
| Pre-release | Append `-alpha.N`, `-beta.N`, `-rc.N` |

### 7.3 Release Workflow

```bash
# 1. Create release branch from develop
git checkout develop
git pull origin develop
git checkout -b release/2.1.0

# 2. Bump version
npm version 2.1.0 --no-git-tag-version

# 3. Update changelog
# Edit CHANGELOG.md

# 4. Commit release prep
git add .
git commit -m "chore(release): prepare 2.1.0"

# 5. Create PR to main
# Get approvals, run final tests

# 6. Merge to main (merge commit)
git checkout main
git merge release/2.1.0

# 7. Tag release
git tag -a v2.1.0 -m "Release 2.1.0"
git push origin v2.1.0

# 8. Merge back to develop
git checkout develop
git merge main
git push origin develop

# 9. Delete release branch
git branch -d release/2.1.0
```

---

## 8. Git Best Practices

### 8.1 Do's

- ✅ Write meaningful commit messages
- ✅ Keep commits atomic (one logical change)
- ✅ Pull/rebase before pushing
- ✅ Use feature branches for all changes
- ✅ Delete merged branches
- ✅ Sign commits for security-critical repos

### 8.2 Don'ts

- ❌ Force push to shared branches
- ❌ Commit directly to main/develop
- ❌ Commit secrets or credentials
- ❌ Commit generated files
- ❌ Use vague commit messages
- ❌ Leave WIP commits in history

### 8.3 Recovery Commands

```bash
# Undo last commit (keep changes)
git reset --soft HEAD~1

# Undo last commit (discard changes)
git reset --hard HEAD~1

# Undo a pushed commit (creates revert commit)
git revert <commit-hash>

# Recover deleted branch
git reflog
git checkout -b recovered-branch <commit-hash>

# Fix commit message (last commit only)
git commit --amend -m "New message"

# Interactive rebase (clean up history)
git rebase -i HEAD~3
```
