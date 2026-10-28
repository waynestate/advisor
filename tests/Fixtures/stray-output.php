<?php

// Run as a child process by StdioProcessTest: a protocol line must survive, stray output must not.
require __DIR__ . '/../../vendor/autoload.php';

Waynestate\Advisor\Server\StdoutGuard::install();

echo "stray echo\n";
trigger_error('stray warning', E_USER_WARNING);
fwrite(STDOUT, '{"ok":true}' . "\n");
