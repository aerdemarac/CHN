# Medical Cases integration

The shared page is `test-endpoints/medical-cases.php`; the root page has been removed. XAMPP preview URL: `http://localhost/CHN/test-endpoints/medical-cases.php?role=patient`. Feedback is at `http://localhost/CHN/test-endpoints/feedback.php?role=patient`. Sidebar and dashboard targets now point to these routes. Old root URLs intentionally no longer serve active pages.

`include/prototype-role.php` selects patient, doctor, staff or admin from GET only; invalid/missing values default to patient. Sessions store CSRF and submission tokens, never the active prototype role. Query roles do not authenticate users or authorize production reads/writes.

Creation requires a separate Case Title (1–160 Unicode characters) and detailed Case Description (nonempty, at most 65,535 UTF-8 bytes). Both are escaped on display. Legacy cases without titles show “Title not recorded”. Patient identity is fixed on the local server. Doctors resolve an existing patient through `user.national_id` joined to `patient.user_id`; missing, malformed, unknown and multiple matches fail. National IDs are not rendered back into HTML. No patient accounts are created.

Department/doctor choices come from database relationships within the fictional hospital. Changing department resets doctor selection. Patients must select a doctor; departments with no doctors cannot be submitted. Doctor-created cases automatically use the server-verified doctor and that doctor’s department; submitted assignment IDs are ignored. The backend rechecks department availability, fictional patient eligibility and doctor membership. All new cases use literal green/open defaults. Patients cannot submit clinical triage. Patients can open or close only their own cases using a separate CSRF/nonce-protected status action. Status changes do not affect triage. The former review-before-creation workflow has been removed.

## Proposed schema migration

`db/migrations/20261116_case_title.sql` adds nullable VARCHAR(160) case_title, preserving legacy rows without inventing titles. It contains no database selector or data rewrite. Team approval is required before applying it to a shared/real database; `db/schema.sql` is unchanged. No changes were made to `chn_db`.

Fresh isolated setup explicitly applies the migration to `chn_case_creation_dev` after importing the canonical schema. Existing development databases need this migration separately; setup refuses existing databases. Never copy real data into this environment.

## Isolated development server

```sh
CHN_CASE_DEV_MODE=1 /Applications/XAMPP/xamppfiles/bin/php test-endpoints/setup-case-dev.php
mkdir -p /private/tmp/chn-case-dev-sessions
CHN_CASE_DEV_MODE=1 /Applications/XAMPP/xamppfiles/bin/php \
  -d session.save_path=/private/tmp/chn-case-dev-sessions \
  -d display_errors=0 -S 127.0.0.1:8776 -t "$PWD"
```

Open `http://127.0.0.1:8776/test-endpoints/medical-cases.php?role=patient` or `?role=doctor`. Fictional National IDs seeded for testing are `DEMO-901` and `DEMO-902`; use no real identifiers. Setup seeds five fictional users, patients 501/502, doctors 601/602, staff 701, two departments and one hospital.

The fixed server doctor capability defaults to doctor 601. Triage updates are restricted to this doctor's department and assigned/unassigned cases. Set server-only `CHN_CASE_DEV_ACTOR=staff` to test the explicitly provisioned staff 701 capability; its verified healthcare_staff account and department/hospital assignment are checked in SQL. The UI role must match that server capability for clinical mutations. Changing URL roles cannot grant it. Staff receives routing metadata only, with no descriptions or diagnoses. Admin has no clinical permissions.

Writes require built-in PHP server, explicit dev mode, loopback client/Host, same-origin checks, CSRF, expiring purpose-bound nonce, prepared statements and scoped SQL. Creates use transactions; replay redirects to the saved case. The database is fixed to `chn_case_creation_dev`; server-only connection overrides are CHN_CASE_DEV_DB_SOCKET/USER/PASSWORD. The library rejects direct HTTP access. Normal Apache preview cannot write.

```sh
CHN_CASE_DEV_MODE=1 /Applications/XAMPP/xamppfiles/bin/php test-endpoints/test-case-creation.php
CHN_CASE_DEV_MODE=1 /Applications/XAMPP/xamppfiles/bin/php test-endpoints/test-case-relations.php
```

The HTTP suite needs Python 3 and the server at port 8776 with default doctor capability. It leaves fictional cases for review. The patient test entry point delegates to the same current suite. The relation test temporarily inserts synthetic diagnoses and alters one fictional National ID, restoring them in finally. It must run without concurrent fixture edits.

