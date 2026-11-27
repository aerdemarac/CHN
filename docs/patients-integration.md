# Patients Lookup — Backend Integration & Scoping

This document describes the live Patients lookup implementation in CHN (`pages/patients.php`, `js/patients.js`, and `backend/search_patients.php`).

---

## 1. Authorization & Multi-Hospital Scoping

1. **Allowed Roles:** `doctor` and `healthcare_staff` only (`requireRole(['doctor', 'healthcare_staff'], false)` on `pages/patients.php`, `requireRole(['doctor', 'healthcare_staff'], true)` on `backend/search_patients.php`).
   - Unauthenticated users redirect to `../login.php` (HTML) or receive `401 UNAUTHORIZED` (API).
   - `patient` and `admin` users redirect to their dashboards (HTML) or receive `403 FORBIDDEN` (API).
2. **Scope Enforcement (`chnGetUserHospitalScope()`):**
   - **`doctor`:** Scoped to patients who have at least one `case` in the doctor's assigned `department_id` (within their hospital).
   - **`healthcare_staff`:** Scoped to patients who have at least one `case` in any department of their assigned `hospital_id` (and each case displays its localized `department_label`).
   - Clinicians with no assigned hospital/department receive an empty list (`[]`).
3. **Sidebar Routing:** `include/sidebar-menu-manager.php` routes `patients` to `pages/patients.php`.

---

## 2. Frontend Behavior (`pages/patients.php` + `js/patients.js`)

1. **Default Load:** On page load (and when clicking **Clear**), `js/patients.js` sends `POST` JSON `{"search_by": "name", "q": ""}` to `backend/search_patients.php` so all associated patients in the clinician's scope are displayed immediately.
2. **Search Modes:**
   - `name` *(first & default selected)*: Validates English/Turkish Latin letters (`A-Za-zçÇğĞıİöÖşŞüÜ`) on `input` & `paste` without stripping characters (2–100 chars).
   - `phone`: 7–15 digits with optional `+`, spaces, parentheses, or hyphens.
   - `email`: Valid email format up to 254 chars.
   - `national_id`: 5–50 alphanumeric/hyphen/space characters.
3. **Safe Rendering & i18n:**
   - Renders patient cards using `document.createElement` and `textContent` (no `innerHTML`).
   - Resolves pluralized `patients.found` and `patients.age_born` strings from `chnLookup()` and department labels from `chnDepartmentLabel()`.

---

## 3. Backend Endpoint (`backend/search_patients.php`)

- **Method:** `POST` JSON (`chnRequireJsonPost()`, `credentials: 'same-origin'`)
- **Request Payload:** `{"search_by": "name|phone|email|national_id", "q": "..."}` (`q` may be empty `""` to list all scoped patients).
- **Query:** Joins `patient → user (role = 'patient') → case → department → hospital`, ordered by `u.surname ASC, u.name ASC, c.case_id DESC`, and groups cases per `patient_id`.
- **Responses:**
  - `200 OK`: `{"ok": true, "data": {"patients": [...], "scope": {...}}, "message": ""}`
  - `400 INVALID_INPUT`: Unsupported characters in `name` search.
  - `422 INVALID_INPUT`: Invalid `search_by`, `phone`, `email`, or `national_id` format.
  - `401 UNAUTHORIZED` / `403 FORBIDDEN` / `405 METHOD_NOT_ALLOWED` / `500 SERVER_ERROR`.

---

## 4. Localization Files
- `lang/en/patients.php`
- `lang/tr/patients.php`
- `lang/ru/patients.php`
