<?php
/**
 * Molehi-AI Chatbot Backend Proxy
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

/**
 * Loads training and knowledge base markdown files from the ai-training/ directory.
 * Reads ai-training/ai-training.md or combines any .md files found in the folder.
 */
function loadAiTrainingContext(): string
{
    $trainingDir = dirname(__DIR__) . '/ai-training';

    $defaultInstruction = "Sen Kıbrıs Sağlık Ağı (Cyprus Health-Care Network - CHN) sisteminin resmi yapay zeka sağlık asistanısın. Görevin hastalara ve sistem kullanıcılarına sağlık süreçleri, randevu alma, hastane birimleri, doktor branşları ve sistem işleyişi hakkında nazik, profesyonel, güvenilir ve anlaşılır rehberlik sunmaktır.
Önemli Kurallar:
1. Tıbbi acil durumlarda (şiddetli göğüs ağrısı, nefes darlığı, ağır kanama vb.) derhal 112 Acil Servis'e yönlendir.
2. Kesin bir tıbbi tanı koyamazsın.
3. CHN Triage renkleri: Turuncu (Acil bakım), Sarı (Önemli takip), Yeşil (Hayati risk yok, 0 risk).";

    if (!is_dir($trainingDir)) {
        return $defaultInstruction;
    }

    // Specific training file takes priority if present
    $primaryFile = $trainingDir . '/ai-training.md';
    if (is_file($primaryFile) && is_readable($primaryFile)) {
        $content = trim((string)file_get_contents($primaryFile));
        if ($content !== '') {
            return $content;
        }
    }

    // Otherwise, scan all .md files in the folder
    $mdFiles = glob($trainingDir . '/*.md');
    if (!empty($mdFiles)) {
        $combined = [];
        foreach ($mdFiles as $file) {
            if (is_file($file) && is_readable($file)) {
                $text = trim((string)file_get_contents($file));
                if ($text !== '') {
                    $combined[] = $text;
                }
            }
        }
        if (!empty($combined)) {
            return implode("\n\n---\n\n", $combined);
        }
    }

    return $defaultInstruction;
}

// Load env configuration
loadChnEnv();

$geminiApiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
$geminiModel = getenv('GEMINI_MODEL') ?: ($_ENV['GEMINI_MODEL'] ?? 'gemini-3.5-flash-lite');

// Load AI training context from ai-training/ folder
$baseTrainingContext = loadAiTrainingContext();

// Reinforce concise, friendly conversation with selective bolding and emojis
$styleDirectives = "\n\nKRİTİK İLETİŞİM VE FORMAT KURALLARI:
1. YANIT UZUNLUĞU VE ESNEKLİK: Yanıtlarını olabildiğince 3-4 kısa cümleye sığdırmaya çalış. Ancak adım adım navigasyon veya yönlendirme gereken durumlarda madde işaretleri (bullet list) kullanmaktan ve kullanıcıyı aydınlatacak kadar yeterli cümle kurmaktan çekinme.
2. VURGULAR VE EMOJİ: Önemli sayfa adlarını, butonları, numaraları veya kritik uyarıları **kalın** (bold) olarak vurgula (örneğin **Appointments**, **112**, **Tıbbi Kayıtlar**). Mesajı samimi, sıcak ve profesyonel kılmak için uygun emojiler (🩺, 📅, 🚨, vb.) kullan.
3. AKICI VE DOĞAL DİL: Genel sohbetlerde robotik başlıklar (###) kullanma; canlı bir sohbetteki gibi akıcı, doğrudan ve samimi konuş.";

$systemInstruction = $baseTrainingContext . $styleDirectives;

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
    // Generate intelligent simulated answers for common queries in demo mode based on ai-training.md
    $lower = mb_strtolower($userMessage);
    $demoReply = "";

    if (str_contains($lower, 'randevu') || str_contains($lower, 'appointment')) {
        $demoReply = "📅 **Randevu & Vaka Yapısı:**\n\nCHN sistemi 'Vaka Bazlı (Case-Based)' çalışır. Randevularınız şikayetlerinize göre gruplandırılır. Sol menüden **Appointments (Randevular)** sayfasına giderek randevu alabilir (Book), görüntüleyebilir (View) veya yeniden planlayabilirsiniz (Reschedule).\n\n*(Sistem şu an Demo modundadır. `config/.env` dosyasına `GEMINI_API_KEY` girildiğinde `ai-training/ai-training.md` yönergelerine göre tam AI yanıtı üretecektir.)*";
    } elseif (str_contains($lower, 'acil') || str_contains($lower, 'emergency') || str_contains($lower, '112') || str_contains($lower, 'kalp')) {
        $demoReply = "🚨 **Acil Durum Bildirimi:**\n\nHayati tehlikesi olan durumlarda lütfen randevu almayı beklemeyin; derhal **112 Acil Servis'i** arayın veya en yakın acil servise başvurun. Ayrıca CHN üzerindeki **Acil Durum (Emergency)** butonuna basarak 2 aşamalı doğrulama sonrası konumunuzu sağlık ekiplerine iletebilirsiniz.\n\n*(Sistem Demo Modundadır. `ai-training.md` bilgi bankası yüklendi.)*";
    } elseif (str_contains($lower, 'triage') || str_contains($lower, 'renk') || str_contains($lower, 'tier') || str_contains($lower, 'kırmızı')) {
        $demoReply = "🚦 **CHN Tıbbi Durum Seviyelendirmesi (Tiering):**\n\n- 🟠 **Turuncu (Orange):** Yüksek Risk / Bakım Gerektirir.\n- 🟡 **Sarı (Yellow):** Önemli / Orta Düzey Risk.\n- 🟢 **Yeşil (Green):** Hayati Risk Yok (0 Risk).\n\n*(Not: Sistemimizde Kırmızı seviye bilerek yoktur; acil durumdaki hastaların sistem yerine doğrudan 112'ye yönlendirilmesi hedeflenmiştir.)*";
    } elseif (str_contains($lower, 'vaka') || str_contains($lower, 'case')) {
        $demoReply = "📋 **Vaka Bazlı Yapı (Case-Based Structure):**\n\nCHN'de hastanın her rahatsızlığı bir 'Vaka (Case)' olarak açılır. Tüm tahlil, randevu ve reçeteler bu vakanın altında toplanır. Böylece hekimler geçmişinizi bütüncül görebilir.";
    } else {
        $demoReply = "👋 Merhaba! Ben **CHN Yapay Zeka Sağlık Asistanıyım**.\n\n`ai-training/ai-training.md` bilgi bankası başarıyla yüklendi! 📚\nŞu anda sistem **Demo Modunda** çalışmaktadır çünkü `config/.env` dosyasına henüz `GEMINI_API_KEY` eklenmemiştir.\n\n📌 **Sorunuz:**\n> \"{$userMessage}\"\n\nAnahtarınızı eklediğiniz anda Google Gemini yapay zekası `ai-training.md` içindeki tüm kurallara uyarak yanıt verecektir.";
    }

    echo json_encode([
        'success' => true,
        'reply' => $demoReply,
        'demo' => true,
        'model' => 'demo-mode',
        'training_source' => 'ai-training/ai-training.md'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

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
        'temperature' => 0.5,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 600,
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
