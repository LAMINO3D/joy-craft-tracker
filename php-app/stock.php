<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/helpers.php';

$user     = require_auth('stock');
$title    = 'Stock & Achats';
$subtitle = 'Fournitures, mouvements et fournisseurs';
$active   = 'stock';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'add_fournisseur') {
        $nom = post('nom');
        if ($nom === '') { flash('Le nom du fournisseur est obligatoire.', 'error'); redirect('stock.php?tab=fournisseurs'); }
        q('INSERT INTO fournisseurs (nom,telephone,adresse,specialite,conditions_paiement) VALUES (?,?,?,?,?)',
            [$nom, post('telephone') ?: null, post('adresse') ?: null, post('specialite') ?: null, post('conditions_paiement') ?: null]);
        audit('creation', 'fournisseur', (int) db()->lastInsertId(), $nom);
        flash('Fournisseur ajoute.');
        redirect('stock.php?tab=fournisseurs');
    }

    if ($action === 'add_fourniture') {
        $nom = post('nom');
        if ($nom === '') { flash('Le nom de la fourniture est obligatoire.', 'error'); redirect('stock.php'); }
        $qte = postf('quantite');
        q('INSERT INTO fournitures (fournisseur_id,nom,type,unite,quantite,seuil_critique,prix_unitaire) VALUES (?,?,?,?,?,?,?)',
            [posti('fournisseur_id'), $nom, post('type') ?: null, post('unite') ?: 'pcs', 0, postf('seuil_critique', 3), postf('prix_unitaire')]);
        $id = (int) db()->lastInsertId();
        if ($qte > 0) {
            q('INSERT INTO mouvements_stock (fourniture_id,type,quantite,motif,utilisateur_id) VALUES (?,?,?,?,?)', [$id, 'entree', $qte, 'Stock initial', $user['id']]);
        }
        recalc_stock($id);
        audit('creation', 'fourniture', $id, $nom);
        flash('Fourniture ajoutee.');
        redirect('stock.php');
    }

    if ($action === 'maj_fourniture') {
        $id = posti('id', 0);
        q('UPDATE fournitures SET seuil_critique=?, prix_unitaire=? WHERE id=?', [postf('seuil_critique', 3), postf('prix_unitaire'), $id]);
        audit('modification', 'fourniture', $id);
        flash('Fourniture mise a jour.');
        redirect('stock.php');
    }

    if ($action === 'delete_fourniture') {
        if ($user['role'] !== 'admin') { flash('Seul un administrateur peut supprimer une fourniture.', 'error'); redirect('stock.php'); }
        $id = posti('id', 0);
        q('DELETE FROM fournitures WHERE id=?', [$id]);
        audit('suppression', 'fourniture', $id);
        flash('Fourniture supprimee.');
        redirect('stock.php');
    }

    if ($action === 'mouvement') {
        $fid = posti('fourniture_id', 0);
        $type = post('type') === 'sortie' ? 'sortie' : 'entree';
        $qte = postf('quantite');
        $f = fetchOne('SELECT * FROM fournitures WHERE id=?', [$fid]);
        if (!$f || $qte <= 0) {
            flash('Fourniture ou quantite invalide.', 'error');
        } elseif ($type === 'sortie' && $qte > (float) $f['quantite']) {
            flash('Stock insuffisant : il reste ' . qty($f['quantite']) . ' ' . $f['unite'] . ' de « ' . $f['nom'] . ' ».', 'error');
        } else {
            q('INSERT INTO mouvements_stock (fourniture_id,type,quantite,motif,utilisateur_id) VALUES (?,?,?,?,?)', [$fid, $type, $qte, post('motif') ?: null, $user['id']]);
            recalc_stock($fid);
            audit($type, 'stock', $fid, $f['nom'] . ' x' . qty($qte));
            $f = fetchOne('SELECT * FROM fournitures WHERE id=?', [$fid]);
            $msg = ($type === 'entree' ? 'Entree' : 'Sortie') . ' enregistree. Stock actuel : ' . qty($f['quantite']) . ' ' . $f['unite'] . '.';
            flash($msg, (float) $f['quantite'] <= (float) $f['seuil_critique'] ? 'info' : 'success');
        }
        redirect('stock.php?tab=mouvements');
    }
}

$tab = get('tab', 'fournitures');
$fournisseurs = fetchAll('SELECT * FROM fournisseurs ORDER BY nom');
$fournitures  = fetchAll('SELECT f.*, fo.nom AS fournisseur FROM fournitures f LEFT JOIN fournisseurs fo ON fo.id=f.fournisseur_id ORDER BY (f.quantite<=f.seuil_critique) DESC, f.nom');
$mouvements   = fetchAll('SELECT m.*, f.nom AS fourniture, f.unite, u.prenom, u.nom AS unom FROM mouvements_stock m JOIN fournitures f ON f.id=m.fourniture_id LEFT JOIN utilisateurs u ON u.id=m.utilisateur_id ORDER BY m.id DESC LIMIT 100');
$valeur = 0.0;
foreach ($fournitures as $f) { $valeur += (float) $f['quantite'] * (float) $f['prix_unitaire']; }
$alertes = array_filter($fournitures, fn($f) => (float) $f['quantite'] <= (float) $f['seuil_critique']);

