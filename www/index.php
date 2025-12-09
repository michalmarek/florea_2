<?php
header("X-Content-Type-Options: nosniff");
header("x-frame-options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer-when-downgrade");
header("strict-transport-security: max-age=31536000; includeSubDomains");

require_once("../app/bootstrap.php");
