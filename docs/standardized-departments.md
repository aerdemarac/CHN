# Standardized Clinic Departments (`lib/departments.php`)

This document explains the standardized clinic department hash-map (`#63`) and how it supports both database operations and multi-language UI rendering across CHN.

---

## 1. Architecture & Files

- **`lib/departments.php`**: Canonical key-to-name hash-map (23 standardized clinic departments, e.g. `'cardiology' => 'Cardiology'`), following the same pattern as `lib/countries.php`.
- **`include/departments.php`**: Shared PHP helpers:
  - `chnDepartmentCatalog()`: Returns the full `key => canonical_name` map.
  - `chnDepartmentKey(string $keyOrName)`: Resolves either a catalog key (`'cardiology'`) or a canonical name (`'Cardiology'`) to its canonical key, or `null` if unknown.
  - `chnDepartmentCanonicalName(string $keyOrName)`: Resolves a key or name to the exact canonical English string stored in MySQL `department.department_name`.
  - `chnDepartmentLabel(string $keyOrName)`: Returns the localized display label (`department.<key>`) for the active UI language (`en`, `tr`, `ru`), falling back to the canonical name.
- **`lang/{en,tr,ru}/departments.php`**: Translations for all 23 standardized department keys.

---

## 2. How It Helps App & Database Operations

1. **Prevents Duplicate or Dirty Department Rows:**
   - The `department` table enforces `UNIQUE (hospital_id, department_name)`. Without a canonical allowlist, the same department could be inserted multiple times with casing, spacing, or language variations (e.g. `"Cardiology"`, `"cardiology "`, `"Kardiyoloji"`).
   - `backend/create_department.php` validates incoming `department_key` (or `department_name`) through `chnDepartmentKey()` / `chnDepartmentCanonicalName()`, rejecting unknown values with `422 INVALID_INPUT` and persisting only canonical names.
2. **Consistent Island-Wide Queries & Reporting:**
   - Cross-hospital lookups and aggregations (e.g. querying all `Cardiology` doctors, cases, or appointments across all public hospitals) match reliably because every hospital uses the exact same canonical `department_name`.
3. **Decouples Storage from UI Language:**
   - MySQL always stores one language-neutral canonical value (`Cardiology`), while `chnDepartmentLabel()` renders `Cardiology` (EN), `Kardiyoloji` (TR), or `Кардиология` (RU) across all pages.
4. **Uniform Lookup DTOs (`backend/get_hospitals.php`):**
   - Returns `department_id`, `department_key`, `department_name` (canonical), and `department_label` (localized) so frontend selectors can match departments by ID or key cleanly.

---

## 3. Integrated Pages
- **Admin Hospitals & Departments (`pages/hospitals.php`, `js/hospitals.js`):** Replaces free-text department input with a standardized `<select name="department_key">` populated from `chnDepartmentCatalog()`.
- **User Management (`pages/user-management.php`):** Displays localized department names in workplace dropdowns and table rows.
- **Medical Cases & Appointments (`pages/medical-cases.php`, `pages/appointments.php`):** Renders all department filters, dropdowns, and labels through `chnDepartmentLabel()`.
