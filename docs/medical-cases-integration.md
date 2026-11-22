# Medical Cases visual demo

Open `http://localhost/CHN/test-endpoints/medical-cases.php?role=patient` (or doctor/staff/admin). Feedback lives at `http://localhost/CHN/test-endpoints/feedback.php?role=patient`. Each feature has one shared page; Sidebar/dashboard links preserve prototype roles. Missing/invalid roles default to patient. URL roles are presentation only, never authentication.

The pages render fictional fixtures. There is no case API, database connection, session identity, Python backend/test runner, or persistence. The local database adapter, setup scripts, HTTP/database test endpoints and proposed executable migrations have been removed. Existing databases were not modified or deleted during this simplification. The previously started case development server has been stopped; normal XAMPP preview is sufficient.

Case creation checks required title, detailed description, patient doctor selection and (for the doctor view) a fictional National ID. It displays a demo confirmation only; it neither resolves a real patient nor creates a record. Doctor assignment is illustrated by the demo doctor context. Department/doctor options are explicitly fictional lookup fixtures, including a no-doctor department. Replacing these fixtures with authorized lookup responses is a future integration task.

Patient/doctor status pencils and clinician triage pencils update only the currently displayed DOM, including the visible table and status counts. Reloading, switching roles or applying filters restores fixture values. No fetch, POST, localStorage or cookies are used by these demo actions. Mutation buttons start disabled and are enabled by JavaScript; without JavaScript, they cannot submit. A manually submitted POST renders a “nothing saved” notice and performs no mutation. Search/filter/detail selection remains GET-based. Feedback keeps its existing draft-only validation/history preview.

## Future backend contract

Preserve the schema-aligned field names: case_id, case_title, case_description, department_id, doctor_id, case_state and tier_color. Creation intent names are patient_create/create_doctor; separate update intents are update_status/update_doctor_status/update_triage. These are form labels for future wiring, not implemented endpoints. Backend integration should replace the demo submit handler with an authenticated adapter and a documented error/success response contract; no endpoint URLs have been invented.

The future server must validate all fields and relationships independently: required title (1–160 Unicode characters), detailed description (up to 65,535 UTF-8 bytes), required patient doctor selection in the selected department, and National ID resolution through user/patient for doctor creation. Doctor identity and department must come from trusted authentication. New records start green/open. Status is open/closed; triage is green/yellow/orange. Recheck patient ownership, assigned doctor scope and approved staff permissions for every request. Enforce CSRF, replay protection and active-treatment closure rules on the backend; browser validation is presentation only.

The canonical schema currently has no case_title and requires doctor attribution on closure. The backend team must review additive title storage and accurate patient closure attribution before integration; no migrations or database changes are executed by this demo. Do not fabricate a doctor closure actor for patient actions.

List/detail DTOs should keep joined display names and diagnoses related by case_id, with diagnosis entries newest first. Apply authorization before returning any data; never fetch a broad dataset and rely on URL role/client filtering. Staff displays routing metadata only; admin clinical permissions remain undefined.

## Add Diagnosis preview

Open `pages/medical-cases.php?role=doctor` and select an assigned case from the case list. Diagnosis History offers Add Diagnosis for a case that was open when selected and remains open in the preview. Changing its status to closed hides the action and discards any draft. Cases loaded as closed stay read-only for diagnosis entry, even after a preview-only status change. The team must approve production rules for closed and reopened cases before connecting the form to a backend.

The form carries `case_id`, required `diagnosis_text` (up to 65,535 UTF-8 bytes for MySQL TEXT), and optional `icd_code` (up to 20 characters; blank maps to NULL on integration). Preview entries are added to the current page in newest-first order. They have no generated record ID or authoritative timestamp. The Russian interface follows the same input policy as English and Turkish, so Cyrillic clinical input is currently rejected.

On integration, derive `doctor_id` from the authenticated PHP session. Verify doctor role, assignment, case state, CSRF protection and field validity on the server, and prevent replayed writes. The backend generates `diagnosis_id` and `diagnosed_at`. Replace the temporary entry with the authorized server response.
