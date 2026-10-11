# Phase 1 browser review

Reviewed on 2026-10-12 using installed Chrome in headless mode. The in-app browser runtime was unavailable, so the browser-use workflow's standalone fallback was used. A temporary local PHP server used `smile_testing`, fictional accounts and fictional learner data; the development database was not used for learner writes.

## Checked

- Login at desktop/mobile size; remembered-login submission and the resulting persistent authentication cookie.
- Overview at 1440px and 390px, desktop dark/light appearance, mobile sidebar, Escape dismissal and navigation.
- Learner creation with current school year, prior-consent confirmation, optional empty LRN, generated SMILE ID and initial non-graded enrollment.
- Populated roster and learner profile at desktop/mobile size. Forms fit the viewport; the roster table scrolls within its container.
- Document checklist: mark a fictional birth certificate submitted, upload a dummy PDF and download identical bytes through its signed private link.
- Mobile document checklist and read-only audit history; creation, checklist updates, upload and download entries appear.
- Logout, guest redirect away from a learner record and rejected login with password clearing.

## Fixed

- **Remember me:** the starter checkbox changed visually but always submitted `false`. Connected it to the real Inertia form state; three frontend regression tests cover checked/unchecked submission and rejected-login cleanup.
- **School date:** UTC defaulted enrollment to the previous date before 08:00 Philippine time. Applied the owner's Asia/Manila choice to school-date defaults and validation through `SCHOOL_TIMEZONE`; stored event timestamps retain UTC. Updated the example and ignored local environments. Six backend cases check form defaults, consent display, UTC preservation and five date-validation paths across the boundary. Browser enrollment was repeated against the final UTC-storage/Manila-school-date configuration and displayed/saved the Philippine date.

## Limits and follow-up

- Owner acceptance on an actual phone/laptop remains pending. This was Chrome viewport emulation, not a physical-device or complete accessibility audit.
- External Bunny fonts were blocked only in the review browser because loading stalled. Screenshots used fallback fonts; final font appearance needs owner verification.
- Overview statistics, charts and activity remain placeholders; the learner roster has live counts. Disabled future navigation, filters and exports remain unavailable. No Phase 2 feature was added.
- Guardian/assessment editing, annual enrollment, LRN recording and all administrator forms were not exercised in this browser pass; their backend coverage remains the primary evidence.
- Print/PDF report fidelity, parent access, exceptional operator access and approved graded-level changes remain later work. See [PHASE1_AUTHORIZATION_REVIEW.md](PHASE1_AUTHORIZATION_REVIEW.md).

Temporary scripts and fictional screenshots stay in ignored `storage/framework/testing/browser-review/`. They are not shipped or committed. The temporary server, fictional authentication state and uploaded QA file are removed after review. Isolated PostgreSQL regression tests reset the QA records.
