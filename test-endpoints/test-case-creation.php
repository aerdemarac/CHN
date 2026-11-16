<?php
/** CLI launcher for the current creation/triage HTTP regression suite. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
if (getenv('CHN_CASE_DEV_MODE') !== '1') exit("Set CHN_CASE_DEV_MODE=1.\n");
passthru('python3 ' . escapeshellarg(__DIR__ . '/test-case-flows.py'), $status);
exit($status);
