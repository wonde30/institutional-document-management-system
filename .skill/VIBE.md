# 2026 VIBE CODING SETUP — WOLLO UNIVERSITY SYSTEM

## The Core Idea

Vibe coding in 2026 means every AI agent that touches your project already knows:
- What you're building
- What your conventions are
- What it must never do
- Exactly what the stack is

You never re-explain context. You just say "build the approval workflow" and the agent knows your RBAC, your DB conventions, your layer architecture, your naming rules.

One AGENTS.md in the project root makes this happen across every tool.

---

## YOUR FILES AND WHERE THEY LIVE

```
project-root/
├── AGENTS.md          ← universal context (OpenCode, Claude Code, Copilot, Cursor, Codex)
├── CLAUDE.md          ← symlink or copy of AGENTS.md (Claude Code reads this name)
├── .cursorrules       ← symlink or copy of AGENTS.md (Cursor reads this name)
backend/
frontend/
```

**AGENTS.md is the single source of truth.** The others point to it.

On Windows (PowerShell), create symlinks:
```powershell
# Run from project root
New-Item -ItemType SymbolicLink -Path "CLAUDE.md" -Target "AGENTS.md"
# For .cursorrules: Cursor uses the same content, just copy it
Copy-Item AGENTS.md .cursorrules
```

---

## YOUR TOOL STACK (2026, FREE WHERE POSSIBLE)

### Terminal Agent: OpenCode + Gemini Free Tier
Your primary coding agent. Lives in the terminal. Reads AGENTS.md automatically.

```bash
# Start a session
opencode

# First message every session:
/context AGENTS.md

# Then go straight to work
```

Switch to Gemini free tier (fastest, most capable free option):
```
/connect → Google → paste Gemini API key from aistudio.google.com
/models → gemini-2.5-flash
```

---

### Offline Agent: Ollama (no internet needed)
When internet is slow or down.

```bash
# Already installed → just run:
ollama run qwen2.5-coder:3b

# Or through OpenCode:
/connect → Ollama
/models → qwen2.5-coder:3b
```

Paste AGENTS.md content at the start of each session manually (Ollama has no file reading).

---

### IDE: VS Code + GitHub Copilot Free
GitHub Copilot free tier gives you 2,000 completions/month. Enough for daily work.

Setup:
1. Install VS Code
2. Extensions: GitLens, Tailwind CSS IntelliSense, PHP Intelephense, Volar (Vue), ESLint, Prettier
3. Install GitHub Copilot extension → sign in with GitHub (free tier)
4. AGENTS.md in project root → Copilot reads it automatically as CLAUDE.md context

---

### Chat Sessions: Claude (here) or Gemini
For architecture planning, audit sessions, deep dives.

Paste `SYSTEM.md` as first message → then paste code.
Paste `DEBUG.md` as first message for debugging/inspection sessions.

---

## THE DAILY WORKFLOW

### Starting a feature

```
1. Create feature branch
   git checkout -b feature/document-versioning

2. Open OpenCode in project root
   opencode

3. Load project context
   /context AGENTS.md

4. Plan before building
   /plan [describe the feature in one sentence]
   → AI returns: schema, API contract, authorization design, data flow
   → You review and approve before any code is written

5. Build layer by layer
   migration → model → policy → FormRequest → controller → resource → service
   (never skip layers, never merge layers)

6. Inspect before committing
   Paste each file into DEBUG session → /inspect
   Fix all CRIT and HIGH issues

7. Humanize before showing anyone
   Paste each file → /humanize
   Apply the renames and strip AI patterns

8. Commit
   git add .
   git commit -m "feat: add document version history endpoint"
```

---

### Debugging a failure

```
1. Get the full stack trace (not just the first line)

2. Open DEBUG session
   Paste DEBUG.md → paste error + relevant code

3. Run /debug
   → AI gives 4-phase diagnosis
   → You fix the root cause, not the symptom

4. Write the regression test first
   it('does not throw when document has no previous version', function() {})
   → Run it → it fails
   → Apply fix → run it → it passes
```

---

### Before any demo or PR

```
1. Run the audit prompt (QMT_FULLSTACK_AUDIT_PROMPT.md)
   Paste it + all changed files

2. Fix everything rated CRIT and HIGH

3. Run /humanize on every file you wrote in this session

4. Check AGENTS.md "WHAT AI AGENTS MUST NEVER DO" list
   Every item on that list — verify it's not in your code
```

---

## PROMPT QUICK REFERENCE

| File | Use for | Paste in |
|---|---|---|
| `AGENTS.md` | Lives in project root — agents read it automatically | OpenCode, Claude Code, Copilot |
| `SYSTEM.md` | Architecture, planning, feature work, code review | Any chat session as first message |
| `DEBUG.md` | Bugs, errors, inspection, humanization | Any chat session as first message |
| `QMT_FULLSTACK_AUDIT_PROMPT.md` | Pre-demo, pre-PR full audit | Any chat session |

---

## COMMAND QUICK REFERENCE

| Command | What it does |
|---|---|
| `/plan [feature]` | Schema + API + auth design before any code |
| `/audit [code]` | Scored review, numbered issues |
| `/inspect [code]` | Security + performance + auth scan |
| `/debug [code + error]` | 4-phase root cause diagnosis |
| `/humanize [code]` | Strip AI patterns, force domain names |
| `/scar [code]` | Add missing TODO/FIXME/NOTE comments |
| `/teach [topic]` | Deep concept explanation with test question |
| `/quiz [topic]` | 5 questions, scored |
| `/short` | Compressed output mode |
| `/full` | Full output mode |

---

## GIT WORKFLOW (solo but professional)

```bash
# Never work directly on main
git checkout -b feature/[name]

# Small commits, one concern per commit
git commit -m "feat: add department scoping to document index"
git commit -m "fix: approval step not updating on second approver"
git commit -m "chore: add Reverb config to .env.example"

# Merge via squash when feature is done
git checkout main
git merge --squash feature/[name]
git commit -m "feat: document versioning complete"
git branch -d feature/[name]
```

Commit prefix rules:
- `feat:` new functionality
- `fix:` bug fix
- `chore:` config, deps, non-functional
- `refactor:` restructure without behavior change
- `test:` tests only
- `docs:` documentation only

---

## ENVIRONMENT FILES

```bash
# .env.example — commit this
# .env — never commit this

# Key variables to add for this system
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=localhost
REVERB_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"

FILESYSTEM_DISK=local   # → s3 in production
AWS_BUCKET=             # → Cloudflare R2 in production

QUEUE_CONNECTION=database  # → redis in production
```

---

## WEEKLY RHYTHM

| Day | Focus |
|---|---|
| Saturday | Plan next week's features using /plan |
| Sun–Thu | Build — one layer per session, inspect before moving on |
| Friday | Audit everything built this week, humanize, commit clean |

One feature per week. Finished and clean beats two features half-done.

---

## WHAT MAKES THIS PROJECT CAREER-DEFINING

This system, done properly, demonstrates:
- Production RBAC with Spatie Permission
- Real-time WebSockets with Reverb
- File versioning with Media Library
- Approval workflow state machine
- Audit logging for administrative context
- Full-stack authorization consistency
- Institution-scale data scoping (departments)

That is a senior-level portfolio piece. Not "I built a Todo app". Wollo University using it makes it real. Real usage beats polished demos every time.
