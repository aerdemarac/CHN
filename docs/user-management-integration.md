# User Management — Admin Backend & Frontend Integration

This document describes the live Admin User Management implementation in CHN (`pages/user-management.php`, `js/user-management.js`, `backend/create_user.php`, `backend/update_user.php`, and `backend/delete_user.php`).

---

## 1. Authorization & Page Loading (`pages/user-management.php`)

1. The page is restricted to `admin` users via `requireRole(['admin'], false)` (`backend/auth.php`).
   - Unauthenticated requests redirect to `../login.php`.
   - Non-admin authenticated users redirect to their own role's dashboard.
2. Live data is queried from MySQL (`chnGetPdo()`):
   - `hospital` and `department` rows populate the workplace dropdowns.
   - `user` rows are `LEFT JOIN`ed with `doctor`, `department`, and `staff` so each row displays the user's role, workplace (`department · hospital`), phone, and registration date.
3. Supports server-side role tab filtering (`?filter_role=...`) and name/email search (`?q=...`), while preserving clean URLs without demo role-switching.
4. Sidebar navigation in `include/sidebar-menu-manager.php` routes `users` to `pages/user-management.php`.

---

## 2. Frontend Behavior (`js/user-management.js`)

1. **Create Mode:**
   - Clicking **Create account** opens `#users-form` in `create` mode.
   - Admin can create any of the 4 roles (`patient`, `doctor`, `healthcare_staff`, `admin`).
   - Hospital and Department dropdowns are shown and required only when `user_role` is `doctor` or `healthcare_staff` (departments dynamically filter by selected hospital).
   - Password and confirmation are required.
2. **Edit Mode:**
   - Clicking **Edit** on any user row populates `#users-form` in `edit` mode with that user's current values (`first_name`, `last_name`, `email`, `phone`, `birth_date`, `gender`, `nationality`, `national_id`, and `hospital_id`/`department_id` for doctors/staff).
   - `user_role` is **read-only** (`disabled`) so existing role-table foreign keys remain intact.
   - Password fields are optional: leaving both blank preserves the existing password.
3. **Character & Input Validation:**
   - Enforces English/Turkish Latin letters (`A-Za-zçÇğĞıİöÖşŞüÜ`) on names (`input` and `paste` events without stripping characters), email format, international phone (`+` and 7–15 digits), birth date (`1900-01-01` to today), `gender` (`female`/`male`), nationality (`2-letter code`), and `national_id` (11-digit TC Kimlik No algorithm for `TR`, passport/document format otherwise).
4. **Delete Action:**
   - Clicking **Delete** prompts for confirmation and sends `POST` JSON to `backend/delete_user.php`. Self-deletion is disabled in the UI and rejected on the backend.

---

## 3. Backend Endpoints

All endpoints enforce `requireRole(['admin'], true)` and `chnRequireJsonPost()`, returning standardized JSON responses.

### A. `backend/create_user.php`
- **Method:** `POST` JSON
- **Allowed Roles:** `patient`, `doctor`, `healthcare_staff`, `admin`
- **Database Transaction:**
  1. Checks duplicate `email` (`409 EMAIL_EXISTS`).
  2. For `doctor` and `healthcare_staff`, verifies `(department_id, hospital_id)` exists in `department`.
  3. Inserts into `user` with `password_hash($password, PASSWORD_DEFAULT)` and `verify = 1` (immediately active).
  4. Explicitly inserts the linked role record:
     - `patient` → `INSERT INTO patient (user_id)`
     - `doctor` → `INSERT INTO doctor (department_id, user_id)`
     - `healthcare_staff` → `INSERT INTO staff (user_id, hospital_id, department_id)`
     - `admin` → `INSERT INTO admin (user_id)`
- **Responses:** `201 Created` (`{"ok": true, "data": {"user_id": ...}}`), `400`/`422 INVALID_INPUT`, `422 PASSWORD_MISMATCH`, `409 EMAIL_EXISTS`, `401`/`403`, `500 SERVER_ERROR`.

### B. `backend/update_user.php`
- **Method:** `POST` JSON
- **Scope:** Edits any existing user (`user_id`); `role` is read-only and never changed.
- **Database Transaction:**
  1. Locks and reads `user` (`FOR UPDATE`); returns `404 USER_NOT_FOUND` if missing.
  2. Checks duplicate `email` excluding `user_id` (`409 EMAIL_EXISTS`).
  3. Updates `user` fields (`email`, `name`, `surname`, `gender`, `phone`, `birth_date`, `nationality`, `national_id`, and `password` only if provided).
  4. For `doctor` / `healthcare_staff`, validates `(department_id, hospital_id)` and updates `doctor.department_id` or `staff.hospital_id, staff.department_id`.
  5. Refreshes `$_SESSION['name']` / `$_SESSION['surname']` if the admin edited their own account.
- **Responses:** `200 OK`, `400`/`422 INVALID_INPUT`, `422 PASSWORD_MISMATCH`, `404 USER_NOT_FOUND`, `409 EMAIL_EXISTS`, `409 WORKPLACE_LOCKED` (if doctor has linked cases/appointments blocking department change), `500 SERVER_ERROR`.

### C. `backend/delete_user.php`
- **Method:** `POST` JSON (`{"user_id": ...}`)
- **Rules & Error Handling:**
  - Rejects self-deletion with `400 CANNOT_DELETE_SELF`.
  - Executes `DELETE FROM user WHERE user_id = :user_id` (`ON DELETE CASCADE` removes `patient`/`doctor`/`staff`/`admin` rows when unreferenced).
  - Catches `SQLSTATE 23000` (`ON DELETE RESTRICT` from `case`, `appointment`, `feedback`, `emergency_log`, `diagnosis`, `treatment`, `api_auth`) and returns `409 USER_IN_USE`.
- **Responses:** `200 OK`, `400 CANNOT_DELETE_SELF`, `404 USER_NOT_FOUND`, `409 USER_IN_USE`, `422 INVALID_INPUT`, `500 SERVER_ERROR`.

---

## 4. Localization Files
- `lang/en/users.php`
- `lang/tr/users.php`
- `lang/ru/users.php`
