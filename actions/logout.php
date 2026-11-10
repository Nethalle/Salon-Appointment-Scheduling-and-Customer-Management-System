
<?php

require_once __DIR__ . '/../includes/auth.php';

require_post();
verify_csrf();

logout_user();

redirect('public/login.php');
