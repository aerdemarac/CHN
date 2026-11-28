<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

chnStartSession();
chnRequireJsonPost();
chnClearLastAccountCookie();

chnJsonSuccess([], 'Saved device account reference cleared.', 200);
