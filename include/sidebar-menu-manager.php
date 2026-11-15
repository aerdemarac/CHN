<?php
/**
 * CHN role-based sidebar menu definitions (presentation only).
 *
 * The caller MUST set $chnUserRole from a trusted authenticated backend/DB
 * lookup before including include/left-sidebar.php.
 * Hiding or showing links DOES NOT enforce access permissions.
 *
 * Design decisions (Nov 14, 2026):
 * - AI Assistant is NOT part of the sidebar; planned as a floating button.
 * - Account/Profile options live in the Top Bar, not as duplicate sidebar links.
 * - Patient self-registration is outside the logged-in sidebar.
 * - Admin User Management handles controlled account creation (doctors/staff).
 * - Case items precede Medical Records items wherever both appear.
 * - Pages not yet built are rendered as "Soon" by left-sidebar.php.
 * - Access levels and final menu labels must be approved by the backend team.
 */

declare(strict_types=1);

if (!function_exists('chnSidebarItemsForRole')) {
    function chnSidebarItemsForRole(string $role): array
    {
        // Only pre-existing icon names from css/style.css.
        // Routes are project-root relative; unimplemented routes display Soon.
        $items = [
            'dashboard' => ['label' => 'Dashboard', 'file' => 'index.php', 'icon' => 'dashboard'],
            'appointments' => ['label' => 'Appointments', 'file' => 'appointments.php', 'icon' => 'appointments'],
            'patients' => ['label' => 'Patients', 'file' => 'patients.php', 'icon' => 'records'],
            'cases' => ['label' => 'Medical Cases', 'file' => 'medical-cases.php', 'icon' => 'cases'],
            'records' => ['label' => 'Medical Records', 'file' => 'medical-records.php', 'icon' => 'records'],
            'alerts' => ['label' => 'Emergency Alerts', 'file' => 'emergency-alerts.php', 'icon' => 'cases'],
            'feedback' => ['label' => 'Feedback & Support', 'file' => 'feedback.php', 'icon' => 'feedback'],
            'users' => ['label' => 'User Management', 'file' => 'admin/user-management.php', 'icon' => 'records'],
            'complaints' => ['label' => 'Complaint Management', 'file' => 'complaints.php', 'icon' => 'feedback'],
            'emergency_logs' => ['label' => 'Emergency Logs', 'file' => 'emergency-logs.php', 'icon' => 'cases'],
            'settings' => ['label' => 'Settings', 'file' => 'settings.php', 'icon' => 'preview'],
        ];

        $byRole = [
            'patient' => [
                'dashboard', 'appointments', 'cases', 'records', 'feedback',
            ],
            'doctor' => [
                'dashboard', 'appointments', 'patients', 'cases', 'records', 'feedback',
            ],
            'healthcare_staff' => [
                'dashboard', 'alerts', 'appointments', 'patients', 'cases', 'records', 'feedback',
            ],
            'admin' => [
                'dashboard', 'users', 'complaints', 'emergency_logs', 'settings',
            ],
        ];

        $normalized = strtolower(trim($role));
        $normalized = str_replace([' ', '-'], '_', $normalized);
        $keys = $byRole[$normalized] ?? []; // Unknown roles see no protected navigation.
        return array_map(static fn(string $key): array => $items[$key], $keys);
    }
}
