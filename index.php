<?php
/**
 * GEÇİCİ YÖNLENDİRME (TEMPORARY REDIRECT)
 * -----------------------------------------------------------------------------
 * NOT: Bu dosya (index.php) henüz implemente edilmemiştir.
 * Asıl oturum kontrolü ve dashboard yönlendirmesi kurulana kadar, bu dosyaya 
 * gelen tüm istekler geçici olarak varsayılan XAMPP root dosya gezginine 
 * (Index of /CHN) yönlendirilmektedir.
 * 
 * index.php asıl yapısıyla implemente edildiğinde .htaccess içerisindeki 
 * geçici kural kaldırılarak bu dosya devreye alınacaktır.
 * -----------------------------------------------------------------------------
 */

declare(strict_types=1);

// Doğrudan index.php çağrıldığında varsayılan XAMPP dosya gezginine (Index of /CHN) yönlendir:
header('Location: ./');
exit;
