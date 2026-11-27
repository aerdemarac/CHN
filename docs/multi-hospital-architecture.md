# Island-Wide Multi-Hospital Architecture

This document summarizes the multi-hospital structure across the database, backend, and frontend (`#62`).

---

## 1. Database (`db/schema.sql` & `db/seed.sql`)
- **`hospital`**: Stores verified public hospitals (`hospital_type = 'public'`), `city`, `address`, and `phone`.
- **`department`**: `hospital_id INT NOT NULL` — every department strictly belongs to a public hospital, with `UNIQUE (hospital_id, department_name)` and `UNIQUE (department_id, hospital_id)`.
- **`doctor` / `staff`**:
  - `doctor.department_id` links each doctor to a specific hospital through `department.hospital_id`.
  - `staff (hospital_id, department_id)` uses `fk_staff_department_hospital` to ensure staff department assignments belong to their assigned hospital.
- **`emergency_log`**: Includes `hospital_id INT NULL` (`fk_emergency_hospital`) so island-wide emergency calls can be routed to and audited per hospital.
- **Seed (`db/seed.sql`)**: Seeds 4 island-wide public hospitals (Lefkoşa, Gazimağusa, Girne, Güzelyurt) and 8 departments.

---

## 2. Backend Scoping & APIs
- **Shared Scope Helper (`backend/auth.php` → `chnGetUserHospitalScope(PDO $pdo, array $sessionUser)`):**
  - `admin` & `patient`: `is_island_wide = true` (patients can choose any public hospital; admins oversee all hospitals).
  - `doctor`: scoped to their `department.hospital_id` and `doctor.department_id`.
  - `healthcare_staff`: scoped to their `staff.hospital_id` and `staff.department_id`.
- **Cascading Lookup (`backend/get_hospitals.php`):**
  - `GET` JSON endpoint returning public hospitals → departments → doctors, automatically scoped when called by a hospital-bound doctor or staff member.
- **Admin Hospital & Department Management:**
  - `backend/create_hospital.php` (`POST` JSON, `public` only)
  - `backend/update_hospital.php` (`POST` JSON)
  - `backend/create_department.php` (`POST` JSON)
  - `backend/delete_department.php` (`POST` JSON, returns `409 DEPARTMENT_IN_USE` if linked to doctors/cases/appointments/staff)

---

## 3. Frontend (`pages/hospitals.php`)
- Admin page protected by `requireRole(['admin'], false)` and linked in `include/sidebar-menu-manager.php` (`Hospitals`).
- Supports creating/editing public hospitals, adding/deleting departments per hospital, and viewing staff/doctor/case counts across the island.
