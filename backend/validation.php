<?php
declare(strict_types=1);

/**
 * Permitted letters: English Latin (A-Z, a-z) and Turkish Latin characters.
 */
const CHN_ALLOWED_LETTERS = 'A-Za-zçÇğĞıİöÖşŞüÜ';

/**
 * Extract a trimmed string field from a decoded JSON payload.
 */
function chnInputString(array $input, string $key): string
{
    $value = $input[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/**
 * Validate a person's first or last name against the allowed character set.
 */
function chnIsValidName(string $value): bool
{
    $len = mb_strlen($value, 'UTF-8');
    if ($len < 1 || $len > 50) {
        return false;
    }

    $pattern = '/^[' . CHN_ALLOWED_LETTERS . '][' . CHN_ALLOWED_LETTERS . ' \'.-]*$/u';
    return preg_match($pattern, $value) === 1;
}

/**
 * Validate a YYYY-MM-DD birth date between 1900-01-01 and today.
 */
function chnIsValidBirthDate(string $value): bool
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) !== 1) {
        return false;
    }

    $year = (int) $matches[1];
    $month = (int) $matches[2];
    $day = (int) $matches[3];

    if (!checkdate($month, $day, $year)) {
        return false;
    }

    $today = date('Y-m-d');
    return $value >= '1900-01-01' && $value <= $today;
}

/**
 * Validate a Turkish Republic Identity Number (TC Kimlik No).
 */
function chnIsValidTcKimlikNo(string $value): bool
{
    if (preg_match('/^[1-9][0-9]{10}$/', $value) !== 1) {
        return false;
    }

    $digits = array_map('intval', str_split($value));
    $odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
    $even = $digits[1] + $digits[3] + $digits[5] + $digits[7];
    $tenth = ((($odd * 7 - $even) % 10) + 10) % 10;
    $eleventh = array_sum(array_slice($digits, 0, 10)) % 10;

    return $digits[9] === $tenth && $digits[10] === $eleventh;
}

/**
 * Validate national ID / passport number based on nationality.
 */
function chnIsValidNationalId(string $value, string $nationality): bool
{
    if ($nationality === 'TR') {
        $compact = preg_replace('/\s+/', '', $value) ?? '';
        return chnIsValidTcKimlikNo($compact);
    }

    if (strlen($value) > 50 || preg_match('/^[A-Za-z0-9\s-]+$/', $value) !== 1) {
        return false;
    }

    $clean = preg_replace('/[\s-]+/', '', $value) ?? '';
    $len = strlen($clean);
    return $len >= 5 && $len <= 50;
}

/**
 * Normalize a phone number to optional leading '+' followed by digits.
 */
function chnNormalizePhone(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    $hasPlus = str_starts_with($trimmed, '+');
    $digits = preg_replace('/\D/', '', $trimmed) ?? '';
    return ($hasPlus ? '+' : '') . $digits;
}

/**
 * Validate a phone number and ensure normalized length fits VARCHAR(16).
 */
function chnIsValidPhone(string $value): bool
{
    if (preg_match('/^\+?[0-9\s()-]+$/', $value) !== 1) {
        return false;
    }
    $normalized = chnNormalizePhone($value);
    $digits = preg_replace('/\D/', '', $normalized) ?? '';
    return strlen($digits) >= 7 && strlen($normalized) <= 16;
}

/**
 * Validate an email address against supported characters and length.
 */
function chnIsValidEmail(string $value): bool
{
    if ($value === '' || strlen($value) > 254) {
        return false;
    }
    if (preg_match('/^[A-Za-z0-9._%+\-@]+$/', $value) !== 1) {
        return false;
    }
    return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength and permitted characters.
 */
function chnIsValidPassword(string $value): bool
{
    $len = mb_strlen($value, 'UTF-8');
    if ($len < 8 || $len > 255) {
        return false;
    }
    if (preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
        return false;
    }
    $hasLetter = preg_match('/[' . CHN_ALLOWED_LETTERS . ']/u', $value) === 1;
    $hasDigit = preg_match('/\d/', $value) === 1;
    return $hasLetter && $hasDigit;
}
