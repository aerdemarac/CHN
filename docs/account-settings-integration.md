# Account Settings — Backend Integration & Role Permissions

This document describes the live Account Settings implementation in CHN (`pages/account-settings.php`, `js/account-settings.js`, `backend/update_account_settings.php`, and `backend/change_password.php`).

---

## 1. Authentication & Page Loading (`pages/account-settings.php`)

1. The page requires an active authenticated session via `requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], false)` (`backend/auth.php`).
   - Unauthenticated requests are redirected to `../login.php`.
2. User details are loaded directly from MySQL using `$_SESSION['user_id']`:
   - Queries `user` (`user_id`, `email`, `role`, `name`, `surname`, `gender`, `birth_date`, `nationality`, `phone`) with a `LEFT JOIN patient` for `patient.full_address`.
   - If the user record no longer exists, `chnDestroySession()` is called and the browser is redirected to `../login.php`.
3. The top bar and sidebar receive the verified `$chnUserName` (`name + surname`) and `$chnUserRole` from the database record.
4. Assets (`account-settings.css`, `account-settings.js`, `topbar.js`, `sidebar.js`, `style.css`) are loaded with `?v=filemtime(...)` cache-busting.

---

## 2. Role-Based Field Permissions

Field editability is enforced in the UI **and** independently on the server in `backend/update_account_settings.php`:

| Field | `patient` | `doctor` / `healthcare_staff` | `admin` | Storage |
| :--- | :---: | :---: | :---: | :--- |
| `phone` | Editable | Editable | Editable | `user.phone` (`VARCHAR(16)`) |
| `preferred_language` | Editable | Editable | Editable | `chn_lang` cookie + `$_SESSION['lang']` |
| `full_address` | Editable | Not shown | Not shown | `patient.full_address` (`VARCHAR(255)`) |
| `name`, `surname`, `email` | Read-only | Read-only | Editable | `user.name`, `user.surname`, `user.email` |
| `birth_date`, `nationality`, `gender` | Read-only | Read-only | Editable | `user.birth_date`, `user.nationality`, `user.gender` |
| Password (`new_password`) | Editable | Editable | Editable | `user.password` (`password_hash()`) |

---

## 3. Frontend Behavior (`js/account-settings.js`)

1. **Character Set & Input Validation:**
   - Validates editable fields on `input`, `paste`, and `blur` without silently stripping unsupported characters.
   - Enforces English/Turkish Latin letters (`A-Za-zçÇğĞıİöÖşŞüÜ`) for `name` / `surname`, allowed address characters for `full_address`, international phone format (`+` followed by 7–15 digits), 2-letter `nationality`, and non-future `birth_date`.
2. **Dirty State & Unsaved Changes:**
   - Tracks changes against the initial field snapshot and warns via `beforeunload` if the user attempts to leave with unsaved changes.
3. **Profile Save (`fetch` + JSON):**
   - Sends `POST` JSON to `backend/update_account_settings.php` with `credentials: 'same-origin'`.
   - On success (`ok: true`), updates the normalized phone value, resets the dirty-state snapshot, displays the localized success message, and reloads the page if `language_changed` is `true` so the new UI language takes effect immediately.
4. **Password Modal (`#password-dialog`):**
   - Available to **all authenticated roles** without requiring current-password verification.
   - Validates `new_password` (minimum 12 characters, containing at least one letter and one digit) and `password_confirm` match.
   - Sends `POST` JSON to `backend/change_password.php` with `credentials: 'same-origin'` and clears sensitive inputs on close, success, `Escape`, and `pagehide`.

---

## 4. Backend Endpoints

### A. `backend/update_account_settings.php`
- **Auth:** `requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], true)`
- **Method:** `POST` JSON (`chnRequireJsonPost()`)
- **Validation (`backend/validation.php`):**
  - `phone`: optional; if non-empty, validated with `chnIsValidPhone()` and normalized via `chnNormalizePhone()`.
  - `preferred_language`: optional; must be one of `en`, `tr`, `ru`.
  - `full_address` (`patient` only): optional; max 255 characters, restricted to `CHN_ALLOWED_LETTERS`, digits, spaces, and `, . - / : # ' ()`.
  - `admin`-only fields: `name`/`surname` (`chnIsValidName()`), `email` (`chnIsValidEmail()` + uniqueness check excluding current `user_id`), `birth_date` (`chnIsValidBirthDate()`), `nationality` (`/^[A-Z]{2}$/`), `gender` (`CHN_GENDERS`).
- **Database Transaction:** Updates `user` (and `patient.full_address` for `patient`, inserting the `patient` row if missing) inside `$pdo->beginTransaction()` / `commit()`.
- **Session & Cookie Sync:** Updates `$_SESSION['name']` / `$_SESSION['surname']` (for `admin`) and updates `chn_lang` cookie + `$_SESSION['lang']` when `preferred_language` is provided.
- **Responses:**
  - `200 OK`: `{"ok": true, "data": {"phone": "...", "full_address": "...", "preferred_language": "...", "language_changed": bool}, "message": "Account settings updated."}`
  - `400 INVALID_INPUT` / `422 INVALID_INPUT`: Validation failure.
  - `409 EMAIL_EXISTS`: Admin attempted to use an email already registered to another user.
  - `401 UNAUTHORIZED` / `405 METHOD_NOT_ALLOWED` / `500 SERVER_ERROR`.

### B. `backend/change_password.php`
- **Auth:** `requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], true)`
- **Method:** `POST` JSON (`chnRequireJsonPost()`)
- **Payload:** `{"new_password": "...", "password_confirm": "..."}`
- **Validation:**
  - `new_password` must be at least 12 characters and pass `chnIsValidPassword()` (letter + digit, max 255 chars, no control characters).
  - `password_confirm` must exactly match `new_password`.
- **Persistence & Security:**
  - Hashes password with `password_hash($newPassword, PASSWORD_DEFAULT)` and updates `user.password`.
  - Calls `session_regenerate_id(true)`.
- **Responses:**
  - `200 OK`: `{"ok": true, "data": {}, "message": "Password updated."}`
  - `422 INVALID_PASSWORD` / `422 PASSWORD_MISMATCH`
  - `401 UNAUTHORIZED` / `405 METHOD_NOT_ALLOWED` / `500 SERVER_ERROR`.

---

## 5. Localization Files
Account settings UI and validation strings are maintained in:
- `lang/en/account.php`
- `lang/tr/account.php`
- `lang/ru/account.php`
