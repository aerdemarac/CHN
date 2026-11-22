# CHN — Backend Structure & Development Guidelines

## 1. Project Overview & Development Rules

CHN is a university graduation project developed by a team of four students using PHP, MySQL, HTML, CSS, and JavaScript.

The backend is intentionally kept simple. The goal is to build a functional, secure, and maintainable application without introducing unnecessary architectural complexity.

### Development Rules for AI Agents

When working on this project:

- Follow the existing project structure and coding conventions.
- Read relevant existing files before modifying or creating new ones.
- Prefer simple procedural PHP over unnecessary OOP abstractions or design patterns.
- Do not introduce frameworks, ORMs, repositories, or additional dependencies.
- Reuse existing functions and shared code instead of duplicating functionality.
- Avoid modifying files or functionality unrelated to the requested task.
- Do not silently change established architectural or business decisions.
- Ask for clarification when requirements are unclear or conflict with existing decisions.
- Keep implementations understandable for all four team members.
- Update this document when an architectural decision is explicitly changed.

## 2. Architecture & Communication Protocol

- **Architecture:** Unified Fetch API + JSON communication across both Web and Electron applications.
- **Backend Directory:** All API endpoints must reside under `backend/` (e.g., `backend/login.php`, `backend/create_appointment.php`).
- **HTTP Methods:**
  - GET requests must not modify application data.
  - All state-changing operations must use POST.
- **JSON Communication:**
  - Frontend communicates with backend endpoints exclusively via `fetch()` with JSON payloads.
  - Backend handlers parse JSON input via `php://input` and always respond with `Content-Type: application/json; charset=utf-8`.
  - Backend handlers never render HTML or issue `header('Location: ...')` redirects; they strictly return JSON.
- **Separation of Responsibilities:**
  - Frontend pages display HTML, manage user interactions, and handle DOM updates via Fetch responses.
  - Backend handlers process requests, validate input, interact with the database, and return JSON responses.
  - Page redirection (e.g. after login) is performed client-side using redirect URLs returned in the JSON response.
  - Shared PHP functions should be reused where appropriate.

## 3. Authentication & Authorization

### 3.1 Sessions

- Use native PHP Sessions (`$_SESSION`).
- Session cookies must use:
  - `HttpOnly = true`
  - `SameSite = Lax`
  - `Secure = true` when operating over HTTPS.
- Client `fetch()` requests must include credentials (`credentials: 'same-origin'`) so session cookies are automatically transmitted.
- Sessions expire after 30 minutes of inactivity, tracked through `$_SESSION['last_activity']`.
- Session IDs must be regenerated using `session_regenerate_id(true)` after successful login.
- Enable `session.use_strict_mode = 1`.
- Logout and session expiration must properly clear and destroy the session.
- Language preference is stored in a persistent `chn_lang` cookie to retain user choice across sessions and for unauthenticated guests, and synchronized to `$_SESSION['lang']` during active sessions.
- Remember Me functionality is disabled.

### 3.2 Role-Based Authorization

- Use a centralized `requireRole([...])` function.
- For HTML page requests: Unauthenticated users are redirected to `login.php`; unauthorized users are redirected to their dashboard.
- For API endpoints: Unauthenticated requests return HTTP 401 Unauthorized; unauthorized roles return HTTP 403 Forbidden with a JSON error response.
- Authorization must be checked on the backend, not just through frontend visibility.

### 3.3 Data Ownership

- Users must only access and modify records they are authorized to manage.
- Ownership must be enforced directly in SQL queries where applicable.

Example:

```sql
WHERE id = :id AND patient_id = :session_user_id
```

- Never trust record IDs or ownership information submitted by the client.

## 4. Database & Data Management

### 4.1 Database Access

- Use raw PDO with reusable PHP functions.
- No ORM, repository pattern, or unnecessary database abstraction layers.
- Always use Prepared Statements for dynamic SQL values.
- Never concatenate untrusted input into SQL queries.
- Reuse the project's shared database connection.

### 4.2 Data Deletion

- Use Hard Delete (`DELETE FROM ...`) as the default deletion strategy.
- Define appropriate foreign key rules using `ON DELETE CASCADE` or `ON DELETE SET NULL`.
- No soft-delete system unless explicitly required by a future feature.

### 4.3 Transactions & Data Integrity

- Use PDO transactions when multiple database operations must succeed or fail together.
- Transactions are mandatory when an operation modifies two or more tables.
- Roll back active transactions when an operation fails.
- Use database constraints such as `PRIMARY KEY`, `FOREIGN KEY`, `UNIQUE`, and `NOT NULL` to protect data integrity.
- Handle database errors gracefully without exposing internal SQL errors to users.

## 5. Core Business Logic

### 5.1 Appointments

- A doctor cannot have multiple appointments for the same date and time slot.
- Prevent double-booking using a database-level `UNIQUE` constraint on:

```sql
UNIQUE (doctor_id, appointment_date, appointment_time)
```

