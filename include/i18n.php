<?php
declare(strict_types=1);

const CHN_LANGUAGES = [
    'en' => ['code' => 'EN', 'label' => 'English'],
    'tr' => ['code' => 'TR', 'label' => 'Türkçe'],
    'ru' => ['code' => 'RU', 'label' => 'Русский'],
];
const CHN_DEFAULT_LANG = 'en';
const CHN_LANG_COOKIE = 'chn_lang';

function chnCurrentPath(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return '/' . ltrim(is_string($path) ? $path : '', '/');
}

function chnLangUrl(?string $lang): string
{
    $query = $_GET;
    unset($query['lang']);
    if ($lang !== null) {
        $query['lang'] = $lang;
    }
    return chnCurrentPath() . ($query === [] ? '' : '?' . http_build_query($query));
}

function chnLang(): string
{
    $cookie = $_COOKIE[CHN_LANG_COOKIE] ?? '';
    return is_string($cookie) && isset(CHN_LANGUAGES[$cookie]) ? $cookie : CHN_DEFAULT_LANG;
}

function chnMessages(string $lang): array
{
    static $cache = [];
    if (!isset($cache[$lang])) {
        $base = dirname(__DIR__) . '/lang/';
        $files = array_merge([$base . $lang . '.php'], glob($base . $lang . '/*.php') ?: []);
        $messages = [];
        foreach ($files as $file) {
            $part = is_file($file) ? require $file : [];
            if (is_array($part)) {
                $messages += $part;
            }
        }
        $cache[$lang] = $messages;
    }
    return $cache[$lang];
}

function chnPluralForm(string $lang, int $count): string
{
    $n = abs($count);
    if ($lang === 'ru') {
        if ($n % 10 === 1 && $n % 100 !== 11) {
            return 'one';
        }
        if ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14)) {
            return 'few';
        }
        return 'many';
    }
    if ($lang === 'tr') {
        return 'other';
    }
    return $n === 1 ? 'one' : 'other';
}

function chnLookup(string $key): string|array|null
{
    return chnMessages(chnLang())[$key] ?? chnMessages(CHN_DEFAULT_LANG)[$key] ?? null;
}

function chnFill(string $text, array $params): string
{
    $pairs = [];
    foreach ($params as $name => $value) {
        $pairs['{' . $name . '}'] = (string) $value;
    }
    return strtr($text, $pairs);
}

function t(string $key, array $params = []): string
{
    $text = chnLookup($key);
    if (is_array($text)) {
        $text = $text['other'] ?? $text['many'] ?? reset($text);
    }
    return chnFill(is_string($text) ? $text : $key, $params);
}

function tn(string $key, int $count, array $params = []): string
{
    $forms = chnLookup($key);
    if (!is_array($forms)) {
        return t($key, $params + ['count' => $count]);
    }
    $text = $forms[chnPluralForm(chnLang(), $count)] ?? $forms['other'] ?? $forms['many'] ?? reset($forms);
    return chnFill((string) $text, $params + ['count' => $count]);
}

function chnDate(DateTimeInterface $date, string $style): string
{
    $list = static function (string $key): array {
        $value = chnLookup($key);
        return is_array($value) ? array_values($value) : [];
    };
    $month = (int) $date->format('n') - 1;
    $weekday = (int) $date->format('N') - 1;
    $months = $list('date.months');
    $genitive = $list('date.months_genitive');
    $weekdayName = $list('date.weekdays')[$weekday] ?? $date->format('l');
    return strtr(t('date.' . $style), [
        '{d}' => $date->format('j'),
        '{Y}' => $date->format('Y'),
        '{H:i}' => $date->format('H:i'),
        '{month}' => $months[$month] ?? $date->format('F'),
        '{month_gen}' => $genitive[$month] ?? $months[$month] ?? $date->format('F'),
        '{mon}' => $list('date.months_short')[$month] ?? $date->format('M'),
        '{weekday}' => $weekdayName,
        '{Weekday}' => mb_strtoupper(mb_substr($weekdayName, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($weekdayName, 1, null, 'UTF-8'),
        '{wd}' => $list('date.weekdays_short')[$weekday] ?? $date->format('D'),
    ]);
}

function te(string $key, array $params = []): string
{
    return htmlspecialchars(t($key, $params), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function chnJsMessages(array $keys): string
{
    $messages = [];
    foreach ($keys as $name => $key) {
        $messages[is_int($name) ? $key : $name] = t($key);
    }
    return htmlspecialchars(
        (string) json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$chnRequestedLang = $_GET['lang'] ?? null;
if (is_string($chnRequestedLang) && isset(CHN_LANGUAGES[$chnRequestedLang])) {
    $_COOKIE[CHN_LANG_COOKIE] = $chnRequestedLang;
    if (!headers_sent()) {
        setcookie(CHN_LANG_COOKIE, $chnRequestedLang, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            header('Location: ' . chnLangUrl(null), true, 303);
            exit;
        }
    }
}
unset($chnRequestedLang);
