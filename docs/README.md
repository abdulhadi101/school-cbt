# School CBT Documentation

This directory tracks where the standalone CBT is going and what has already been built.

## Relevant Documents

- `standalone-cbt-plan.md`: product scope, architecture direction, pilot requirements, completed work, next roadmap, and release priorities.
- `project-status.md`: current implementation status, verified commands, committed state, uncommitted work, and immediate next steps.
- `extraction-checklist.md`: SMS CBT lessons, defects to avoid, and rewrite guidance. This is historical reference only; the standalone app must not depend on copied SMS code.

## Current Direction

The app is now a clean standalone Laravel/Vue/MySQL CBT system for a single school LAN deployment. We are no longer copying SMS CBT files into a `_reference` folder. Useful SMS ideas can be consulted from the original SMS repo, but production code is being rewritten in `school_cbt`.

## Current Next Feature

Exam draft authoring is now in place. The next logical feature is exam scheduling and audience assignment:

- assign exams to class levels and sections
- configure per-student accommodations
- expose eligible published exams to students
- connect schedules to invigilation and attempt-start rules