Production dependencies remain: verified authentication, patient ownership, doctor assignment/staff hospital and department policy, approved staff clinical capabilities, audited connection configuration and approved migration. This local adapter is not production authorization. Feedback remains a non-persisting preview. Diagnosis history remains under each selected patient/doctor case, ordered newest first and scoped by case_id.

## Verification for this update

- 79 HTTP assertions passed per run (two runs); fictional cases 1–4 persisted in the newly created isolated database. New cases were verified green/open before later triage updates.
- Explicit staff server capability updated case 1 to orange; a mismatched doctor UI role was denied. A staff URL on the default doctor server was denied.
- Diagnosis relation/newest-first ordering, staff diagnosis isolation and ambiguous National ID rejection passed. Synthetic diagnosis rows were removed and the test identifier restored.
- `test-endpoints/test-case-browser.html` passed at 393px and 1440px for both pages: no document overflow, doctor option reset/filtering, empty selection handling and Feedback draft validation. Serve it through the local development server to rerun.
- XAMPP rendered both relocated pages for all four roles plus invalid role; both former root URLs returned 404.
- PHP syntax passed for all 26 PHP files; `git diff --check` passed.

The migration was applied only by fresh isolated setup, not to chn_db. These results establish local fictional integration only; the production dependencies above remain unresolved.

## Patient status and assignment update

Patient creation now requires a department-matched doctor on both server and form. Doctor creation ignores submitted doctor/department IDs and assigns the server-verified doctor in their own department. The patient detail view offers open/closed status updates, restricted in SQL to the server-resolved patient's own case; other roles cannot invoke that action. CSRF, purpose-bound nonce and replay protections apply. Triage remains separate.

`db/migrations/20261116_patient_case_status.sql` is a second proposed migration requiring team review for shared databases. The existing trigger required a closing doctor. The proposal adds `closed_by_patient_id` and updates the closure trigger to allow one doctor or the case's own patient as closure actor, while retaining active-treatment checks and clearing closure metadata on reopening. Doctor attribution is never fabricated for a patient action. Fresh isolated setup applies both proposals. Only the isolated development database has been migrated; chn_db remains untouched.

The updated HTTP suite passed 99 checks, including missing doctor rejection, forged assignment replacement, patient close/reopen, invalid status, CSRF, other-patient denial and status replay. Fictional cases 7/8 were created by this successful run (an earlier diagnostic run created 5/6).

Final repeat after the audit-preservation fix: 99 HTTP checks passed, creating fictional cases 9/10. Browser checks passed again at 393px/1440px. A temporary synthetic treatment verified closure denial with active treatment; after its removal, patient closure attribution (`closed_by_patient_id=501`, doctor attribution NULL) and clearing metadata on reopening passed. Temporary treatment data was removed.

## Doctor status and inline triage editor

Doctors can now open/close cases within the same assigned/unassigned department scope as their local reader and triage capability. The server verifies its fixed doctor actor independently of the URL role; staff/admin/patient cannot invoke the doctor status action. Closure uses closed_by_doctor_id, clears patient attribution, and preserves the active-treatment restriction. Reopening clears closure metadata; repeating a same-state request does not rewrite attribution. No additional migration is needed beyond the previously proposed patient closure migration.

In the selected case details, the triage value and pencil form a keyboard-accessible native details/summary control. Opening it reveals green/yellow/orange selection and a save button. It works without JavaScript. Saving is disabled in the standard fixture preview. Existing CSRF and purpose-bound replay protections remain in place.

Verification: 118 HTTP checks passed, including doctor close/reopen, replay, invalid state, CSRF, other-role denial and out-of-scope case denial. The successful run created cases 14/15 plus one explicitly fictional out-of-scope case. Browser tests passed at 393px/1440px, including opening the pencil editor from a clinical detail link and finding the doctor status form, with no overflow. A temporary synthetic treatment verified active-treatment closure denial, correct doctor attribution (601, patient attribution NULL), and reopening cleanup; the temporary treatment was deleted. All persistence was confined to chn_case_creation_dev.

Patient and doctor status editors now sit directly inside the Case status metadata field, matching the triage pencil interaction. The separate status section has been removed. Native details/summary keeps the editor collapsed until activated, except validation errors reopen it.