require __DIR__ . '/partials/header.php';
?>

<div class="grid grid--3">
  <div class="kpi"><div class="kpi__label">References en stock</div><div class="kpi__value"><?= count($fournitures) ?></div></div>
  <div class="kpi"><div class="kpi__label">Valeur du stock</div><div class="kpi__value"><?= money($valeur) ?></div></div>
  <div class="kpi <?= $alertes ? 'kpi--bad' : 'kpi--ok' ?>" id="alertes"><div class="kpi__label">Sous le seuil critique</div><div class="kpi__value"><?= count($alertes) ?></div></div>
</div>

<div class="tabs" data-tabs="stock" style="margin-top:20px">
  <button class="tab<?= $tab === 'fournitures' ? ' is-active' : '' ?>" data-tab="fournitures">Fournitures</button>
  <button class="tab<?= $tab === 'mouvements' ? ' is-active' : '' ?>" data-tab="mouvements">Entrees / Sorties</button>
  <button class="tab<?= $tab === 'fournisseurs' ? ' is-active' : '' ?>" data-tab="fournisseurs">Fournisseurs</button>
</div>

<!-- ===== Fournitures ===== -->
<div data-panel="fournitures" data-panel-group="stock" <?= $tab !== 'fournitures' ? 'hidden' : '' ?>>
  <section class="card">
    <div class="card__head"><h2>Nouvelle fourniture</h2></div>
    <form method="post" class="form form--inline">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_fourniture">
      <div class="field"><label>Designation *</label><input name="nom" required placeholder="Planche chene 200x50"></div>
      <div class="field"><label>Type</label><input name="type" placeholder="Bois, Fer, Finition..."></div>
      <div class="field"><label>Unite</label><input name="unite" value="pcs"></div>
      <div class="field"><label>Fournisseur</label><select name="fournisseur_id"><option value="">—</option><?php foreach ($fournisseurs as $fo): ?><option value="<?= $fo['id'] ?>"><?= e($fo['nom']) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>Quantite initiale</label><input name="quantite" type="number" step="0.01" min="0" value="0"></div>
      <div class="field"><label>Seuil critique</label><input name="seuil_critique" type="number" step="0.01" min="0" value="3"></div>
      <div class="field"><label>Prix unitaire (<?= APP_CURRENCY ?>)</label><input name="prix_unitaire" type="number" step="0.001" min="0" value="0"></div>
      <div class="field"><button class="btn btn--primary">Ajouter</button></div>
    </form>
  </section>

  <section class="card">
    <div class="card__head"><h2>Inventaire</h2><input data-filter="#tbl-four" placeholder="Rechercher..." style="max-width:260px"></div>
    <div class="table-wrap"><table id="tbl-four">
      <thead><tr><th>Designation</th><th>Type</th><th>Fournisseur</th><th class="num">En stock</th><th class="num">Seuil</th><th class="num">P.U.</th><th class="num">Valeur</th><th></th></tr></thead>
      <tbody>
      <?php if (!$fournitures): ?><tr><td colspan="8" class="empty">Aucune fourniture.</td></tr><?php endif; ?>
      <?php foreach ($fournitures as $f): $low = (float) $f['quantite'] <= (float) $f['seuil_critique']; ?>
        <tr class="<?= $low ? 'low' : '' ?>">
          <td><strong><?= e($f['nom']) ?></strong></td>
          <td><?= e($f['type'] ?? '-') ?></td>
          <td><?= e($f['fournisseur'] ?? '-') ?></td>
          <td class="num <?= $low ? 'stock-low' : '' ?>"><?= $low ? '&#9888; ' : '' ?><?= qty($f['quantite']) ?> <?= e($f['unite']) ?></td>
          <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="maj_fourniture"><input type="hidden" name="id" value="<?= $f['id'] ?>">
          <td><input type="number" step="0.01" name="seuil_critique" value="<?= e($f['seuil_critique']) ?>" style="width:90px"></td>
          <td><input type="number" step="0.001" name="prix_unitaire" value="<?= e($f['prix_unitaire']) ?>" style="width:110px"></td>
          <td class="num"><?= money((float) $f['quantite'] * (float) $f['prix_unitaire']) ?></td>
          <td class="right"><div class="actions" style="justify-content:flex-end;flex-wrap:nowrap"><button class="btn btn--light btn--sm">OK</button>
          </form>
          <?php if ($user['role'] === 'admin'): ?>
            <form method="post" class="inline-form" data-confirm="Supprimer cette fourniture et son historique ?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_fourniture"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn btn--danger btn--sm">Suppr.</button></form>
          <?php endif; ?></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
