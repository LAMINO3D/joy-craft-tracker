<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/helpers.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = post('email');
    $pass  = (string) ($_POST['password'] ?? '');
    if ($email === '' || $pass === '') {
        $error = 'Veuillez saisir votre e-mail et votre mot de passe.';
    } elseif (login_user($email, $pass)) {
        redirect('index.php');
    } else {
        $error = 'Identifiants incorrects ou compte desactive.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion · <?= APP_NAME ?></title>
<meta name="description" content="Connexion au systeme de gestion SMT DU SAHEL.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="centered">
  <form class="card card--auth" method="post" action="login.php" autocomplete="on">
    <?= csrf_field() ?>
    <div class="auth__brand">
      <div class="brand__mark">SMT</div>
      <div>
        <strong><?= APP_NAME ?></strong>
        <span><?= APP_BASELINE ?></span>
      </div>
    </div>
    <h1 class="h2">Connexion</h1>
    <p class="muted" style="margin-top:0">Accedez a votre espace selon votre role.</p>

    <?php if ($error): ?><div class="flash flash--error"><?= e($error) ?></div><?php endif; ?>

    <div class="form">
      <div class="field">
        <label for="email">Adresse e-mail</label>
        <input id="email" name="email" type="email" required autofocus value="<?= e(post('email')) ?>" placeholder="vous@smtdusahel.tn">
      </div>
      <div class="field">
        <label for="password">Mot de passe</label>
        <input id="password" name="password" type="password" required placeholder="••••••••">
      </div>
      <button class="btn btn--primary btn--block" type="submit">Se connecter</button>
    </div>

    <div class="demo">
      <strong>Comptes de demonstration</strong> (mot de passe : <code>Smt2026!</code>)<br>
      <code>admin@smtdusahel.tn</code> · <code>achats@…</code> · <code>stock@…</code> · <code>rh@…</code> · <code>commercial@…</code>
    </div>
  </form>
</body>
</html>
