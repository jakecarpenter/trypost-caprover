---
description: Friday release ritual — create git tag, GitHub release (auto-generated changelog), and a customer-facing email draft (Cal.com style)
allowed-tools: Bash, Write, Read, Skill
---

You are running the Friday release ritual for TryPost. Three artifacts are produced:

1. A git tag (semver)
2. A GitHub release with the **auto-generated** changelog (PR list + authors via GitHub's native generator — flat, technical, for developers)
3. A **customer-facing email draft** in Cal.com style (themed prose, end-user voice, no commit/PR references)

Plus local mirrors in `releases/<version>/`.

**Always confirm with the user before any push/tag/release.**

## Context (auto-loaded)

- Current branch: !`git branch --show-current`
- Working tree: !`git status --porcelain`
- Latest tag: !`git describe --tags --abbrev=0 2>/dev/null || echo "(none)"`
- Repo (owner/name): !`gh repo view --json nameWithOwner -q .nameWithOwner 2>/dev/null || echo "(no gh)"`
- Local vs origin/main: !`git fetch --quiet origin main 2>/dev/null; git rev-list --left-right --count HEAD...origin/main 2>/dev/null || echo "0	0"`
- Commits since latest tag (or all if no tag): !`LAST_TAG=$(git describe --tags --abbrev=0 2>/dev/null); if [ -z "$LAST_TAG" ]; then git log --pretty=format:"%H%x09%s" --reverse; else git log "$LAST_TAG"..HEAD --pretty=format:"%H%x09%s" --reverse; fi`

## Workflow

### Step 1 — Pre-flight checks

Stop and tell the user if any of these fail:

- Current branch must be `main`. Else: ask user to `git checkout main`.
- Working tree must be clean. Else: ask user to commit/stash.
- Local in sync with `origin/main` (rev-list count `0	0`). Else: ask user to pull/push.
- Commits-since-tag list must be non-empty. Else: "Nada novo desde a última tag."

### Step 2 — Determine next version

TryPost uses **sequential numbering with rollover at 9** — not standard semver. Do not parse conventional commits to choose the bump. Every release is the next sequential number, whatever the commits look like.

1. If no previous tag exists → next version = **`v1.0.0`** (first release ever).
2. Otherwise, parse the latest tag as `vMAJOR.MINOR.PATCH` and increment by these rules:
   - `patch += 1`
   - If `patch` reaches `10`: set `patch = 0`, `minor += 1`
   - If `minor` reaches `10`: set `minor = 0`, `major += 1`
3. Re-prefix with `v`.

Examples:

| From | To |
|---|---|
| (no tag) | v1.0.0 |
| v1.0.0 | v1.0.1 |
| v1.0.8 | v1.0.9 |
| v1.0.9 | v1.1.0 |
| v1.5.7 | v1.5.8 |
| v1.9.8 | v1.9.9 |
| v1.9.9 | v2.0.0 |

There is no manual override — the next version is whatever the rule above produces. If a release needs a different version for some special reason, the user must create the tag manually outside this command.

### Step 3 — Preview the changelog (GitHub native format)

Use GitHub's release-notes generator API to produce the changelog **without creating anything yet**:

```bash
gh api -X POST "repos/{OWNER}/{REPO}/releases/generate-notes" \
  -f tag_name="<new_version>" \
  -f target_commitish="main" \
  -f previous_tag_name="<latest_tag>" \
  --jq '.body'
```

For the first release ever (no previous tag), omit the `previous_tag_name` flag — GitHub falls back to the initial commit.

The body already contains:
- `<subject> by @<author> in #<PR>` lines
- "New Contributors" section when applicable
- `Full Changelog: ...` compare link

**Do not modify it.** The GitHub-native format is the goal.

### Step 4 — Draft the customer email (Cal.com style)

This email is for **end users of TryPost** — non-developers, paying customers, trial users. It must **NOT** reference: commits, PRs, authors, SHAs, conventional commit scopes, version control concepts, internal class names, file paths.

Read the commits only as **internal source material**. Translate to user-facing language.

#### Structure

```markdown
---
subject: "Changelog: TryPost <version> — <theme 1>, <theme 2>, <theme 3>..."
---

# Changelog: TryPost <version> — <theme 1>, <theme 2>, <theme 3>...

By Paulo Castellano • Release <version>

Hello! Welcome to this week's update. Here's what's new in TryPost.

## <Theme 1>

<2-4 sentences of concrete narrative — what changed, why a user should care, what they'll notice. No marketing puffery.>

## <Theme 2>

<same>

## <Theme 3 — only if there are genuinely 3 themes worth of work>

<same>

## New features

- <user-facing one-liner — what they can now do>
- <...>

## Fixes

- <user-facing one-liner — what no longer breaks>
- <...>

Cheers,
Paulo Castellano from TryPost.it
```

#### Theme grouping (AI clusters by user impact)

Read all commits since the last tag and cluster into **2-3 user-facing themes**. Use whatever frame makes the changes feel coherent to a customer, not to a developer.

**Good themes** (end-user framing):
- "Trial protection" — bundles billing/Stripe Radar work
- "Reliable Facebook posting" — bundles Facebook fixes
- "Faster scheduling" — bundles queue/post improvements
- "Better post editor" — bundles UI changes to the post composer

**Bad themes** (internal framing — never use these):
- "Refactoring"
- "Dependency updates"
- "Feature commits" / "Fix commits"
- "Backend improvements"

If there are fewer than 3 themeable groups, use 2 or just 1. Don't pad. Internal-only changes (chore, CI, refactor, deps) usually shouldn't appear at all — fold the user-visible ones into "Fixes" with a user-voice rewrite, drop the rest.

#### Bullet rules for "New features" / "Fixes"

Rewrite each item in **user voice**, not commit voice:

- ❌ "fix(facebook): send Graph API requests as form-urlencoded"
- ✅ "Fixed an issue where multi-image Facebook posts could fail to publish"

- ❌ "feat(billing): charge one-time trial setup fee at Stripe Checkout"
- ✅ (Probably its own theme, not a bullet — billing is a big user-facing topic)

- ❌ "chore(deps): bump axios to 1.13.5"
- ✅ (Skip entirely — pure internal)

If a commit has no user-visible effect, **omit it**. Don't pad the email.

#### Subject line

Pattern: `Changelog: TryPost <version> — <theme 1>, <theme 2>, <theme 3>...`

Cap around 80 chars. If themes don't fit, shorten to the 2 most impactful + "and more...".

### Step 5 — Humanize the email prose

Run the email body through the `humanizer` skill before previewing:

1. Invoke the `Skill` tool with `skill: humanizer` and pass the draft email body plus this context: *"This is a customer-facing changelog email for TryPost (social media scheduler SaaS). Tone: developer founder writing to early users on a Friday — warm, specific, no marketing puffery. Cal.com style. Keep the existing structure (subject frontmatter, section headers, bullets, signature). Do not strip section headers or the 'Cheers, Paulo Castellano from TryPost.it' signature."*
2. Replace the draft email body with the humanized version.

**Do NOT humanize:**
- The changelog from Step 3 (flat commit list, no prose).
- The subject line frontmatter.
- The literal signature `Cheers,\nPaulo Castellano from TryPost.it` — keep it exact.

The humanizer skill itself covers all patterns. Trust it.

### Step 6 — Confirm with the user

Show:
1. **Proposed version** (e.g., `v1.0.9 → v1.1.0` — sequential rollover at 9).
2. **Changelog preview** (Step 3 output).
3. **Email preview**: subject line + full body (post-humanizer).
4. **Files that will be created/pushed**:
   - Tag `<version>` (pushed to origin)
   - GitHub release `<version>`
   - `releases/<version>/changelog.md`
   - `releases/<version>/email.md`

Then ask in Portuguese: **"Crio a tag, publico o release e salvo os arquivos?"**

Do **not** proceed without explicit yes.

### Step 7 — Execute

After confirmation, in this exact order:

1. Create local directory: `mkdir -p releases/<version>`
2. Write `releases/<version>/changelog.md` with the Step 3 content (raw GitHub markdown).
3. Write `releases/<version>/email.md` with frontmatter + humanized body.
4. Create annotated tag: `git tag -a <version> -m "Release <version>"`
5. Push tag: `git push origin <version>`
6. Create the GitHub release using the changelog file as body:
   ```bash
   gh release create <version> --title "<version>" --notes-file releases/<version>/changelog.md
   ```
7. Report to the user:
   - GitHub release URL (from `gh` output)
   - Local paths: `releases/<version>/changelog.md`, `releases/<version>/email.md`
   - Reminder: *"Os arquivos em `releases/<version>/` não foram commitados. Commit depois se quiser preservar o histórico no repo."*

### On failure

- `git push origin <version>` fails: report the exact error, leave the local tag in place, do not retry destructively.
- `gh release create` fails: the tag is already pushed; tell the user they can recreate manually with `gh release create <version> --title "<version>" --notes-file releases/<version>/changelog.md`.
- `Skill` or `Write` failure during artifact prep: report and stop. Do not push the tag without the artifacts being prepared.
