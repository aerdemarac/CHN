<?php
/**
 * CHN role-based sidebar menu definitions (presentation only).
 *
 * The caller MUST set $chnUserRole from a trusted authenticated backend/DB
 * lookup before including include/left-sidebar.php in live pages.
 * Fictional prototype pages may opt into include/prototype-role.php instead.
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
            'dashboard' => ['label' => 'Dashboard', 'file' => 'pages/dashboard-test.php', 'icon' => 'dashboard'],
            'appointments' => ['label' => 'Appointments', 'file' => 'appointments.php', 'demo' => 'pages/appointments-test.php', 'icon' => 'appointments'],
            'patients' => ['label' => 'Patients', 'file' => 'pages/patients.php', 'icon' => 'patients'],
            'cases' => ['label' => 'Medical Cases', 'file' => 'pages/medical-cases.php', 'icon' => 'cases'],
            'alerts' => ['label' => 'Emergency Alerts', 'file' => 'pages/emergency-audit.php', 'demo' => 'pages/emergency-demo.php', 'icon' => 'emergency'],
            'feedback' => ['label' => 'Feedback & Support', 'file' => 'pages/feedback.php', 'icon' => 'feedback'],
            'users' => ['label' => 'User Management', 'file' => 'pages/user-management.php', 'icon' => 'users'],
            'hospitals' => ['label' => 'Hospitals', 'file' => 'pages/hospitals.php', 'icon' => 'dashboard'],
            'complaints' => ['label' => 'Complaint Management', 'file' => 'pages/complaints-test.php', 'icon' => 'feedback'],
            'emergency_logs' => ['label' => 'Emergency Logs', 'file' => 'pages/emergency-audit.php', 'demo' => 'pages/emergency-demo.php', 'icon' => 'emergency'],
        ];

        $byRole = [
            'patient' => [
                'overview' => ['dashboard'],
                'health' => ['appointments', 'cases'],
                'support' => ['feedback'],
            ],
            'doctor' => [
                'overview' => ['dashboard'],
                'clinical' => ['appointments', 'patients', 'cases'],
                'support' => ['feedback'],
            ],
            'healthcare_staff' => [
                'overview' => ['dashboard'],
                'emergency' => ['alerts'],
                'clinical' => ['appointments', 'patients', 'cases'],
                'support' => ['feedback'],
            ],
            'admin' => [
                'overview' => ['dashboard'],
                'management' => ['users', 'hospitals', 'complaints'],
                'security' => ['emergency_logs'],
                'support' => ['feedback'],
            ],
        ];

        $normalized = strtolower(trim($role));
        $normalized = str_replace([' ', '-'], '_', $normalized);
        // Compatibility alias: if an older link still passes 'staff', map to 'healthcare_staff'
        if ($normalized === 'staff') {
            $normalized = 'healthcare_staff';
        }
        $groups = $byRole[$normalized] ?? []; // Unknown roles see no protected navigation.
        $result = [];
        foreach ($groups as $group => $keys) {
            foreach ($keys as $key) {
                $result[] = $items[$key] + ['key' => $key, 'group' => $group];
            }
        }
        return $result;
    }
}
