# Medical Cases — Backend Integration, Multi-Hospital Scoping & Clinical Workflows

This document describes the live Medical Cases implementation in CHN (`pages/medical-cases.php`, `js/medical-cases.js`, `backend/create_case.php`, `backend/update_case.php`, `backend/manage_treatment.php`, and `backend/add_diagnosis.php`).

---

## 1. Authorization & Multi-Hospital Scoping

1. **Page Access (`pages/medical-cases.php`):**
   - Protected by `requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], false)`.
   - Unauthenticated users are redirected to `../login.php`.
2. **Role Scope (`chnGetUserHospitalScope()`):**
   - **`patient`:** Sees only their own cases (`p.user_id = $userId`) across all public hospitals, with a Hospital filter and cascading **Hospital → Department → Doctor** selectors when opening a new case.
   - **`doctor`:** Scoped to cases in their assigned `department_id` (within their hospital). Can create cases (resolving patient by `national_id`), open/close cases, update triage, add/complete treatments, and add diagnoses on open cases.
   - **`healthcare_staff`:** Scoped to cases in their assigned `hospital_id` (`dep.hospital_id`). Can view case routing, descriptions, appointments, treatments, and diagnoses, and update medical triage (`green`, `yellow`, `orange`).
   - **`admin`:** Displays the administrative notice card (no clinical case actions).
3. **Standardized Departments:** All department labels in tables, filters, dropdowns, and appointment histories are localized through `chnDepartmentLabel()` (`include/departments.php`).

---

## 2. Backend Endpoints (`POST` + JSON)

All endpoints enforce `requireRole(..., true)`, `chnRequireJsonPost()`, PDO prepared statements, and `CHN_ALLOWED_LETTERS` character validation.

### A. `backend/create_case.php`
- **Roles:** `patient`, `doctor`
- **Behavior:**
  - `patient`: Validates `hospital_id`, `department_id`, `doctor_id` (verifying `doctor` belongs to `department` and `department` belongs to `hospital`) and `case_description`.
  - `doctor`: Resolves `patient_id` from `user.national_id` (`role = 'patient'`) and auto-assigns the doctor's `doctor_id` and `department_id`.
  - Inserts into `case` with `case_state = 'open'` and `tier_color = 'green'`.
- **Responses:** `201 Created` (`{"ok": true, "data": {"case_id": ...}}`), `400`/`422 INVALID_INPUT`, `404 PATIENT_NOT_FOUND`, `403 FORBIDDEN`.

### B. `backend/update_case.php`
- **Roles:** `doctor` (`update_status`, `update_triage`), `healthcare_staff` (`update_triage` only)
- **Behavior:**
  - Enforces `department_id` scope for `doctor` and `hospital_id` scope for `healthcare_staff`.
  - `update_status`: Sets `closed_by_doctor_id` and `closed_at = NOW()` when closing (`closed`), or clears them when reopening (`open`).
  - Catches SQL trigger `tr_case_bu` (`SQLSTATE 45000`) when active treatments exist and returns `409 ACTIVE_TREATMENTS`.
- **Responses:** `200 OK`, `403 FORBIDDEN`, `404 CASE_NOT_FOUND`, `409 ACTIVE_TREATMENTS`, `422 INVALID_INPUT`.

### C. `backend/manage_treatment.php`
- **Roles:** `doctor` only (scoped to `case.department_id`)
- **Actions:**
  - `add`: Adds an `active` treatment (`treatment_desc` up to 255 chars) to an `open` case (`201 Created`).
  - `deactivate`: Marks an active treatment `inactive` with `deactivated_by_doctor_id` and `completed_at = NOW()` (`200 OK`), allowing the case to be closed.
- **Note:** `patient` and `healthcare_staff` have read-only visibility of treatments on the case detail view.

### D. `backend/add_diagnosis.php`
- **Roles:** `doctor` only (scoped to `case.department_id`)
- **Behavior:**
  - Requires `case_state === 'open'` (`409 CASE_CLOSED` if closed).
  - Validates `diagnosis_text` (up to 65,535 bytes, allowed Latin charset) and optional `icd_code` (up to 20 chars).
  - Inserts into `diagnosis` and returns `201 Created`.

---

## 3. Frontend & Localization (`pages/medical-cases.php`, `js/medical-cases.js`)
- Uses `fetch()` with `credentials: 'same-origin'` and `POST` JSON payloads for case creation, status/triage updates, treatment management, and diagnosis entry.
- **Add treatment** and **Add diagnosis** toggle buttons (`#open-treatment`, `#open-diagnosis`) are rendered only for `doctor` on `open` cases (matching database trigger rules `tr_treatment_bi`).
- Localization files: `lang/en/cases.php`, `lang/tr/cases.php`, `lang/ru/cases.php`.
