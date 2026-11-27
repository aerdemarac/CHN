<?php
declare(strict_types=1);

require_once __DIR__ . '/i18n.php';

/**
 * Return the standardized clinic department key => canonical name map.
 */
function chnDepartmentCatalog(): array
{
    static $catalog = null;
    if ($catalog === null) {
        $catalog = require dirname(__DIR__) . '/lib/departments.php';
    }
    return $catalog;
}

/**
 * Resolve a department key or canonical name to its catalog key, or null if unknown.
 */
function chnDepartmentKey(string $keyOrName): ?string
{
    $trimmed = trim($keyOrName);
    if ($trimmed === '') {
        return null;
    }

    $catalog = chnDepartmentCatalog();
    if (isset($catalog[$trimmed])) {
        return $trimmed;
    }

    $lower = mb_strtolower($trimmed, 'UTF-8');
    foreach ($catalog as $key => $canonicalName) {
        if (mb_strtolower($canonicalName, 'UTF-8') === $lower) {
            return $key;
        }
    }

    return null;
}

/**
 * Resolve a department key or name to its canonical database department_name.
 */
function chnDepartmentCanonicalName(string $keyOrName): ?string
{
    $key = chnDepartmentKey($keyOrName);
    if ($key === null) {
        return null;
    }
    return chnDepartmentCatalog()[$key];
}

/**
 * Return the localized display label for a department key or database department_name.
 */
function chnDepartmentLabel(string $keyOrName): string
{
    $key = chnDepartmentKey($keyOrName);
    if ($key === null) {
        return $keyOrName;
    }

    $translationKey = 'department.' . $key;
    $translated = t($translationKey);
    return $translated !== $translationKey ? $translated : chnDepartmentCatalog()[$key];
}
