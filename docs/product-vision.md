# BusinessPulse — Product Vision

Developed by Inventa Studio. Tagline: **See what matters. Act on it. Measure the impact.**

## 1. Product identity
BusinessPulse is a Business Intelligence and Decision Execution Platform. It is not a dashboard, a chatbot, a meeting summarizer, or a task manager. Its differentiator is connecting the full decision lifecycle:

**Detect → Understand → Decide → Execute → Verify**

| Lifecycle step | What BusinessPulse does |
|---|---|
| Detect | Imports business data, computes deterministic metrics, evaluates business rules, raises issues. |
| Understand | Shows evidence per issue and generates an AI executive brief from verified facts. |
| Decide | Captures management meetings, transcribes them, extracts decisions and proposed tasks. |
| Execute | Humans approve task drafts; staff own and update assigned tasks. |
| Verify | Later data is re-evaluated against the preserved baseline evidence to show what changed. |

Every feature must serve at least one lifecycle step. Features that do not are out of scope.

## 2. Target users
- **Owner / Admin** (company owner, general manager): wants to know what needs attention, decide, and see if it worked.
- **Manager** (department head, e.g. Sales Manager): reviews issues, approves task drafts, assigns work in the department.
- **Staff** (e.g. sales executive): sees assigned tasks, updates progress.

## 3. Business problem
Owners receive data in spreadsheets, discuss problems in meetings, and lose the thread: decisions are not captured, tasks are not tracked, and nobody checks whether the action changed the numbers. BusinessPulse closes that loop.

## 4. Value proposition
1. Problems are found by deterministic rules, not opinions.
2. AI explains verified facts; it never invents figures.
3. Meeting talk becomes accountable, human-approved tasks.
4. Outcomes are measured against preserved evidence, without claiming causation.

## 5. Principles
- Deterministic core, AI as assistant.
- Human approval before any assignment.
- Incomplete data is never treated as zero or as normal performance.
- Traceability from data to outcome.
- Simple modular monolith; Laravel-native.

## 6. Stage boundaries
- **Stage 1 — Functional Proof of Value:** one company, Sales department, one CSV template, three predefined rules, on-demand briefs, meeting intelligence, task execution, outcome verification, polished UI, deployable without Docker. Production foundation, not a throwaway prototype.
- **Stage 2 — Production MVP (context only, not specified):** more departments, configurable rules, advanced imports, scheduled briefs, reminders, escalation, WhatsApp and email notifications, expanded administration, operational controls, client-specific requirements.
