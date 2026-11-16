<?php
$pageTitle = "Molehi-AI Chatbot Demo";
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | CHN</title>
    <link rel="stylesheet" href="../css/auth.css">
    <!-- Chatbot Widget CSS -->
    <link rel="stylesheet" href="../css/chatwidget.css">
    <style>
        body {
            background-color: #f1f5f9;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #1e293b;
            padding: 40px 20px;
            margin: 0;
        }
        .demo-container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            padding: 36px 40px;
            border: 1px solid #e2e8f0;
        }
        .demo-header {
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 24px;
            margin-bottom: 24px;
        }
        .demo-badge {
            display: inline-block;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 13px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 12px;
        }
        h1 {
            margin: 0 0 10px 0;
            font-size: 26px;
            color: #0f172a;
        }
        p {
            line-height: 1.6;
            color: #475569;
            margin: 0 0 14px 0;
        }
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
        }
        .card h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: #0284c7;
        }
        .code-box {
            background: #0f172a;
            color: #f8fafc;
            padding: 16px 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 13.5px;
            overflow-x: auto;
            margin: 16px 0;
        }
        .callout {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            padding: 14px 18px;
            border-radius: 0 8px 8px 0;
            margin: 20px 0;
            font-size: 14px;
            color: #166534;
        }
    </style>
</head>
<body>

    <div class="demo-container">
        <div class="demo-header">
            <span class="demo-badge">🚀 Canlı Önizleme & Test</span>
            <h1>Molehi-AI Chatbot (Gemini + LocalStorage)</h1>
            <p>Ekranın <strong>sağ alt köşesindeki yüzen baloncuk (bean)</strong> butonuna tıklayarak sohbeti başlatabilirsiniz.</p>
        </div>

        <div class="card-grid">
            <div class="card">
                <h3>🤖 Gemini Yapay Zeka</h3>
                <p>Güvenli PHP proxy katmanı (<code>backend/chat.php</code>) üzerinden <code>config/.env</code> ile iletişim kurar.</p>
            </div>
            <div class="card">
                <h3>💾 LocalStorage Bellek</h3>
                <p>Sayfayı yenileseniz veya sayfalar arasında gezinseniz bile sohbet geçmişiniz kaybolmaz.</p>
            </div>
            <div class="card">
                <h3>📱 Responsive & Şık</h3>
                <p>Masaüstünde şık bir kart, mobil cihazlarda ise dokunmatik uyumlu tam ekran deneyimi sunar.</p>
            </div>
        </div>

        <div class="callout">
            ✅ <strong>API Key Olmadan Da Çalışır:</strong> Şu anda API anahtarı girilmediği için sistem akıllı <em>Demo Modunda</em> çalışır; randevu, triage veya acil durum sorularınıza hazır rehberlik sağlar.
        </div>

        <h3>API Anahtarını Nasıl Tanımlarsınız?</h3>
        <p>Google AI Studio'dan aldığınız API anahtarını projenizdeki <code>config/.env</code> dosyasına eklemeniz yeterlidir:</p>
        <div class="code-box">GEMINI_API_KEY=AIzaSy...SizinAnahtarinizBuraya
GEMINI_MODEL=gemini-1.5-flash</div>

        <h3>Projeye Dahil Etmek İçin:</h3>
        <p>Herhangi bir PHP veya HTML sayfasına yalnızca bu 2 satırı eklemeniz yeterlidir:</p>
        <div class="code-box">&lt;link rel="stylesheet" href="css/chatwidget.css"&gt;
&lt;script src="js/chatwidget.js" defer&gt;&lt;/script&gt;</div>
    </div>

    <!-- Chatbot Widget JS -->
    <script src="../js/chatwidget.js" defer></script>
</body>
</html>
