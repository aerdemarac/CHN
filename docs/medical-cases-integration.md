# Medical Cases integration

`medical-cases.php` remains one shared page. Its default mode uses explicitly fictional fixtures from `include/medical-cases-preview-data.php`. The optional local-only database mode below supports actual MySQL case creation and reads. There is still no trusted production authentication contract or team-provided case API in this checkout; README backend filenames describe planned files. No real medical records are accessed and there is no separate diagnosis page.

The schema defines `patient`, `doctor`, `staff`, `admin`. The shared menu manager uses `staff` as its canonical key, with `healthcare_staff` retained only as a legacy caller alias. The prototype URL accepts only `patient`, `doctor`, `staff`, `admin`; the legacy alias in a URL is invalid and defaults to `patient`. The top bar role remains a display value, not authorization. Existing role menu order and file-existence “Soon” behavior are preserved. Admin access must be specified by the backend team, not inferred.

Before enabling live records, replace the fixture loading with the team's documented authenticated read interface and remove the preview labels/DEMO prefix. Do not expose an unrestricted dataset and filter it in the browser. Enforce patient ownership through patient.user_id; enforce the backend team's authorized doctor assignment and staff hospital/department scope on both list and individual case/diagnosis requests. Fail closed for unauthenticated, unsupported-role and unassigned accounts. Recheck every supplied case ID; GET parameters are untrusted. Do not infer broader departmental access for doctors or any admin clinical access from role labels. Counts and department options must come only from the authorized scope. Specify pagination and count semantics with the backend if lists are paginated.

The current view uses schema fields case_id, department_id, case_description, case_state, tier_color, created_at and joined department_name/doctor_name display fields. `open` is labeled Active; `closed` is Closed. Triage remains orange/yellow/green and does not determine case state. Orange/yellow use the design palette's shared warning color with distinct shapes and explicit text labels.

Diagnosis history accepts zero or more records with diagnosis_id, case_id, diagnosis_text, diagnosed_at, optional icd_code and optional joined doctor_name. Join diagnosis.case_id to case.case_id and diagnosis.doctor_id to doctor/user independently of the case's responsible doctor. Render newest first; escape all output. Missing diagnoses, doctor, ICD and case description are supported. Agree date/time timezone semantics with the backend (schema DATETIME has no timezone). Production mutation actions remain disabled; local-only case creation is described below.

Filters use GET q/status/department; selection uses GET case and preserves the active filters. Selection is resolved only within the current results; changing filters clears selection. Case selection and filters work without JavaScript. The optional creation script only disables submission buttons while submitting; server validation and nonce checks enforce correctness.

## Role-based shared page prototype (rule 9)

One `medical-cases.php` serves all four roles. `include/prototype-role.php` is explicitly included by this page and the existing Sidebar demo. It reads only GET `role`; absent, invalid, array, and legacy `healthcare_staff` inputs default to `patient`. No session or cookie determines the active prototype role. Other pages are not automatically opted into this behavior; live authorization is unchanged.

The role selector intentionally clears case selection and filters. All Medical Cases internal links, reset links, case selection URLs and GET form submissions retain the canonical role. Shared Sidebar and Top Bar internal URLs retain it when the caller has opted into the prototype helper. Existing query parameters/fragments are preserved by the URL helper. There is no pagination in this small fixture list; any future pagination must use the same helper. Appointments and Medical Records are not implemented in this checkout and remain Soon.

Fixture-only scopes: patient 501 sees their cases; doctor 601 sees assigned cases with fictional patient references and clinical information; staff sees operational metadata in demonstration department 1 without clinical descriptions or diagnoses. These are UI illustrations, not approved access policy. Scope filtering precedes summaries, department options, search and selection. Admin sees a permissions-not-defined state with no records or clinical actions. Default previews remain read-only. No production medical mutation permissions are introduced. Diagnosis history stays under the selected case for patient/doctor views and remains empty until records exist; no diagnoses are fabricated.

Future backend integration must replace URL role selection and fixture scopes with verified identity and server-enforced policy. Never connect URL-selected roles to real medical records.


## Functional creation in an isolated local database

Because no trusted backend authentication or database interface is available, writes deliberately do **not** run through normal XAMPP/Apache. `backend/medical-cases-dev.php` is a library, not a public API; direct HTTP access is rejected. The write handler lives in the shared page and fails closed unless all of these conditions hold: PHP built-in development server (`cli-server`), explicit server environment `CHN_CASE_DEV_MODE=1`, loopback client address, and a loopback Host header. Nonmatching requests cannot create cases even with `?role=doctor`. The database name is fixed to `chn_case_creation_dev`; no request can choose it or connection credentials. Use fictional information only and never copy production records into this database.

