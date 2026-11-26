# CHN — Register, E-posta Doğrulama, Login, Cookie & Session Akış Detayları

Bu doküman, CHN projesindeki **kayıt → e-posta doğrulama → giriş → cookie/session → yönlendirme** döngüsünün teknik işleyişini adım adım açıklar.

---

## 1. İlk Giriş ve Yönlendirme (`index.php` & `.htaccess`)

1. Kullanıcı tarayıcıdan `http://localhost/CHN/` adresine girdiğinde `.htaccess` içindeki `DirectoryIndex index.php` kuralı isteği `index.php` dosyasına yönlendirir.
2. `index.php`, `backend/auth.php` içindeki `chnStartSession()` fonksiyonunu çağırır:
   - Tarayıcıdan gelen `PHPSESSID` cookie'si varsa PHP bunu sunucudaki session dosyasıyla eşleştirir ve `$_SESSION` dizisini doldurur.
   - `$_SESSION['last_activity']` üzerinden 30 dakikalık inaktivite zaman aşımı kontrol edilir.
3. `index.php` oturum durumuna göre yönlendirme yapar:
   - `$_SESSION['user_id']` ve `$_SESSION['role']` **doluysa**: `chnDashboardUrlForRole($_SESSION['role'])` ile rolüne uygun dashboard'a (`pages/dashboard-test.php?role=<rol>`) `Location` header'ı ile yönlendirir.
   - **Boşsa** (giriş yapılmamış veya session süresi dolmuşsa): `login.php` sayfasına yönlendirir.

---

## 2. Kayıt Akışı (Register Cycle)

### A. Ön Yüz (`register.php` + `js/auth.js`)
1. Kullanıcı kayıt formunu doldurup gönderdiğinde `js/auth.js` içindeki `initRegisterForm()` `submit` olayını yakalar (`event.preventDefault()`).
2. Tarayıcı tarafında ön doğrulama yapılır:
   - Ad / soyad formatı
   - Doğum tarihi aralığı (`1900-01-01` – bugün)
   - Cinsiyet (`female` / `male`)
   - Ülke kodu
   - Kimlik numarası (`TR` seçiliyse 11 haneli TC Kimlik No algoritması; diğer ülkeler için pasaport/kimlik formatı)
   - E-posta formatı
   - Telefon normalizasyonu (`+` ve rakamlar, max 16 karakter)
   - Şifre gücü ve şifre tekrarı eşleşmesi
3. `sendForm()` fonksiyonu `fetch('backend/register.php', ...)` ile isteği gönderir:
   - **Method:** `POST`
   - **Credentials:** `credentials: 'same-origin'`
   - **Headers:** `Content-Type: application/json`, `Accept: application/json`
   - **Body:** `first_name`, `last_name`, `birth_date`, `gender`, `nationality`, `national_id`, `email`, `phone`, `password`, `password_confirm` alanlarını içeren JSON.

### B. Arka Yüz (`backend/register.php`)
1. `chnRequireJsonPost()`:
   - HTTP metodunun `POST` olduğunu doğrular (değilse `405 METHOD_NOT_ALLOWED`).
   - `php://input` üzerinden ham JSON gövdesini `json_decode(..., true)` ile çözer (geçersizse `400 INVALID_JSON`).
2. **Sunucu Taraflı Karakter Seti ve Format Doğrulaması (`backend/validation.php`):**
   - Frontend doğrulaması atlatılsa bile backend tüm alanları bağımsız doğrular.
   - `CHN_ALLOWED_LETTERS` (`A-Za-zçÇğĞıİöÖşŞüÜ`) sabiti ile yalnızca İngilizce ve Türkçe Latin harflere + alan bazlı izinli noktalama işaretlerine izin verilir.
   - Geçersiz veri varsa veritabanına dokunmadan `400 INVALID_INPUT` döner.
3. **Tekrarlı E-posta Kontrolü:**
   - `backend/db.php` içindeki `chnGetPdo()` ile MySQL'e Prepared Statement (`SELECT user_id FROM user WHERE email = :email LIMIT 1`) atılır.
   - Kayıt varsa `409 EMAIL_EXISTS` döner.
4. **Şifre Hashleme & Doğrulama Token'ı:**
   - Şifre `password_hash($password, PASSWORD_DEFAULT)` ile tek yönlü hashlenir; düz şifre asla saklanmaz.
   - `bin2hex(random_bytes(32))` ile 64 karakterlik kriptografik olarak rastgele bir `verify_token` üretilir.
