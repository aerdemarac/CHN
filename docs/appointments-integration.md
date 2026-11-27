# Appointments — Backend Integration, Multi-Hospital Scoping & Scheduling Rules

This document describes the live Appointments implementation in CHN (`pages/appointments.php`, `js/appointments.js`, `backend/get_appointment_slots.php`, `backend/book_appointment.php`, and `backend/manage_appointment.php`).

---

## 1. Authorization & Multi-Hospital Scoping

1. **Page Access (`pages/appointments.php`):**
   - Protected by `requireRole(['patient', 'doctor', 'healthcare_staff', 'admin'], false)`.
   - Unauthenticated users are redirected to `../login.php`.
   - Route registered in `include/sidebar-menu-manager.php` as `pages/appointments.php`.
2. **Role Scope (`chnGetUserHospitalScope()`):**
   - **`patient`:** Sees only their own appointments (`p.user_id = $userId`) across all public hospitals. Can book new appointments on their `open` cases, reschedule upcoming `confirmed` appointments, and cancel upcoming `confirmed` appointments (subject to the 2-hour rule).
   - **`doctor`:** Sees only appointments assigned to their `doctor_id` (`d.user_id = $userId`). Can complete `confirmed` visits (scheduled for today or earlier), cancel upcoming `confirmed` visits, and jump to **Add diagnosis** (`medical-cases.php?case=<id>#add-diagnosis`) on `completed` visits.
   - **`healthcare_staff`:** Read-only view scoped to their assigned `hospital_id` (`dep.hospital_id`). Includes department (`chnDepartmentLabel()`), status, patient name, and date-range filters.
   - **`admin`:** Displays the administrative notice card (no scheduling actions).
3. **Appointment Lifecycle (`db/schema.sql`):**
   - `appointment_status` uses `ENUM('confirmed','cancelled','completed') NOT NULL DEFAULT 'confirmed'`.
   - New bookings and rescheduled appointments are immediately `confirmed` (since slot availability is validated atomically at write time).

---

## 2. Backend Endpoints

All endpoints enforce `requireRole(..., true)` and PDO prepared statements. Write endpoints use `chnRequireJsonPost()` and transactions with `FOR UPDATE` locking.

### A. `backend/get_appointment_slots.php` (`GET`)
- **Roles:** `patient`
- **Query Params:** `doctor_id`, `date` (`YYYY-MM-DD`), optional `exclude_appointment_id` (used while rescheduling).
- **Behavior:** Returns `taken_slots` (`HH:MM`) for the given doctor and date, excluding `cancelled` rows and `exclude_appointment_id`.
- **Responses:** `200 OK` (`{"ok": true, "data": {"taken_slots": [...]}}`), `422 INVALID_INPUT`.

### B. `backend/book_appointment.php` (`POST` + JSON)
- **Roles:** `patient`
- **Payload:** `case_id`, `doctor_id`, `appointment_date`, `appointment_time` (`HH:MM`), optional `appointment_id` (reschedule mode).
- **Behavior:**
  - Verifies the `case` belongs to the patient and is `open` (`409 CASE_CLOSED` if closed).
  - Verifies `(doctor_id, department_id)` matches `case.department_id`.
  - Enforces weekday scheduling from tomorrow through `+60 days` and valid half-hour clinic slots (`09:00`–`11:30`, `13:00`–`15:30`).
  - Checks for double-booking using `SELECT ... FOR UPDATE` excluding `cancelled` rows (`409 SLOT_TAKEN`).
  - Inserts or updates the appointment with `appointment_status = 'confirmed'`.
- **Responses:** `201 Created` (new booking), `200 OK` (rescheduled), `404`, `409 SLOT_TAKEN` / `CASE_CLOSED` / `INVALID_STATE`, `422 INVALID_INPUT`.

### C. `backend/manage_appointment.php` (`POST` + JSON)
- **Roles:** `patient` (`cancel`), `doctor` (`cancel`, `complete`)
- **Payload:** `action` (`cancel` | `complete`), `appointment_id`.
- **Behavior:**
  - **`patient` `cancel`:** Requires `appointment_status === 'confirmed'` and at least 2 hours (`7200` seconds) remaining before the scheduled date/time (`409 TOO_LATE_TO_CANCEL`). Performs a hard `DELETE FROM appointment` to immediately free the slot.
  - **`doctor` `cancel`:** Requires `appointment_status === 'confirmed'`. Updates `appointment_status = 'cancelled'` (preserving the row so it appears in the **Cancelled** tab).
  - **`doctor` `complete`:** Requires `appointment_status === 'confirmed'`. Updates `appointment_status = 'completed'`.
- **Responses:** `200 OK`, `403 FORBIDDEN`, `404 APPOINTMENT_NOT_FOUND`, `409 TOO_LATE_TO_CANCEL` / `INVALID_STATE`, `422 INVALID_INPUT`.

---

## 3. Frontend & Localization (`pages/appointments.php`, `js/appointments.js`)
- Live slot availability is fetched from `backend/get_appointment_slots.php` whenever the patient selects a doctor or calendar date, disabling taken slots in real time.
- Booking, rescheduling, cancelling, and completing visits send `fetch()` requests with `credentials: 'same-origin'` and persist feedback banners across page reloads via `sessionStorage` (`chn_appointments_flash`).
- Localization files: `lang/en/appointments.php`, `lang/tr/appointments.php`, `lang/ru/appointments.php`.