The server fixes the development actor to doctor 601 and checks their linked database user role, fictional email, department and hospital assignment. Patient choices are restricted to fictional patients 501/502 with their expected fixture identities; the department must match doctor 601. Responsible doctor is optional, otherwise only doctor 601 is allowed, and doctor/department membership is checked in PHP and by the existing composite FK. The local doctor's reader includes their assigned cases plus unassigned cases in that same department so a saved unassigned case remains visible. This local test scope is not an approved production permission rule. Staff/admin creation is disabled. Patient creation is available through the clinician-reviewed intake sequence below. URL role is only an additional UI restriction; it cannot establish the fixed server capability.

Setup a **new** isolated database (the setup refuses an existing one):

```sh
cd /Applications/XAMPP/xamppfiles/htdocs/CHN
CHN_CASE_DEV_MODE=1 /Applications/XAMPP/xamppfiles/bin/php test-endpoints/setup-case-dev.php
mkdir -p /private/tmp/chn-case-dev-sessions
CHN_CASE_DEV_MODE=1 /Applications/XAMPP/xamppfiles/bin/php \
  -d session.save_path=/private/tmp/chn-case-dev-sessions \
  -d display_errors=0 -S 127.0.0.1:8776 -t "$PWD"
```

Then open `http://127.0.0.1:8776/medical-cases.php?role=doctor`. The page clearly identifies database-backed fictional records with DEV IDs. The regular `http://localhost/CHN/medical-cases.php?role=doctor` remains the non-writing fixture preview. Setup uses the unmodified canonical schema/tables/triggers, changing only the target database name at import time. It seeds 4 fictional users with unknown random password hashes, 2 patients, 2 doctors, 2 departments and 1 explicitly fictional test hospital. It seeds no cases or diagnoses and never alters `chn_db`. No login system is added.

Connection variables are server-only `CHN_CASE_DEV_DB_SOCKET`, `CHN_CASE_DEV_DB_USER`, `CHN_CASE_DEV_DB_PASSWORD`. The development defaults match local XAMPP's Unix socket and root/empty-password setup; use a limited database account for any shared development environment (SELECT on the dev lookup tables and INSERT on its case table). Do not put credentials in URLs or version control. No `.env` or unrelated mailer configuration is loaded.

Creates use native PDO prepared statements in a transaction, validated integer IDs, explicit triage allowlist, optional UTF-8 TEXT description capped at 65,535 bytes, and literal `case_state='open'`. The database generates case_id/created_at. Arrays, malformed IDs, unavailable choices, wrong departments and invalid triage are rejected with field errors. PDO details and record contents are not exposed in errors. Failed database reads do not substitute fixture records.

A separate CHN_CASE_DEV session stores CSRF, expiring purpose-bound submission tokens and local fictional intake/review drafts, never the active URL role or login state. Same-session POSTs are serialized; replay of a saved token redirects to the same case instead of inserting another. Validation failures preserve fields. Successful commits use a 303 redirect, clear old filters to reveal the new record, and display a one-time confirmation associated with that saved ID. Refreshing does not resubmit. Tokens expire after 30 minutes; at most 20 forms are retained. This is per-form duplicate protection, not a production database idempotency ledger across lost sessions/server crashes. Origin checks complement CSRF; enabling JavaScript is not required to save.

Run the CLI integration test while that local server is running:

```sh
CHN_CASE_DEV_MODE=1 /Applications/XAMPP/xamppfiles/bin/php test-endpoints/test-case-creation.php
```

Each test run persists three fictional cases and prints their IDs; it also inserts two explicitly synthetic diagnosis relation test rows and removes them in `finally`. It verifies real FK enforcement, validation, URL role denial, forged identities, CSRF/origin/Host checks, duplicate POSTs, saved detail/refresh, patient scope and diagnosis isolation/order. Do not run it against live data. Setup and test scripts return 403 over HTTP.

Production dependency: the backend team must supply a verified identity/authorization interface, doctor patient/department assignment policy, audited connection configuration and approved read/write scopes before any real database is connected. Replace this local-only adapter with that interface; do not loosen the gate or use `role`/POST IDs as authorization. Staff creation remains disabled until explicitly authorized; admin gains no clinical permissions.

### Verification performed for this change

- PHP syntax and whitespace checks passed for all changed/new files.
- CLI HTTP/MySQL integration: 119 assertions passed, creating cases 1, 2 and 3 (green assigned patient 501; yellow assigned patient 502; orange unassigned patient 501 with NULL description).
- Actual browser integration at 393px and 1440px: 158 checks passed, including a mobile form save that persisted case 5 (patient 501, doctor 601, yellow). The saved detail view survived a refresh. All four cases remain in the dedicated dev database, all open; no original database was changed.
- Two explicitly synthetic diagnosis rows (IDs 1/2) were temporarily inserted for case 1 to test multiple diagnoses, date ordering, optional ICD/doctor and case isolation; they were removed after the test. No diagnosis records remain.
- Additional HTTP smoke checks confirmed normal XAMPP rejects writes for every URL role, database failure returns 503 without substituting fixture data or exposing PDO details, search/status/department filters work, and Feedback remains available.
- Sidebar menus, notifications, language/profile controls, role-preserving links/forms, mobile off-canvas navigation, required form fields, optional doctor/description, table scrolling and document overflow were checked in the browser. The normal fixture-mode role regression checks also passed.


