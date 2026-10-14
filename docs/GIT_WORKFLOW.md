# Git Workflow — FindBack

This project uses a protected, PR-based workflow.

## Branches

| Branch          | Purpose                                            | Protected |
| --------------- | -------------------------------------------------- | --------- |
| `main`          | Production-ready code                              | ✅ Yes    |
| `development`   | Integration branch; features are merged here       | ✅ Yes    |
| `feature/*`     | Individual features (e.g. `feature/database`)      | ❌ No     |

```
main  <--------  development  <--------  feature/database
 (prod)           (integration)          (your work)
```

## Branch protection rules

Both `main` and `development` are protected with:

- **Pull request required** — changes cannot be pushed directly; open a PR and
  merge it. (At least 1 approving review is requested, but the repository
  admin can complete the merge.)
- **Force pushes disabled** — history cannot be rewritten on these branches.
- **Deletion disabled** — the branches cannot be deleted.
- **Admin bypass enabled** — the owner/admin can still push/merge directly when
  necessary (so you are never locked out of your own repo).

## Standard feature workflow

```powershell
# 1. Create a feature branch off development
git checkout development
git pull
git checkout -b feature/my-feature

# 2. Do work, then commit
git add -A
git commit -m "Describe the change"

# 3. Push the feature branch
git push -u origin feature/my-feature

# 4. Open a pull request into development
gh pr create --base development --head feature/my-feature --fill

# 5. Merge the PR
gh pr merge --merge --delete-branch
```

## Commit message conventions

- Use the imperative mood: `Add user authentication`, not `Added` / `Adds`.
- Keep the subject line short (≤ ~72 chars); add detail in the body if needed.
- Reference issues where relevant.

## Secrets & git-ignore

- `.env` (and any `*.env` except `.env.example`) is **git-ignored** — never
  commit credentials.
- `vendor/` and `node_modules/` are ignored; commit `composer.lock` and
  `package-lock.json` only.
