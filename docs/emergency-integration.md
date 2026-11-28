# Emergency Request & Audit — Backend & Frontend Integration

This document describes the live Emergency Request (`pages/emergency-call.php`) and Emergency Audit/Alerts (`pages/emergency-audit.php`) implementation across the database, backend, and frontend.

---

## 1. Database Changes (`db/schema.sql`, `db/seed.sql`)

- **`emergency_log`:**
  - Renamed `emergency_location` → `gps_location` (`VARCHAR(255) NULL`).
  - Added `manual_address` (`VARCHAR(255) NULL`) for mismatched locations, unavailable GPS, or optional detailed location notes.
  - Added `emergency_type` (`ENUM(...) NULL`) supporting 10 predefined emergency types plus `other`.
  - Added optional guest fields `anon_name` (`VARCHAR(100) NULL`), `anon_phone` (`VARCHAR(16) NULL`), and `anon_dob` (`DATE NULL`).
- **`user`:**
  - Added `is_banned` (`TINYINT(1) NOT NULL DEFAULT 0`) checked on login (`backend/login.php`) and session validation (`requireRole()` in `backend/auth.php`).

---

## 2. Last Active Account Security (`backend/auth.php` & `backend/login.php`)

- On successful login, `chnSetLastAccountCookie($userId)` sets an `HttpOnly`, `SameSite=Lax`, HMAC-SHA256-signed cookie `chn_last_acct`.
- When `pages/emergency-call.php` is opened:
  - **Authenticated user:** Loads `name`, `surname`, `phone`, `birth_date`, and `patient.full_address` directly from the database via `$_SESSION['user_id']`, and sets `emergency_log.user_id` on submission.
  - **Unauthenticated user with valid `chn_last_acct` cookie:** Verifies the HMAC signature server-side (`chnGetLastAccountUserId()`), pre-fills the optional contact inputs and saved account address (with a **Clear saved account info** button calling `backend/clear_last_account.php`), while storing `user_id = NULL` on unauthenticated submissions.

---

## 3. Four Location Scenarios (`pages/emergency-call.php`, `js/emergency.js`, `backend/create_emergency.php`)

1. **Account Address + GPS Active:**
   - Prompts the user to confirm whether GPS coordinates and the saved account address match.
   - **Match:** Submits `gps_location`, sets `manual_address = NULL`, pre-fills contact info, optional `emergency_type`.
   - **Mismatch:** Reveals an optional detailed location input (`manual_address`, saved as `NULL` if left blank), optional `emergency_type`.
2. **Account Address + GPS Inactive:**
   - Requires checking a location accuracy responsibility checkbox; stores `gps_location = NULL` and the verified account address in `manual_address`, optional `emergency_type`.
3. **No Account Address + GPS Active:**
   - Reveals an optional detailed location input (`manual_address`, saved as `NULL` if left blank), optional `emergency_type`.
4. **No Account Address + GPS Inactive:**
   - Shows a **required** manual street address input (`manual_address`), optional `emergency_type`.

All new requests insert with `hospital_id = NULL`, `handled_by_staff_id = NULL`, `handled_at = NULL`, `status = 'pending'`, and `ip_address = $_SERVER['REMOTE_ADDR']`, and send a notification to active `healthcare_staff` accounts.

---

## 4. Hospital Claiming & Audit Workflow (`pages/emergency-audit.php`, `js/emergency-demo.js`, `backend/manage_emergency.php`)

- Protected by `requireRole(['healthcare_staff', 'admin'], false)`.
- **`healthcare_staff` view (Emergency Alerts):**
  - Displays all island-wide `pending` (`hospital_id IS NULL`) alerts plus emergencies belonging to the staff member's assigned hospital (`hospital_id`).
  - **`start_handling`:** Atomically claims a `pending` alert (`SELECT ... FOR UPDATE`), attaching `hospital_id`, `handled_by_staff_id`, `handled_at = NOW()`, and `status = 'in_progress'`.
  - **`mark_resolved`:** Any staff member at the assigned hospital can mark an `in_progress` emergency `resolved`.
  - **`flag_fraud`:** Staff can flag `pending` (claiming for their hospital) or their hospital's `in_progress` emergencies as `fraud`.
- **`admin` view (Emergency Logs):**
  - Displays all emergency logs island-wide, IP addresses, fraud status, and supports `flag_fraud` and `ban_user` (`user.is_banned = 1`).