## Patient creation correction: clinician review before case creation

The project owner explicitly selected **clinician review before case creation**. `case.tier_color` is NOT NULL and accepts only orange/yellow/green, so a complaint that has not been triaged is **not** inserted as a case. No implicit green/default classification, nullable triage, pending case state, new columns or schema migration is introduced.

The Patient page now has a visible Create Case button, a department selector and a required complaint field. There are no patient-editable patient_id, user_id, doctor_id or tier_color inputs. A forged POST containing any of these fields is rejected. In the isolated test, patient identity comes from a server-fixed lookup for patient 501 / user 901 with the expected fictional email and database role. The browser's role, patient IDs and cookies are never accepted as identity evidence. Production must replace the fixed fixture lookup with trusted authenticated ownership.

Local test sequence in one browser session:

1. Open `http://127.0.0.1:8776/medical-cases.php?role=patient`. Enter a fictional complaint in the available department and choose **Request clinical review**. The draft is held temporarily in the local server session; the UI explicitly states **no case has been saved to MySQL**.
2. Use the existing demo-role selector to switch to Doctor. The shared page displays **Review patient complaint** for that exact local draft. Fictional clinician 601 must explicitly select a triage colour and approve it. Approval alone does not create a case.
3. Switch back to Patient. The assigned triage appears as read-only text; the button becomes **Save Case**. Submit the unchanged reviewed complaint. Server-held patient identity, department, clinician assignment and classification are used in the prepared INSERT. The database generates an open case ID/timestamp. The redirect keeps `role=patient`, selects the new record and the case survives refresh.
4. Changing the complaint or department invalidates the approval for saving. The backend requires a new review. An old clinician review form cannot approve a replacement draft. Submission tokens are bound to their purpose, and review tokens are additionally bound to the draft ID. Patient tokens cannot be used for doctor creation/review. Replayed saved forms redirect to their existing case without creating another.

This same-browser role-switch workflow is an explicitly local fictional demonstration, **not** a way to authenticate a real patient or clinician. Drafts are temporary, limited to one per local session and expire after 30 minutes. They are not a persistent multi-user clinical review queue. Doctor's existing direct creation form remains available and unchanged in behavior; staff/admin do not gain creation actions.

### Exact environment behavior

- `http://localhost/CHN/medical-cases.php?role=patient`: XAMPP/Apache serves the new patient button and editable complaint form. The submit button is disabled and the page explains why. Even a manual POST is rejected with 403. This page uses fixture arrays, **no database connection**, and neither reads nor writes `chn_db` or the isolated development database. Sidebar/Top Bar are unchanged.
- `http://127.0.0.1:8776/medical-cases.php?role=patient`: explicitly opted-in PHP built-in loopback test server, using XAMPP's MariaDB over its Unix socket and the fixed `chn_case_creation_dev` database. Reviewed fictional patient cases are genuinely persisted here. Ports 80 and 8776 are two server environments serving the same project files, not separate patient/doctor pages.

Remaining backend decisions/integration: verified patient and clinician identities; who may review which patient/department; an authenticated review service and durable pending-intake storage with audit history; and replay-safe production persistence. The approved sequence fits the existing case schema once triage is assigned, but the current schema has no durable untriaged intake record. The backend team must define its intake queue/storage contract (and obtain approval for any separate intake schema if needed). The temporary local session is not production intake storage. No unrestricted write capability has been added to normal XAMPP.

CLI test for the patient workflow:

```sh
CHN_CASE_DEV_MODE=1 /Applications/XAMPP/xamppfiles/bin/php test-endpoints/test-patient-case-creation.php
```

It persists one explicitly fictional patient case after clinician review and prints its ID. It verifies required/invalid inputs, no insertion before review, stale-review rejection, changed-complaint rejection, forged identity/clinical fields, role/purpose/CSRF restrictions, real persisted ownership/classification, duplicate POST protection, patient list/detail refresh and diagnosis empty state. It is CLI-only and requires the existing isolated test database/server; it does not alter the schema or configure Apache.

Patient correction verification: 61 patient integration assertions and 119 doctor/diagnosis regression assertions passed. Browser checks passed at 393px and 1440px against the actual requested XAMPP URL (154 checks) and the local server (167 checks, including the full review/save/refresh sequence). Normal XAMPP manual POSTs returned 403 for all four roles. New fictional patient cases 6 (yellow) and 11 (green) were persisted after explicit clinician selection; the doctor regression also created cases 7, 8 and 9. All are open in `chn_case_creation_dev`. Temporary synthetic diagnosis rows 3/4 used by the regression were removed; no diagnoses remain. The earlier development records were retained, and `chn_db` was untouched.
