<!-- desloppify-begin -->
<!-- desloppify-skill-version: 1 -->
---
name: desloppify
description: >
  Codebase health scanner and technical debt tracker. Use when the user asks
  about code quality, technical debt, dead code, large files, god classes,
  duplicate functions, code smells, naming issues, import cycles, or coupling
  problems. Also use when asked for a health score, what to fix next, or to
  create a cleanup plan. Supports 28 languages.
allowed-tools: Bash(desloppify *)
---

# Desloppify

## 1. Your Job

**Improve code quality by fixing findings and maximizing strict score honestly.**
Never hide debt with suppression patterns just to improve lenient score. After
every scan, show the user ALL scores:

| What | How |
|------|-----|
| Overall health | lenient + strict |
| 5 mechanical dimensions | File health, Code quality, Duplication, Test health, Security |
| 7 subjective dimensions | Naming Quality, Error Consistency, Abstraction Fit, Logic Clarity, AI Generated Debt, Type Safety, Contract Coherence |

Never skip scores. The user tracks progress through them.

## 2. Core Loop

```
scan → follow the tool's strategy → fix or wontfix → rescan
```

1. `desloppify scan --path .` — the scan output ends with **INSTRUCTIONS FOR AGENTS**. Follow them. Don't substitute your own analysis.
2. Fix the issue the tool recommends.
3. `desloppify resolve fixed "<id>"` — or if it's intentional/acceptable:
   `desloppify resolve wontfix "<id>" --note "reason why"`
4. Rescan to verify.

**Wontfix is not free.** It lowers the strict score. The gap between lenient and strict IS wontfix debt. Call it out when:
- Wontfix count is growing — challenge whether past decisions still hold
- A dimension is stuck 3+ scans — suggest a different approach
- Auto-fixers exist for open findings — ask why they haven't been run

## 3. Commands

```bash
desloppify scan --path src/               # full scan
desloppify scan --path src/ --reset-subjective  # reset subjective baseline to 0, then scan
desloppify next --count 5                  # top priorities
desloppify show <pattern>                  # filter by file/detector/ID
desloppify plan                            # prioritized plan
desloppify fix <fixer> --dry-run           # auto-fix (dry-run first!)
desloppify move <src> <dst> --dry-run      # move + update imports
desloppify resolve open|fixed|wontfix|false_positive "<pat>"   # classify/reopen findings
desloppify review --run-batches --runner codex --parallel --scan-after-import  # preferred blind review path
desloppify review --run-batches --runner codex --parallel --scan-after-import --retrospective  # include historical issue context for root-cause loop
desloppify review --prepare                # generate subjective review data (cloud/manual path)
desloppify review --external-start --external-runner claude  # recommended cloud durable path
desloppify review --external-submit --session-id <id> --import review_result.json  # submit cloud session output with canonical provenance
desloppify review --import file.json       # import review results
desloppify review --validate-import file.json  # validate payload/mode without mutating state
```

## 4. Subjective Reviews (biggest score lever)

Score = 40% mechanical + 60% subjective. Subjective starts at 0% until reviewed.

1. Preferred local path: `desloppify review --run-batches --runner codex --parallel --scan-after-import`.
   This prepares blind packets, runs isolated subagent batches, merges, imports, and rescans in one flow.

