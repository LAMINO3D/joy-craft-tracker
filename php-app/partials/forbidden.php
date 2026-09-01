<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/helpers.php';
$u = current_user();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acces refuse · <?= APP_NAME ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="centered">
  <div class="card card--auth">
    <h1 class="h2">Acces refuse</h1>
    <p class="muted">Votre role
      <strong><?= e(roles_labels()[$u['role']] ?? '-') ?></strong>
      ne donne pas acces a ce module. Contactez un administrateur.</p>
    <a class="btn btn--primary" href="index.php">Retour au tableau de bord</a>
  </div>
</body>
</html>
