# Analysis Prompt: PHP Dependency, GRASP, and Dead Code Audit

Act as a Senior PHP Architect. Perform a deep static analysis of the library `misc/BAGArt/` to identify dead code, map dependencies, and evaluate architectural health based on GRASP principles.

## Execution Steps

### Step A: Identify Candidates

Generate a list of all classes, interfaces, traits, and enums (excluding DTO/Enum folders):

```bash
grep -rnE "^\s*(abstract\s+)?(class|interface|trait|enum)\s+\w+" misc/BAGArt/*/src/ --exclude-dir={DTO,Enum}
```

### Step B: Map Usage & Coupling

For each identified candidate, run` a scan to find where it is consumed:

**External calls (App context)**

```bash
grep -rnw "<ClassName>" app/ routes/ config/ resources/ database/ | grep -v "misc/BAGArt/" | sed -E 's/.*\/([a-zA-Z0-9_]+)\.php:[0-9]+:.*->([a-zA-Z0-9_]+).*/\1::\2/' | sort | uniq
```

**Internal calls (Library context)**

```bash
grep -rnw "<ClassName>" misc/BAGArt/*/src/ | grep -v "<FileWithDefinition>" | wc -l
```

## Analytical Criteria

- **Dead Code**: 0 External + 0 Internal references. Action: Delete.
- **Coupling Alert**: If a class is called from > 5 distinct external locations, flag as "High Coupling (God Object candidate)".
- **GRASP Health**: Identify classes that act as "Information Experts" (high internal usage) vs. "Controllers" (high external usage).

## Report Template

```markdown
# Architecture Analysis: <Library Name>

**Date:** 2026-06-29

## 1. Dead Code (To Delete)

| Class | File Path |
|-------|-----------|
|       |           |

## 2. Coupling and Usage (For Refactoring)

| Class | Used By (Class::method) | GRASP Status |
|-------|-------------------------|--------------|
|       | Caller::method1, Caller::method2 | OK / High Coupling |

## 3. Bottlenecks and Recommendations

**High Coupling:**
- List of classes requiring interface extraction.

**Architectural Notes:**
- Suggestions for applying Information Expert or Creator principles to improve structure.
```

## Instructions for the model

- Maintain strict adherence to the Markdown table structure.
- If exact file paths are too complex, prioritize the `Class::method` list for external callers.
- The report must be written in English.

# Result
Save result to `.promt-results/unused-<LIB-NAME>.md`