5. **PDO Transaction ile Çift Tablo Kaydı:**
   - İki tabloya yazılacağı için `$pdo->beginTransaction()` başlatılır:
     1. `user` tablosuna `role = 'patient'`, `verify = 0`, `verify_token = <64-char hex>` değerleriyle ekleme yapılır. (Public kayıt sadece `patient` rolü açar.)
     2. `$pdo->lastInsertId()` ile oluşan `user_id` alınır ve `patient` tablosuna `(user_id)` eklenir.
   - Her ikisi de başarılıysa `$pdo->commit()`, hata olursa `$pdo->rollBack()` çalışır ve hata `logs/error.log` dosyasına yazılır (`500 SERVER_ERROR`).
6. **Doğrulama Maili Gönderimi (`backend/mailer.php`):**
   - `APP_URL` (`.env` içinden) + `/verify-acc.php?token=<verify_token>` linki hazırlanır.
   - `sendEmail()` PHPMailer + SMTP (`STARTTLS`) üzerinden HTML e-postayı gönderir.
7. Frontend'e `201 Created` ile `{"ok": true, "data": {}, "message": "..."}` döner. `js/auth.js` formu gizleyip başarı kutusunu (`#register-success`) gösterir.

---

## 3. E-posta Doğrulama Akışı (`verify-acc.php`)

1. Kullanıcı e-postasındaki `http://localhost/CHN/verify-acc.php?token=...` linkine tıklar (`GET` isteği).
2. `verify-acc.php`, token'ın 64 karakterlik hex formatında (`^[a-f0-9]{64}$`) olduğunu kontrol eder.
3. `SELECT user_id, verify FROM user WHERE verify_token = :token LIMIT 1` sorgusu çalıştırılır.
4. Eşleşen kullanıcı bulunursa:
   - `UPDATE user SET verify = 1, verify_token = NULL WHERE user_id = :user_id` ile hesap doğrulanır ve token tek kullanımlık olacak şekilde silinir.
   - Ekranda doğrulama başarılı mesajı ve `login.php` butonu gösterilir.
5. Token geçersizse veya daha önce kullanılmışsa (`verify_token` artık `NULL` olduğu için) hata mesajı gösterilir.

---

## 4. Giriş Akışı (Login Cycle)

### A. Ön Yüz (`login.php` + `js/auth.js`)
1. `initLoginForm()` e-posta ve şifrenin boş olmadığını kontrol eder.
2. `fetch('backend/login.php', { method: 'POST', credentials: 'same-origin', ... })` ile JSON gönderir.

### B. Arka Yüz (`backend/login.php`)
1. `chnStartSession()` çağrılır, ardından `chnRequireJsonPost()` ile JSON gövdesi alınır.
2. E-posta formatı geçersiz veya şifre boşsa `422 INVALID_INPUT` döner.
3. **Brute-Force Koruması & Yarış Durumu (Race Condition) Engeli:**
   - `$pdo->beginTransaction()` başlatılır ve `SELECT ... FROM user WHERE email = :email LIMIT 1 FOR UPDATE` çalıştırılır.
   - `FOR UPDATE` satırı transaction bitene kadar kilitler; eşzamanlı paralel istekler sıraya girer ve 5 deneme limiti aşılamaz.
4. **Kontrol Sırası:**
   - **Kullanıcı yoksa:** Transaction kapatılır ve `401 INVALID_CREDENTIALS` döner.
   - **Hesap zaten kilitliyse (`locked_until > şimdi`):** Transaction kapatılır ve `429 ACCOUNT_LOCKED` döner.
   - **Şifre yanlışsa (`!password_verify(...)`):**
     - `failed_attempts` 1 artırılır.
     - `failed_attempts >= 5` (`LOGIN_MAX_ATTEMPTS`) olduysa `locked_until` şu an + 15 dakika (`LOGIN_LOCKOUT_TIME = 900` sn) olarak güncellenir, `commit()` sonrası `429 ACCOUNT_LOCKED` döner.
     - 5'in altındaysa `commit()` sonrası `401 INVALID_CREDENTIALS` döner.
   - **Şifre doğru ama `verify !== 1` ise:**
     - Transaction kapatılır ve `403 ACCOUNT_NOT_VERIFIED` döner. (`js/auth.js` bunu sarı uyarı kutusu olarak gösterir.)
   - **Şifre doğru VE `verify === 1` ise:**
     - `failed_attempts = 0` ve `locked_until = NULL` yapılır, `$pdo->commit()` ile kilit açılır.
