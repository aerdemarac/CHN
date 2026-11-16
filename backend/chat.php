<?php
/**
 * CHN AI Chatbot Backend Proxy
 * Connects frontend Chatwidget.js to Google Gemini API
 * Reads GEMINI_API_KEY and GEMINI_MODEL from config/.env
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only allow POST or OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Only POST requests are allowed.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Loads environment variables from config/.env without external dependencies.
 */
function loadChnEnv(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $candidates = [
        dirname(__DIR__) . '/config/.env',
        dirname(__DIR__) . '/.env',
    ];

    $envPath = null;
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $envPath = $candidate;
            break;
        }
    }

    if ($envPath === null) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Load env configuration
loadChnEnv();

$geminiApiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
$geminiModel = getenv('GEMINI_MODEL') ?: ($_ENV['GEMINI_MODEL'] ?? 'gemini-3.5-flash-lite');

// Read JSON payload from request body
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userMessage = trim((string)($data['message'] ?? ''));
$history = is_array($data['history'] ?? null) ? $data['history'] : [];

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Message cannot be empty.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Safety check on user message length
if (mb_strlen($userMessage) > 2000) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Message is too long (maximum 2000 characters).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// If GEMINI_API_KEY is not configured yet, provide an informative simulated response
if (empty($geminiApiKey)) {
    // Generate intelligent simulated answers for common queries in demo mode
    $lower = mb_strtolower($userMessage);
    $demoReply = "";

    if (str_contains($lower, 'randevu') || str_contains($lower, 'appointment')) {
        $demoReply = "📅 **Randevu İşlemleri:**\n\nCHN sistemi üzerinden randevu almak için sol menüdeki **Appointments (Randevular)** sayfasına giderek müsait doktor ve poliklinik saatlerini görüntüleyebilirsiniz.\n\n*(Not: Şu anda Demo modundayım. `config/.env` dosyasına `GEMINI_API_KEY` tanımlandığında sorularınıza gerçek zamanlı yapay zeka yanıt verecektir.)*";
    } elseif (str_contains($lower, 'acil') || str_contains($lower, 'emergency') || str_contains($lower, '112')) {
        $demoReply = "🚨 **Acil Sağlık Durumları:**\n\nAcil tıbbi yardım, kaza veya hayati tehlike arz eden durumlarda lütfen vakit kaybetmeden **112 Acil Çağrı Merkezi**'ni arayınız.\n\n*(Sistem Demo Modundadır. Sorunuz: \"{$userMessage}\")*";
    } elseif (str_contains($lower, 'triage') || str_contains($lower, 'renk') || str_contains($lower, 'tier')) {
        $demoReply = "🚦 **CHN Triage Aciliyet Renkleri:**\n\n- 🟠 **Turuncu (Orange):** Acil bakım gerektirir, önceliklidir.\n- 🟡 **Sarı (Yellow):** Önemli vaka, takip gerektirir.\n- 🟢 **Yeşil (Green):** Hayati risk yok, standart poliklinik süreci.\n\n*(Sistem Demo Modundadır. `GEMINI_API_KEY` eklendiğinde tam AI desteği aktifleşecektir.)*";
    } else {
        $demoReply = "👋 Merhaba! Ben **CHN Yapay Zeka Sağlık Asistanıyım**.\n\nŞu anda sistem **Demo Modunda** çalışmaktadır çünkü `config/.env` dosyasına henüz geçerli bir `GEMINI_API_KEY` eklenmemiştir.\n\n📌 **Mesajınızı aldım:**\n> \"{$userMessage}\"\n\nAnahtarınızı eklediğiniz anda tüm sağlık, randevu, hekim ve hastane sorularınızı doğrudan Google Gemini yapay zekası yanıtlayacaktır.";
    }

    echo json_encode([
        'success' => true,
        'reply' => $demoReply,
        'demo' => true,
        'model' => 'demo-mode'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// System instruction for CHN Medical Assistant
$systemInstruction = "Sen Kıbrıs Sağlık Ağı (Cyprus Health-Care Network - CHN) sisteminin resmi yapay zeka sağlık asistanısın. Görevin hastalara ve sistem kullanıcılarına sağlık süreçleri, randevu alma, hastane birimleri, doktor branşları ve sistem işleyişi hakkında nazik, profesyonel, güvenilir ve anlaşılır rehberlik sunmaktır.

Önemli Kurallar:
1. Tıbbi acil durumlarda (şiddetli göğüs ağrısı, nefes darlığı, ağır kanama, bilinç kaybı vb.) kullanıcıyı derhal 112 Acil Servis'i aramaya yönlendir.
2. Kesin bir tıbbi tanı koyamazsın. Bilgilerin genel sağlık ve sistem danışmanlığı amaçlı olduğunu, kesin teşhis ve tedavi için uzman doktora muayene olunması gerektiğini gerektiğinde nazikçe hatırlat.
3. Kullanıcı hangi dilde yazarsa o dilde yanıt ver (özellikle Türkçe ve İngilizce).
4. Yanıtlarını okunabilir kılmak için kısa paragraflar, madde işaretleri (*) ve kalın metinler (**) kullan.
5. CHN Triage renkleri: Turuncu (Acil bakım gerektirir), Sarı (Önemli takip), Yeşil (Hayati risk yok, standart).";

// Format multi-turn conversation contents for Gemini API
$contents = [];

// Limit history to the last 10 turns to conserve token quota
$trimmedHistory = array_slice($history, -10);

foreach ($trimmedHistory as $item) {
    if (!is_array($item)) {
        continue;
    }
    $role = (($item['role'] ?? '') === 'user') ? 'user' : 'model';
    $text = trim((string)($item['text'] ?? ''));
    if ($text === '') {
        continue;
    }
    $contents[] = [
        'role' => $role,
        'parts' => [
            ['text' => $text]
        ]
    ];
}

// Append current user message
$contents[] = [
    'role' => 'user',
    'parts' => [
        ['text' => $userMessage]
    ]
];

// Build payload for Gemini API
$payload = [
    'system_instruction' => [
        'parts' => [
            ['text' => $systemInstruction]
        ]
    ],
    'contents' => $contents,
    'generationConfig' => [
        'temperature' => 0.7,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 1024,
    ]
];

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent?key=" . urlencode($geminiApiKey);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-Agent: CHN-Chatbot/1.0'
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$responseBody = curl_exec($ch);
$httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($responseBody === false || $curlError !== '') {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => 'Gemini API bağlantı hatası: ' . ($curlError ?: 'Bilinmeyen cURL hatası.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$geminiResponse = json_decode($responseBody, true);

if ($httpStatus !== 200 || !is_array($geminiResponse)) {
    $apiErrorMessage = $geminiResponse['error']['message'] ?? "Gemini API Hatası (HTTP {$httpStatus}).";
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'error' => $apiErrorMessage,
        'status_code' => $httpStatus
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Extract generated text
$replyText = $geminiResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!is_string($replyText) || trim($replyText) === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Gemini yanıt üretemedi veya içerik filtrelendi.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'reply' => trim($replyText),
    'model' => $geminiModel
], JSON_UNESCAPED_UNICODE);
