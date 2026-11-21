<?php
/** Frontend prototype ONLY. URL role selection never authenticates or authorizes. */
require_once __DIR__ . '/i18n.php';
$chnPrototypeRoles = [
    'patient' => t('role.patient'),
    'doctor' => t('role.doctor'),
    'healthcare_staff' => t('role.healthcare_staff'),
    'admin' => t('role.admin'),
];
$requestedRole = isset($_GET['role']) && is_string($_GET['role']) ? trim($_GET['role']) : '';
if ($requestedRole === 'staff') {
    $requestedRole = 'healthcare_staff';
}
$chnPrototypeRole = array_key_exists($requestedRole, $chnPrototypeRoles) ? $requestedRole : 'patient';
$chnUserRole = $chnPrototypeRole;

/** Use only for authored internal prototype routes. Preserve query and fragment. */
function chnPrototypeUrl(string $path, string $role, array $parameters = []): string
{
    $parts = explode('#', $path, 2);
    $route = explode('?', $parts[0], 2);
    $existing = [];
    parse_str($route[1] ?? '', $existing);
    $query = array_merge($existing, $parameters, ['role' => $role]);
    return $route[0] . '?' . http_build_query($query)
        . (isset($parts[1]) ? '#' . $parts[1] : '');
}