</div>

<!-- ===== Mouvements ===== -->
<div data-panel="mouvements" data-panel-group="stock" <?= $tab !== 'mouvements' ? 'hidden' : '' ?>>
  <section class="card">
    <div class="card__head"><h2>Enregistrer un mouvement</h2></div>
    <form method="post" class="form form--inline">
      <?= csrf_field() ?><input type="hidden" name="action" value="mouvement">
      <div class="field"><label>Type</label><select name="type"><option value="entree">Entree (achat / reception)</option><option value="sortie">Sortie (atelier / ouvrier)</option></select></div>
      <div class="field"><label>Fourniture</label><select name="fourniture_id" required><?php foreach ($fournitures as $f): ?><option value="<?= $f['id'] ?>"><?= e($f['nom']) ?> (<?= qty($f['quantite']) ?> <?= e($f['unite']) ?>)</option><?php endforeach; ?></select></div>
      <div class="field"><label>Quantite</label><input name="quantite" type="number" step="0.01" min="0.01" required></div>
      <div class="field"><label>Motif / ouvrier</label><input name="motif" placeholder="Karim - table 50x50"></div>
      <div class="field"><button class="btn btn--teal">Valider</button></div>
    </form>
    <p class="muted" style="margin:10px 0 0;font-size:13px">Une sortie superieure au stock disponible est refusee automatiquement.</p>
  </section>

  <section class="card">
    <div class="card__head"><h2>Historique (100 derniers)</h2><input data-filter="#tbl-mvt" placeholder="Rechercher..." style="max-width:260px"></div>
    <div class="table-wrap"><table id="tbl-mvt">
      <thead><tr><th>Date</th><th>Type</th><th>Fourniture</th><th class="num">Quantite</th><th>Motif</th><th>Par</th></tr></thead>
      <tbody>
      <?php if (!$mouvements): ?><tr><td colspan="6" class="empty">Aucun mouvement.</td></tr><?php endif; ?>
      <?php foreach ($mouvements as $m): ?>
        <tr>
          <td class="mono"><?= date('d/m/Y H:i', strtotime($m['cree_le'])) ?></td>
          <td><?= $m['type'] === 'entree' ? '<span class="pill pill--ok">Entree</span>' : '<span class="pill pill--warn">Sortie</span>' ?></td>
          <td><?= e($m['fourniture']) ?></td>
          <td class="num"><?= $m['type'] === 'entree' ? '+' : '-' ?><?= qty($m['quantite']) ?> <?= e($m['unite']) ?></td>
          <td><?= e($m['motif'] ?? '-') ?></td>
          <td><?= e(trim(($m['prenom'] ?? '') . ' ' . ($m['unom'] ?? '')) ?: '-') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
</div>

<!-- ===== Fournisseurs ===== -->
<div data-panel="fournisseurs" data-panel-group="stock" <?= $tab !== 'fournisseurs' ? 'hidden' : '' ?>>
  <section class="card">
    <div class="card__head"><h2>Nouveau fournisseur</h2></div>
    <form method="post" class="form form--inline">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_fournisseur">
      <div class="field"><label>Nom *</label><input name="nom" required></div>
      <div class="field"><label>Telephone</label><input name="telephone"></div>
      <div class="field"><label>Adresse</label><input name="adresse"></div>
      <div class="field"><label>Specialite</label><input name="specialite"></div>
      <div class="field"><label>Conditions de paiement</label><input name="conditions_paiement" placeholder="30 jours, comptant..."></div>
      <div class="field"><button class="btn btn--primary">Ajouter</button></div>
    </form>
  </section>
  <section class="card">
    <div class="card__head"><h2>Fournisseurs</h2></div>
    <div class="table-wrap"><table>
      <thead><tr><th>Nom</th><th>Telephone</th><th>Adresse</th><th>Specialite</th><th>Paiement</th><th class="num">Fournitures</th></tr></thead>
      <tbody>
      <?php if (!$fournisseurs): ?><tr><td colspan="6" class="empty">Aucun fournisseur.</td></tr><?php endif; ?>
      <?php foreach ($fournisseurs as $fo): ?>
        <tr><td><strong><?= e($fo['nom']) ?></strong></td><td><?= e($fo['telephone'] ?? '-') ?></td><td><?= e($fo['adresse'] ?? '-') ?></td><td><?= e($fo['specialite'] ?? '-') ?></td><td><?= e($fo['conditions_paiement'] ?? '-') ?></td>
          <td class="num"><?= count(array_filter($fournitures, fn($f) => (int) $f['fournisseur_id'] === (int) $fo['id'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