5. **Session Oluşturma ve Yanıt:**
   - `session_regenerate_id(true)` çağrılır (eski session ID'yi iptal edip yeni ID üretir — *Session Fixation* saldırısını önler).
   - `$_SESSION` içine şu veriler yazılır:
     - `user_id`
     - `role`
     - `name`
     - `surname`
     - `last_activity = time()`
   - Sunucu `200 OK` ile `{"ok": true, "data": {"redirect": "pages/dashboard-test.php?role=<rol>"}, "message": "Logged in."}` döner.
6. `js/auth.js` yanıtı alınca `safeRedirect()` ile URL'nin aynı origin'de olduğunu doğrular ve `window.location.assign(target)` ile kullanıcıyı dashboard'a yönlendirir.

---

## 5. Cookie ve Session Mimarisi

### A. PHP Session Cookie'si (`PHPSESSID`)
`backend/auth.php` içindeki `chnStartSession()` tarafından şu ayarlarla yönetilir:
- **`session.use_strict_mode = 1`:** Sunucu, kendi üretmediği bir `PHPSESSID` değerini kabul etmez; geçersiz/bilinmeyen bir cookie gelirse yeni ve temiz bir session ID üretir.
- **`lifetime = 0` (Session Cookie):** Tarayıcı tamamen kapatıldığında silinir (*Remember Me* kapalıdır).
- **`path = '/'`:** Sitenin tüm alt yollarında geçerlidir.
- **`httponly = true`:** JavaScript (`document.cookie`) bu cookie'yi okuyamaz; XSS saldırılarında session çalınmasını engeller.
- **`samesite = 'Lax'`:** Üçüncü parti sitelerden yapılan `POST` / `fetch` isteklerinde cookie gönderilmez (CSRF koruması).
- **`secure = true` (HTTPS ise):** Yerel `http://localhost` üzerinde `false`, canlı HTTPS ortamında otomatik `true` olur.

### B. Sunucu Tarafı Session Verisi ve 30 Dakika İnaktivite Zaman Aşımı
- Kullanıcının `user_id`, `role`, `name`, `surname` bilgileri **cookie içinde değil**, sunucu tarafında PHP'nin session deposunda (`$_SESSION`) tutulur; tarayıcıya sadece rastgele `PHPSESSID` anahtarı gönderilir.
- Her `chnStartSession()` çağrısında:
  1. `time() - $_SESSION['last_activity'] > 1800` (`SESSION_LIFETIME = 1800` sn = 30 dk) ise `chnDestroySession()` çağrılır:
     - `$_SESSION = []` ile RAM'deki dizi sıfırlanır.
     - `setcookie(session_name(), '', ['expires' => time() - 42000, ...])` ile tarayıcıdaki `PHPSESSID` cookie'si silinir.
     - `session_destroy()` ile sunucudaki session dosyası silinir.
     - Ardından temiz bir boş session başlatılır; kullanıcı artık giriş yapmamış sayılır.
  2. Süre dolmamışsa `$_SESSION['last_activity'] = time()` güncellenir (kayan 30 dakikalık pencere).

### C. Dil Tercihi Cookie'si (`chn_lang`)
- `include/i18n.php` tarafından `chn_lang` (`en`, `tr`, `ru`) kalıcı cookie'de tutulur.
- Aktif bir oturum varsa `chnStartSession()` bu cookie değerini `$_SESSION['lang']` ile senkronize eder.

### D. Korumalı Sayfa ve API Yetkilendirmesi (`requireRole()`)
- `backend/auth.php` içinde tanımlı `requireRole(array $allowedRoles, bool $isApi = true)`:
  - Oturum yoksa: API ise `401 UNAUTHORIZED` JSON, HTML sayfası ise `login.php`'ye yönlendirme yapar.
  - Oturum var ama rol yetkisizse: API ise `403 FORBIDDEN` JSON, HTML sayfası ise kullanıcının kendi rolünün dashboard'una yönlendirme yapar.
