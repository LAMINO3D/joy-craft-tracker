<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/helpers.php';

/** @var array $user  defini par require_auth() dans la page appelante */
$user    = $user ?? current_user();
$active  = $active ?? '';
$title   = $title ?? APP_NAME;
$alerts  = can('stock', $user) ? count(low_stock()) : 0;
$flash   = flash();

$nav = [
    ['key' => 'dashboard', 'href' => 'index.php',     'label' => 'Tableau de bord', 'icon' => 'M3 12h4l3 8 4-16 3 8h4'],
    ['key' => 'personnel', 'href' => 'personnel.php', 'label' => 'Personnel & Paie', 'icon' => 'M16 21v-2a4 4 0 0 0-8 0v2M12 3a4 4 0 1 1 0 8 4 4 0 0 1 0-8z'],
    ['key' => 'stock',     'href' => 'stock.php',     'label' => 'Stock & Achats',   'icon' => 'M3 7l9-4 9 4-9 4-9-4zm0 5l9 4 9-4M3 17l9 4 9-4'],
    ['key' => 'ventes',    'href' => 'ventes.php',    'label' => 'Ventes',           'icon' => 'M3 3h2l3 12h11l2-8H7M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm9 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z'],
    ['key' => 'commandes', 'href' => 'commandes.php', 'label' => 'Commandes',        'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2'],
    ['key' => 'admin',     'href' => 'admin.php',     'label' => 'Administration',   'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.5 19.4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 14.6H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.6 8.5l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 4.6V4a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z'],
];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= APP_NAME ?></title>
<meta name="description" content="Systeme de gestion integre SMT DU SAHEL : personnel et paie, stock et achats, ventes, commandes et pilotage.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<a class="skip" href="#main">Aller au contenu</a>

<div class="shell">
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand__mark">SMT</div>
      <div class="brand__txt">
        <strong><?= APP_NAME ?></strong>
        <span><?= APP_BASELINE ?></span>
      </div>
    </div>

    <nav class="nav" aria-label="Modules">
      <?php foreach ($nav as $item): if (!can($item['key'], $user)) continue; ?>
        <a class="nav__link<?= $active === $item['key'] ? ' is-active' : '' ?>" href="<?= e($item['href']) ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= e($item['icon']) ?>"/></svg>
          <span><?= e($item['label']) ?></span>
          <?php if ($item['key'] === 'stock' && $alerts > 0): ?><em class="badge"><?= $alerts ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar__foot">
      <div class="who">
        <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['prenom'], 0, 1) . mb_substr($user['nom'], 0, 1))) ?></span>
        <div>
          <strong><?= e($user['prenom'] . ' ' . $user['nom']) ?></strong>
          <span><?= e(roles_labels()[$user['role']]) ?></span>
        </div>
      </div>
      <a class="btn btn--ghost btn--sm" href="logout.php">Se deconnecter</a>
    </div>
  </aside>

  <div class="content">
    <header class="topbar">
      <button class="burger" id="burger" aria-label="Ouvrir le menu">
        <span></span><span></span><span></span>
      </button>
      <div>
        <h1><?= e($title) ?></h1>
        <p class="topbar__sub"><?= e($subtitle ?? '') ?></p>
      </div>
      <div class="topbar__right">
        <?php if ($alerts > 0 && can('stock', $user)): ?>
          <a class="alertchip" href="stock.php#alertes"><?= $alerts ?> alerte<?= $alerts > 1 ? 's' : '' ?> de stock</a>
        <?php endif; ?>
        <span class="date"><?= date('d/m/Y') ?></span>
      </div>
    </header>

    <main class="main" id="main">
      <?php if ($flash): ?>
        <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
      <?php endif; ?>