- Handle duplicate booking attempts gracefully, returning an appropriate JSON error response.
- Appointments cannot be scheduled in the past.
- Patients may cancel appointments only when at least 2 hours remain before the appointment.
- Verify the user's role and appointment ownership before viewing, modifying, or cancelling appointments.
- Cancellation uses Hard Delete (`DELETE FROM appointments`).
- Cancelled appointments are permanently removed, making their time slots available again.

### 5.2 Registration & User Management

- Public registration through `backend/register.php` creates `patient` accounts only.
- Users must not be able to assign themselves privileged roles.
- All other roles (`doctor`, `healthcare_staff`, `admin`) are created and managed exclusively through the Admin panel.
- Role assignments must always be validated server-side.

### 5.3 Emergency Reports

- Emergency reports can be submitted by unauthenticated users.
- Record the client's connection IP using `$_SERVER['REMOTE_ADDR']`.
- Do not blindly trust forwarded IP headers such as `X-Forwarded-For`.
- Validate submitted data on the server.

## 6. Security & Error Handling

### 6.1 Password Security

- Hash passwords using `password_hash($password, PASSWORD_DEFAULT)`.
- Verify passwords using `password_verify()`.
- Never store plaintext passwords.

### 6.2 Brute-Force Protection

- Lock an account for 15 minutes after 5 consecutive failed login attempts.
- Track attempts using database fields such as `failed_attempts` and `locked_until`.
- Reset the failed attempt counter after successful authentication.
- Perform lockout updates safely to prevent simultaneous requests from bypassing the limit.
- Use generic login error messages that do not reveal whether an account exists.

### 6.3 XSS Prevention

- Store validated user input as raw data in the database.
- Escape output whenever untrusted data is displayed in HTML.
- Use a shared `e()` or `te()` helper:

```php
function e($value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}
```

- When inserting data into the DOM via JavaScript, use `textContent` or proper attributes instead of `innerHTML`.

### 6.4 Error Handling

- Disable `display_errors` in production.
- Log PHP and database errors to `logs/error.log`.
- Never display raw SQL errors, stack traces, or internal exception messages to users.
- Ensure log files cannot be accessed publicly.
- Never log passwords, session IDs, or authentication tokens.

### 6.5 Character Set Validation and Enforcement

The backend must enforce the application's supported character set independently of frontend validation.

**Accepted characters:**
- English Latin alphabet: `A-Z`, `a-z`
- Turkish Latin characters: `ç, Ç, ğ, Ğ, ı, İ, ö, Ö, ş, Ş, ü, Ü`
- Numeric digits: `0-9`
- Normal whitespace
- Field-specific permitted punctuation.

**Backend enforcement requirements:**
- Reject any incoming text containing characters outside the explicitly permitted character set.
- Never assume that frontend validation has already been performed.
- Validate all relevant incoming values before executing database INSERT or UPDATE operations.
- Invalid input must never be persisted in the database.
- Return an appropriate HTTP 400 response with a JSON error when unsupported characters are detected.
- Use centralized PHP validation helpers to ensure consistent enforcement.
- Use Unicode-aware regular expressions (`/u`) or equivalent validation logic.
- Apply field-specific validation rules where appropriate.
- Do not silently sanitize invalid input and continue processing the request; reject the request instead.

**Security requirement:**

Backend validation is authoritative. Even if a client bypasses all frontend restrictions and sends a manually constructed HTTP request, the backend must reject unsupported characters before any database modification occurs.

### 6.6 User Feedback & Response Format

- All backend endpoints return a standardized JSON envelope:
  - Success: `{"ok": true, "data": {...}, "message": "..."}` with HTTP 200 or 201.
  - Error: `{"ok": false, "error": "ERROR_CODE", "message": "..."}` with appropriate HTTP error codes (400, 401, 403, 422).
- Frontend handles feedback dynamically (displaying inline error messages, updating table rows, or closing modals without page reloads).
- Never return raw exception messages, stack traces, or sensitive database information in responses.
- Do not introduce a separate Session Flash messaging system or URL query parameter error feedback.

## 7. Coding Standards & Team Consistency

### 7.1 PHP Standards

- Every PHP file must start with:

```php
declare(strict_types=1);
```

- PHP functions and variables: `camelCase`.
- Database tables and columns: `snake_case`.
- Constants: `UPPER_SNAKE_CASE`.
- PHP filenames: `snake_case.php` or `kebab-case.php`.

### 7.2 File Paths

- Use `__DIR__` or `dirname(__DIR__)` for filesystem paths.
- Avoid relative paths that depend on the current working directory.

### 7.3 Team Development

- Keep code simple enough for all four team members to understand and maintain.
- Reuse shared database, authentication, authorization, and validation functions.
- Avoid implementing the same functionality differently in different parts of the application.
- Do not make unnecessary changes to another team member's work.
- Keep database schema changes consistent with the shared SQL schema.
- Test relevant functionality after making changes.

### 7.4 Final Development Principle

The CHN backend is a small team project, not an enterprise software platform.

Prioritize:

1. Functionality
2. Security
3. Readability
4. Consistency
5. Maintainability

Always choose the simplest implementation that satisfies the requirements without compromising security or data integrity.
