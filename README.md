# CHN - Cyprus Healthcare Network 

## Team Responsibilities

| Member | Responsibility |
|---|---|
| İzgihan | Database, PHP Backend |
| Erdem | Application & API PHP Backend |
| Şevket | HTML, CSS, JavaScript |
| Cem | HTML, CSS, JavaScript |

## Project Structure

## File Responsibilities (ALL FILES ARE SAMPLES, NOTHING REAL)

### Root Pages
- `index.php` - Main application page.
- `login.php` - User login page.
- `register.php` - User registration page.

### `assets/`
- Folder that contains image, icon and font sources

### `css/`
- `style.css` - Shared styles, layout, colors, and UI components.

### `js/`
- `app.js` - General frontend logic and application behavior.
- `auth.js` - Login, registration, and authentication-related frontend logic.

### `backend/`
Contains the PHP backend endpoints shared by the web and Electron applications.

- `config.php` - MySQL database connection.
- `login.php` - Handles login requests.
- `register.php` - Handles user registration.
- `appointments.php` - Appointment management.
- `medical-cases.php` - Medical case operations.
- `chatbot.php` - AI chatbot requests.
- `emergency.php` - Emergency alert requests.

### `include/`
Reusable PHP-HTML components shared across all web pages such as LEFT-Sidebar and TOP-BAR visual components.
Which will be used through entire web pages with php include feature

- `left-sidebar.php` — Left Navigation bar thats included by all pages
- `top-bar.php` — Top Navigation bar thats same in all pages 

### `lib/`
Reusable PHP libraries such as php mailer or AI Chatbot
- `chatbot.php` - AI Chatbot
- `mailer.php` - Email services using PHPMailer.

### `db/`
- `schema.sql` - SQL commands to create the database tables.
- `seed.sql` - Sample data for development and testing.

Database changes must be applied to the local MySQL database separately; `git pull` does not automatically update it.

### `electron/`
Contains the desktop application files.

- `main.js` - Electron main process and window management.
- `package.json` - Electron dependencies, project metadata, and scripts.

The Electron application uses the shared PHP API rather than a separate backend.

### `test-endpoints/`
Contains php files aiming to test freshly introduced features

- `test-mailer.php` - PHPMailer Test Demonstration
- `top-and-sidebar-test.php` - Demo dashboard containing refined side and topbar implementations

### `config/`
Folder that contains local environment configurations, secret credentials (such as database credentials or SMTP passwords in `.env`), and sensitive settings.
This directory and its contents are strictly excluded from version control via `.gitignore` to prevent secret leaks to remote repositories.

### `.gitignore`
Specifies files that should not be committed, such as local secrets, environment files, and dependencies.

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript
- **Desktop:** Electron
- **Backend:** PHP, REST API
- **Database:** MySQL
- **Email:** PHPMailer

## Core Features

- User registration and authentication
- Role-based access control
- Appointment management
- Case-based medical records
- AI help desk chatbot
- Emergency alerts
- Medical condition severity levels
- Email notifications and two-factor authentication
- Multi-language support
- Authorized external API integration

## Limitations

- Uses demonstration data instead of real patient records.
- The AI chatbot does not access live patient data or replace professional medical diagnosis.
- Emergency alert functionality is limited to the prototype.
- End-to-end encryption is outside the current project scope.

## Setup

1. Install and start Apache and MySQL using XAMPP.
2. Place the project in `C:\xampp\htdocs\chn\`.
3. Create the `chn_db` database in phpMyAdmin.
4. Import `db/schema.sql`.
5. Import `db/seed.sql` if sample data is needed.
6. Configure the local database connection in `backend/config.php`.
7. Open `http://localhost/chn/` in your browser.

**Note:** CHN is an academic prototype and is not intended to replace official healthcare or emergency services.

**Note:** In order to keep the same design pallette and concept through the entire project, One design outline file will be created and must be uploaded to AI before any AI front end design. The whole project must look in one piece and concept.
