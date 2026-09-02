<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/helpers.php';

$user     = require_auth('dashboard');
$title    = 'Tableau de bord';
$subtitle = 'Vue d\'ensemble de l\'activite';
$active   = 'dashboard';

$moisDebut = date('Y-m-01');
$caMois    = (float) fetchValue('SELECT COALESCE(SUM(total),0) FROM ventes WHERE date_vente >= ?', [$moisDebut]);
$nbVentes  = (int) fetchValue('SELECT COUNT(*) FROM ventes WHERE date_vente >= ?', [$moisDebut]);
$cmdCours  = (int) fetchValue("SELECT COUNT(*) FROM commandes WHERE statut IN ('en_attente','en_cours')");
$employes  = (int) fetchValue('SELECT COUNT(*) FROM employes WHERE actif=1');
$lowStock  = low_stock();
$valStock  = (float) fetchValue('SELECT COALESCE(SUM(quantite*prix_unitaire),0) FROM fournitures');
$presents  = (int) fetchValue("SELECT COUNT(*) FROM presences WHERE date_jour=CURDATE() AND statut IN ('present','retard')");

$dernieresVentes = fetchAll('SELECT v.*, c.nom AS client FROM ventes v LEFT JOIN clients c ON c.id=v.client_id ORDER BY v.id DESC LIMIT 6');
$dernieresCmd    = fetchAll('SELECT co.*, c.nom AS client, f.nom AS fournisseur FROM commandes co LEFT JOIN clients c ON c.id=co.client_id LEFT JOIN fournisseurs f ON f.id=co.fournisseur_id ORDER BY co.id DESC LIMIT 6');

require __DIR__ . '/partials/header.php';
?>

<div class="grid grid--4">
  <div class="kpi kpi--accent">
    <div class="kpi__label">Chiffre d'affaires du mois</div>
    <div class="kpi__value"><?= money($caMois) ?></div>
    <div class="kpi__sub"><?= $nbVentes ?> vente<?= $nbVentes > 1 ? 's' : '' ?> depuis le <?= dmy($moisDebut) ?></div>
  </div>
  <div class="kpi">
    <div class="kpi__label">Commandes en cours</div>
    <div class="kpi__value"><?= $cmdCours ?></div>
    <div class="kpi__sub">en attente ou en production</div>
  </div>
  <div class="kpi <?= $lowStock ? 'kpi--bad' : 'kpi--ok' ?>">
    <div class="kpi__label">Alertes de stock</div>
    <div class="kpi__value"><?= count($lowStock) ?></div>
    <div class="kpi__sub">valeur du stock : <?= money($valStock) ?></div>
  </div>
  <div class="kpi">
    <div class="kpi__label">Personnel</div>
    <div class="kpi__value"><?= $presents ?> / <?= $employes ?></div>
    <div class="kpi__sub">presents aujourd'hui</div>
  </div>
</div>

<div class="grid grid--2" style="margin-top:20px">
  <?php if (can('ventes', $user)): ?>
  <section class="card">
    <div class="card__head"><h2>Dernieres ventes</h2><a class="btn btn--light btn--sm" href="ventes.php">Voir tout</a></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Date</th><th>Client</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php if (!$dernieresVentes): ?><tr><td colspan="3" class="empty">Aucune vente enregistree.</td></tr><?php endif; ?>
      <?php foreach ($dernieresVentes as $v): ?>
        <tr><td><?= dmy($v['date_vente']) ?></td><td><?= e($v['client'] ?? 'Client de passage') ?></td><td class="num"><?= money($v['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>

  <?php if (can('commandes', $user)): ?>
  <section class="card">
    <div class="card__head"><h2>Dernieres commandes</h2><a class="btn btn--light btn--sm" href="commandes.php">Voir tout</a></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Ref.</th><th>Tiers</th><th>Statut</th><th class="num">Total</th></tr></thead>
      <tbody>
      <?php if (!$dernieresCmd): ?><tr><td colspan="4" class="empty">Aucune commande.</td></tr><?php endif; ?>
      <?php foreach ($dernieresCmd as $c): ?>
        <tr>
          <td class="mono"><?= e($c['reference']) ?></td>
          <td><?= e($c['type'] === 'client' ? ($c['client'] ?? '-') : ($c['fournisseur'] ?? '-')) ?></td>
          <td><?= pill($c['statut']) ?></td>
          <td class="num"><?= money($c['total']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>

  <?php if (can('stock', $user)): ?>
  <section class="card">
    <div class="card__head"><h2>Stock critique</h2><a class="btn btn--light btn--sm" href="stock.php">Gerer le stock</a></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Fourniture</th><th class="num">Quantite</th><th class="num">Seuil</th></tr></thead>
      <tbody>
      <?php if (!$lowStock): ?><tr><td colspan="3" class="empty">Aucune alerte, le stock est sain.</td></tr><?php endif; ?>
      <?php foreach ($lowStock as $f): ?>
        <tr class="low"><td><?= e($f['nom']) ?></td><td class="num stock-low">&#9888; <?= qty($f['quantite']) ?> <?= e($f['unite']) ?></td><td class="num"><?= qty($f['seuil_critique']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
