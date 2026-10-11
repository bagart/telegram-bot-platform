# ADR-008: Question Tracker and Docs Lifecycle

> Status: accepted
> Date: 2026-10-08

## Context

Two questions from the same grilling session ("docs/questions maintenance", round 2, 2026-10-07) had to be settled before the question tracker could be advanced. First, the user's directive "work on docs/questions" was ambiguous about what "work" means — auditing the OPEN questions, grilling them to decisions, consolidating RESOLVED ones into ADRs, or pure format hygiene — and the environment forced the issue: no root `docs/adr/` existed to consolidate into, existing `docs/tasks/adr/` ADRs were themselves gitignored, some resolved files had empty `## Decision` sections, and every consolidation step would also have to repair committed cross-references. Second, the git tracking policy for question files was in visible tension with committed documents: `.gitignore` rules for `docs/questions` keep only `.gitkeep`/`README` tracked (all question files are local-only), while committed docs cite question files as decision records — unresolvable on a fresh clone, CI, or for another agent/teammate.

Both questions therefore turned on the same two-part design stated in three skill documents: question files are ephemeral local input, and the permanent committed trace lives only in `docs/adr/`. Changing tracking without changing that design would create two sources of truth; consolidating without repointing citations would break them. The decisions below fix the maintenance sequence and confirm the tracking policy, with cross-reference repair bound to the consolidation step where it can actually stick.

## Decision

### Question tracker maintenance scope (source question: `docs-questions-work-scope`, resolved 2026-10-07)

**Option 4 — sequenced combination, per the user's 2026-10-07 directive (decided 2026-10-07).** Research of remaining OPEN questions is delegated to sub-agents using the Phase 2 routine (`dev-grilling.md:106-176`); the session itself asks the user no questions. The sequence:

1. Research every remaining OPEN question in sub-agents (Phase 2 routine) — no user questions this session.
2. Record each answer in its question file and **package the implementation outcomes as `docs/tasks/<topic>.md` task files** (one per topic, `Requirements:` citing the research/question that justifies it — `AGENTS.md:208`, `dev-ai.md:54`).
3. Format hygiene (empty `Decision` sections, stale Context counts) folded into the same pass.
4. **ADR consolidation — create committed root `docs/adr/`, port the 42 resolved decisions, repoint committed citations (`dev-ai.md`, `docs/STATUS.md`), delete consolidated question files — deferred to a separate future task** per `workflow-tasks.md:109` / `dev-grilling.md:180-201` as its own lifecycle step.

Rejected/subsumed alternatives: option 1 (audit + grill OPEN) is subsumed by step 1 — the sub-agent research pass is the audit and grill in one step and had already resolved 10 of the original 15 OPEN questions on 2026-10-07. Option 2 (consolidate now) is deferred, not rejected — consolidation needed a home directory that did not exist yet and is the *release* step of the grilling, a distinct work item deserving its own task. Option 3 (format hygiene) has no decision value alone and is folded in as a by-product in step 3.

Binding residual: `settings-screen-channel-repair-or-retire` is the only human-only OPEN question left (owner capability call: fund vs. retire Channel B); it is excluded from the no-questions research pass by definition and stays OPEN with its Note.

### Git tracking policy for question files (source question: `question-files-git-tracking`, resolved 2026-10-07)

**Option 1 — keep question files gitignored (decided 2026-10-07).** Tracking policy stays exactly as designed (`workflow-tasks.md:126-127`, `workflow-tasks-question.md:81`): question files are ephemeral local input; permanence comes only from ADR consolidation. The `.gitignore` rules for `docs/questions` keep only `.gitkeep`/`README` tracked.

Binding obligation: cross-references in committed docs (`dev-ai.md:148,149,159,160`, `docs/STATUS.md:36,38,39`, module docs) are repaired **during the deferred ADR-consolidation task** — that task creates committed root `docs/adr/`, ports the decisions, repoints every citation to the corresponding ADR, and only then deletes the consolidated question files (deletion would break question-file citations anyway, so consolidation is the single point where the repair sticks).

Rejected alternatives: option 2 (track all) rejected — contradicts the three-place documented design and still requires ADRs for post-consolidation durability → two sources of truth (`dev-grilling.md:259`). Option 3 (track RESOLVED only) rejected — half-state with gitignore exceptions, fixes no reference, leaves OPEN questions invisible. Residual risk (machine/branch loss before consolidation) is accepted and bounded by making consolidation the next scheduled task rather than an open-ended deferral.

## Consequences

Contracts touched: the documented question lifecycle in `docs/skills/workflow-tasks.md` (gitignored trackers, consolidation as the grilling release step), `docs/skills/workflow-tasks-question.md` (rule 6: deletion only at ADR consolidation; global gitignore), `docs/skills/dev-grilling.md` (Phase 2 research routine, Phase 3 consolidation procedure, no-two-sources-of-truth rule), plus the implementation-workflow gates in `AGENTS.md` and `dev-ai.md` for packaging outcomes as task files.

Depends on these decisions: the maintenance order of the tracker (research → task files → hygiene → consolidation), the durability of decision records (ADR-only, not question files), and every committed citation to question files — this wave's citation-repair step repoints them at ADRs so none dangles after deletion. The git-tracking decision's repair obligation and the work-scope decision's step-4 deferral are the same obligation seen from two sides.

Follow-ups: **the step-4 deferral ("ADR consolidation — create committed root `docs/adr/`, port the resolved decisions, repoint committed citations, delete consolidated question files") is completed by this wave — ADR-001..008, dated 2026-10-08**; ADR-008 is the last file of that wave, so the citation repair and deletion obligations bind immediately after it. The `settings-screen-channel-repair-or-retire` owner capability call was answered 2026-10-07 (option 1 — retire Channel B, recorded in `ADR-005-settings-storage-contract`); packaged implementation outcomes live as ephemeral task files awaiting execution.
