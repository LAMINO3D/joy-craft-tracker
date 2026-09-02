<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/helpers.php';

$u = current_user();
if ($u) {
    audit('deconnexion', 'utilisateur', (int) $u['id'], $u['email']);
}
logout_user();
redirect('login.php');