2. **Review each dimension independently.** For best results, review dimensions in
   isolation so scores don't bleed across concerns. If your agent supports parallel
   execution, use it — your agent-specific overlay (appended below, if installed)
   has the optimal approach. Each reviewer needs:
   - The codebase path and the dimensions to score
   - What each dimension means (from `query.json`'s `dimension_prompts`)
   - The output format (below)
   - Nothing else — let them decide what to read and how

3. Cloud/manual path: run `desloppify review --prepare`, perform isolated reviews,
   merge assessments (average scores if multiple reviewers cover the same dimension)
   and findings, then import:
   ```bash
   desloppify review --import findings.json
   ```
   Import is fail-closed by default: if any finding is invalid/skipped, import aborts.
   Use `--allow-partial` only for explicit exceptions.
   External imports ingest findings by default. For durable cloud-subagent scores,
   prefer the session flow:
   `desloppify review --external-start --external-runner claude` then use the generated
   `claude_launch_prompt.md` + `review_result.template.json`, and run the printed
   `desloppify review --external-submit --session-id <id> --import <file>` command.
   Legacy durable import remains available via
   `--attested-external --attest "I validated this review was completed without awareness of overall score and is unbiased."`
   (with valid blind packet provenance in the payload).
   Use `desloppify review --validate-import findings.json ...` to preflight schema
   and import mode before mutating state.
   Manual override cannot be combined with `--allow-partial`, and those manual
   assessment scores are provisional: they expire on the next `scan` unless
   replaced by trusted internal or attested-external imports.

   Required output format per reviewer:
   ```json
   {
     "session": { "id": "<session_id_from_template>", "token": "<session_token_from_template>" },
     "assessments": { "naming_quality": 75.0, "logic_clarity": 82.0 },
     "findings": [{
       "dimension": "naming_quality",
       "identifier": "short_id",
       "summary": "one line",
       "related_files": ["path/to/file.py"],
       "evidence": ["specific observation"],
       "suggestion": "concrete action",
       "confidence": "high|medium|low"
     }]
   }
   ```
   For non-session legacy imports (`review --import ... --attested-external`), `session` may be omitted.

4. **Fix findings via the core loop.** After importing, findings become tracked state
   entries. Fix each one in code, then resolve:
   ```bash
   desloppify issues                    # see the work queue
   # ... fix the code ...
   desloppify resolve fixed "<id>"      # mark as fixed
   desloppify scan --path .             # verify
   ```

**Do NOT fix findings before importing.** Import creates tracked state entries that
let desloppify correlate fixes to findings, track resolution history, and verify fixes
on rescan. If you fix code first and then import, the findings arrive as orphan issues
with no connection to the work already done.

Need a clean subjective rerun from zero? Run `desloppify scan --path src/ --reset-subjective` before preparing/importing fresh review data.

Even moderate scores (60-80) dramatically improve overall health.

Integrity safeguard:
- If one subjective dimension lands exactly on the strict target, the scanner warns and asks for re-review.
- If two or more subjective dimensions land on the strict target in the same scan, those dimensions are auto-reset to 0 for that scan and must be re-reviewed/imported.
- Reviewers should score from evidence only (not from target-seeking).

## 5. Quick Reference

- **Tiers**: T1 auto-fix, T2 quick manual, T3 judgment call, T4 major refactor
- **Zones**: production/script (scored), test/config/generated/vendor (not scored). Fix with `zone set`.
- **Auto-fixers** (TS only): `unused-imports`, `unused-vars`, `debug-logs`, `dead-exports`, etc.
- **query.json**: After any command, has `narrative.actions` with prioritized next steps.
- `--skip-slow` skips duplicate detection for faster iteration.
- `--lang python`, `--lang typescript`, or `--lang csharp` to force language.
- C# defaults to `--profile objective`; use `--profile full` to include subjective review.
- Score can temporarily drop after fixes (cascade effects are normal).

## 6. Escalate Tool Issues Upstream

When desloppify itself appears wrong or inconsistent:

1. Capture a minimal repro (`command`, `path`, `expected`, `actual`).
2. Open a GitHub issue in `peteromallet/desloppify`.
3. If you can fix it safely, open a PR linked to that issue.
4. If unsure whether it is tool bug vs user workflow, issue first, PR second.

## Prerequisite

`command -v desloppify >/dev/null 2>&1 && echo "desloppify: installed" || echo "NOT INSTALLED — run: pip install --upgrade git+https://github.com/peteromallet/desloppify.git"`

<!-- desloppify-end -->

## Gemini CLI Overlay

Gemini CLI has experimental subagent support, but subagents currently run
sequentially (not in parallel). Review dimensions one at a time.

### Setup

Enable subagents in Gemini CLI settings:
```json
{
  "experimental": {
    "enableAgents": true
  }
}
```

Optionally define a reviewer agent in `.gemini/agents/desloppify-reviewer.md`:

```yaml
---
name: desloppify-reviewer
description: Scores subjective codebase quality dimensions for desloppify
kind: local
tools:
  - read_file
  - search_code
temperature: 0.2
max_turns: 10
---

You are a code quality reviewer. You will be given a codebase path, a set of
dimensions to score, and what each dimension means. Read the code, score each
dimension 0-100 from evidence only, and return JSON in the required format.
Do not anchor to target thresholds. When evidence is mixed, score lower and
explain uncertainty.
```

### Review workflow

1. Preferred local path (Codex runner): `desloppify review --run-batches --runner codex --parallel --scan-after-import`.
2. Gemini/cloud path: run `desloppify review --prepare` to generate `query.json`.
3. Invoke the reviewer agent for each group of dimensions sequentially.
   Even without parallelism, isolating dimensions across separate agent
   invocations prevents score bleed between concerns.
4. Merge assessments and findings, then import:
   - robust session flow (recommended): `desloppify review --external-start --external-runner claude`; use generated `claude_launch_prompt.md` + `review_result.template.json`, then run printed `desloppify review --external-submit --session-id <id> --import <file>`
   - durable scored import (legacy): `desloppify review --import findings.json --attested-external --attest "I validated this review was completed without awareness of overall score and is unbiased."`
   - findings-only fallback: `desloppify review --import findings.json`

When Gemini CLI adds parallel subagent execution, split dimensions across
concurrent agent calls instead.

### Review integrity

1. Do not use prior chat context, score history, or target-threshold anchoring while scoring.
2. Score from evidence only; when evidence is mixed, score lower and explain uncertainty.
3. Return machine-readable JSON only for review imports. For `--external-submit`, include `session` from the generated template:

```json
{
  "session": {
    "id": "<session_id_from_template>",
    "token": "<session_token_from_template>"
  },
  "assessments": {
    "naming_quality": 0,
    "error_consistency": 0,
    "abstraction_fit": 0,
    "logic_clarity": 0,
    "ai_generated_debt": 0
  },
  "findings": [
    {
      "dimension": "naming_quality",
      "identifier": "short_id",
      "summary": "one-line defect summary",
      "related_files": ["relative/path/to/file.py"],
      "evidence": ["specific code observation"],
      "suggestion": "concrete fix recommendation",
      "confidence": "high|medium|low"
    }
  ]
}
```
4. `findings` MUST match `query.system_prompt` exactly. Use `"findings": []` only when no defects are found.
5. Import is fail-closed by default: invalid/skipped findings abort `desloppify review --import` unless `--allow-partial` is explicitly passed.
6. Assessment scores are auto-applied from trusted internal run-batches imports, or via Claude cloud session imports (`--external-start` + printed `--external-submit`). Legacy attested external import via `--attested-external` remains supported.

<!-- desloppify-overlay: gemini -->
<!-- desloppify-end -->

## 7. Workflow Requirement

**ALWAYS run a `desloppify scan --path .` as the final step before completing any assigned task.** This ensures that code health is tracked persistently and any regressions are caught immediately. Present the final scores to the user as part of your task completion summary.
