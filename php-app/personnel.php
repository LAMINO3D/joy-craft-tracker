<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/helpers.php';

$user     = require_auth('personnel');
$title    = 'Personnel & Paie';
$subtitle = 'Employes, presences et calcul des salaires';
$active   = 'personnel';

/* ---------------- Actions ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'add_employe') {
        $nom = post('nom'); $prenom = post('prenom');
        if ($nom === '' || $prenom === '') {
            flash('Nom et prenom obligatoires.', 'error');
        } else {
            q('INSERT INTO employes (nom,prenom,poste,telephone,salaire_base,date_embauche) VALUES (?,?,?,?,?,?)',
                [$nom, $prenom, post('poste') ?: null, post('telephone') ?: null, postf('salaire_base'), post('date_embauche') ?: null]);
            audit('creation', 'employe', (int) db()->lastInsertId(), "$prenom $nom");
            flash('Employe ajoute.');
        }
        redirect('personnel.php?tab=employes');
    }

    if ($action === 'toggle_employe') {
        $id = posti('id', 0);
        q('UPDATE employes SET actif = 1 - actif WHERE id = ?', [$id]);
        audit('activation', 'employe', $id);
        flash('Statut de l\'employe modifie.');
        redirect('personnel.php?tab=employes');
    }

    if ($action === 'save_presences') {
        $date = post('date_jour') ?: date('Y-m-d');
        $statuts = $_POST['statut'] ?? [];
        $arr = $_POST['heure_arrivee'] ?? [];
        $dep = $_POST['heure_depart'] ?? [];
        foreach ($statuts as $empId => $st) {
            $empId = (int) $empId;
            if (!in_array($st, ['present', 'absent', 'conge', 'retard'], true)) continue;
            q('INSERT INTO presences (employe_id,date_jour,statut,heure_arrivee,heure_depart) VALUES (?,?,?,?,?)
               ON DUPLICATE KEY UPDATE statut=VALUES(statut), heure_arrivee=VALUES(heure_arrivee), heure_depart=VALUES(heure_depart)',
                [$empId, $date, $st, ($arr[$empId] ?? '') ?: null, ($dep[$empId] ?? '') ?: null]);
        }
        audit('pointage', 'presence', null, $date);
        flash('Presences enregistrees pour le ' . dmy($date) . '.');
        redirect('personnel.php?tab=presences&date=' . urlencode($date));
    }

    if ($action === 'generer_paie') {
        $mois = max(1, min(12, posti('mois', (int) date('n'))));
        $annee = posti('annee', (int) date('Y'));
        $jo = max(1, posti('jours_ouvrables', 26));
        $debut = sprintf('%04d-%02d-01', $annee, $mois);
        $fin = date('Y-m-t', strtotime($debut));
        $emps = fetchAll('SELECT * FROM employes WHERE actif=1');
        foreach ($emps as $emp) {
            $jt = (int) fetchValue("SELECT COUNT(*) FROM presences WHERE employe_id=? AND date_jour BETWEEN ? AND ? AND statut IN ('present','retard')", [$emp['id'], $debut, $fin]);
            $conges = (int) fetchValue("SELECT COUNT(*) FROM presences WHERE employe_id=? AND date_jour BETWEEN ? AND ? AND statut='conge'", [$emp['id'], $debut, $fin]);
            $jtTotal = min($jo, $jt + $conges); // conges payes
            $salaire = round((float) $emp['salaire_base'] / $jo * $jtTotal, 3);
            q('INSERT INTO paies (employe_id,mois,annee,jours_ouvrables,jours_travailles,salaire_calcule,net_a_payer)
               VALUES (?,?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE jours_ouvrables=VALUES(jours_ouvrables), jours_travailles=VALUES(jours_travailles),
                 salaire_calcule=VALUES(salaire_calcule), net_a_payer=salaire_calcule+primes-deductions',
                [$emp['id'], $mois, $annee, $jo, $jtTotal, $salaire, $salaire]);
        }
        audit('generation', 'paie', null, "$mois/$annee");
        flash('Paie generee pour ' . sprintf('%02d/%04d', $mois, $annee) . '.');
        redirect("personnel.php?tab=paie&mois=$mois&annee=$annee");
    }

    if ($action === 'maj_paie') {
        $id = posti('id', 0);
        $primes = postf('primes'); $ded = postf('deductions');
        $paye = post('date_paiement') ?: null;
        q('UPDATE paies SET primes=?, deductions=?, net_a_payer=salaire_calcule+?-?, date_paiement=? WHERE id=?', [$primes, $ded, $primes, $ded, $paye, $id]);
        audit('modification', 'paie', $id);
        flash('Fiche de paie mise a jour.');
        redirect('personnel.php?tab=paie&mois=' . posti('mois', (int) date('n')) . '&annee=' . posti('annee', (int) date('Y')));
    }
}

/* ---------------- Donnees ---------------- */
$tab      = get('tab', 'employes');
$employes = fetchAll('SELECT * FROM employes ORDER BY actif DESC, nom, prenom');
$datePres = get('date', date('Y-m-d'));
$presences = [];
foreach (fetchAll('SELECT * FROM presences WHERE date_jour=?', [$datePres]) as $p) {
    $presences[(int) $p['employe_id']] = $p;
}
$mois  = (int) get('mois', date('n'));
$annee = (int) get('annee', date('Y'));
$paies = fetchAll('SELECT p.*, e.nom, e.prenom, e.poste, e.salaire_base FROM paies p JOIN employes e ON e.id=p.employe_id WHERE p.mois=? AND p.annee=? ORDER BY e.nom', [$mois, $annee]);
$masse = array_sum(array_column($paies, 'net_a_payer'));

require __DIR__ . '/partials/header.php';
?>

<div class="tabs" data-tabs="perso">
  <button class="tab<?= $tab === 'employes' ? ' is-active' : '' ?>" data-tab="employes">Employes</button>
  <button class="tab<?= $tab === 'presences' ? ' is-active' : '' ?>" data-tab="presences">Presences</button>
  <button class="tab<?= $tab === 'paie' ? ' is-active' : '' ?>" data-tab="paie">Paie</button>
</div>

<!-- ===== Employes ===== -->
<div data-panel="employes" data-panel-group="perso" <?= $tab !== 'employes' ? 'hidden' : '' ?>>
  <section class="card">
    <div class="card__head"><h2>Nouvel employe</h2></div>
    <form method="post" class="form form--inline">
      <?= csrf_field() ?><input type="hidden" name="action" value="add_employe">
      <div class="field"><label>Nom *</label><input name="nom" required></div>
      <div class="field"><label>Prenom *</label><input name="prenom" required></div>
      <div class="field"><label>Poste</label><input name="poste" placeholder="Menuisier, soudeur..."></div>
      <div class="field"><label>Telephone</label><input name="telephone"></div>
      <div class="field"><label>Salaire de base (<?= APP_CURRENCY ?>)</label><input name="salaire_base" type="number" step="0.001" min="0" value="0"></div>
      <div class="field"><label>Date d'embauche</label><input name="date_embauche" type="date"></div>
      <div class="field"><button class="btn btn--primary" type="submit">Ajouter</button></div>
    </form>
  </section>

  <section class="card">
    <div class="card__head"><h2>Liste du personnel</h2><input data-filter="#tbl-emp" placeholder="Rechercher..." style="max-width:260px"></div>
    <div class="table-wrap"><table id="tbl-emp">
      <thead><tr><th>Nom</th><th>Poste</th><th>Telephone</th><th class="num">Salaire base</th><th>Embauche</th><th>Statut</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($employes as $e): ?>
        <tr>
          <td><strong><?= e($e['prenom'] . ' ' . $e['nom']) ?></strong></td>
          <td><?= e($e['poste'] ?? '-') ?></td>
          <td><?= e($e['telephone'] ?? '-') ?></td>
          <td class="num"><?= money($e['salaire_base']) ?></td>
          <td><?= dmy($e['date_embauche']) ?></td>
          <td><?= $e['actif'] ? '<span class="pill pill--ok">Actif</span>' : '<span class="pill pill--muted">Inactif</span>' ?></td>
          <td class="right">
            <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_employe"><input type="hidden" name="id" value="<?= $e['id'] ?>">
              <button class="btn btn--light btn--sm"><?= $e['actif'] ? 'Desactiver' : 'Reactiver' ?></button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
</div>

<!-- ===== Presences ===== -->
<div data-panel="presences" data-panel-group="perso" <?= $tab !== 'presences' ? 'hidden' : '' ?>>
  <section class="card">
    <div class="card__head">
      <h2>Pointage du jour</h2>
      <form method="get" class="actions"><input type="hidden" name="tab" value="presences">
        <input type="date" name="date" value="<?= e($datePres) ?>"><button class="btn btn--light">Charger</button></form>
    </div>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_presences"><input type="hidden" name="date_jour" value="<?= e($datePres) ?>">
      <div class="table-wrap"><table>
        <thead><tr><th>Employe</th><th>Statut</th><th>Arrivee</th><th>Depart</th></tr></thead>
        <tbody>
        <?php foreach ($employes as $e): if (!$e['actif']) continue; $p = $presences[(int) $e['id']] ?? null; ?>
          <tr>
            <td><strong><?= e($e['prenom'] . ' ' . $e['nom']) ?></strong><br><span class="muted"><?= e($e['poste'] ?? '') ?></span></td>
            <td><select name="statut[<?= $e['id'] ?>]">
              <?php foreach (['present', 'retard', 'absent', 'conge'] as $s): ?>
                <option value="<?= $s ?>" <?= ($p['statut'] ?? 'present') === $s ? 'selected' : '' ?>><?= statut_label($s) ?></option>
              <?php endforeach; ?></select></td>
            <td><input type="time" name="heure_arrivee[<?= $e['id'] ?>]" value="<?= e($p['heure_arrivee'] ? substr($p['heure_arrivee'], 0, 5) : '') ?>"></td>
            <td><input type="time" name="heure_depart[<?= $e['id'] ?>]" value="<?= e($p['heure_depart'] ? substr($p['heure_depart'], 0, 5) : '') ?>"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="actions" style="margin-top:14px"><button class="btn btn--primary">Enregistrer les presences</button></div>
    </form>
  </section>
</div>

<!-- ===== Paie ===== -->
<div data-panel="paie" data-panel-group="perso" <?= $tab !== 'paie' ? 'hidden' : '' ?>>
  <section class="card">
    <div class="card__head"><h2>Generer la paie du mois</h2></div>
    <form method="post" class="form form--inline">
      <?= csrf_field() ?><input type="hidden" name="action" value="generer_paie">
      <div class="field"><label>Mois</label><select name="mois"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>" <?= $m === $mois ? 'selected' : '' ?>><?= sprintf('%02d', $m) ?></option><?php endfor; ?></select></div>
      <div class="field"><label>Annee</label><input type="number" name="annee" value="<?= $annee ?>" min="2020" max="2100"></div>
      <div class="field"><label>Jours ouvrables</label><input type="number" name="jours_ouvrables" value="26" min="1" max="31"></div>
      <div class="field"><button class="btn btn--teal">Calculer</button></div>
    </form>
    <p class="muted" style="margin-bottom:0;font-size:13px">Salaire = salaire de base / jours ouvrables x jours travailles (presents + retards + conges payes). Les primes et deductions se saisissent ensuite ligne par ligne.</p>
  </section>

  <section class="card">
    <div class="card__head">
      <h2>Fiches de paie <?= sprintf('%02d/%04d', $mois, $annee) ?></h2>
      <form method="get" class="actions"><input type="hidden" name="tab" value="paie">
        <input type="number" name="mois" value="<?= $mois ?>" min="1" max="12" style="width:80px"><input type="number" name="annee" value="<?= $annee ?>" style="width:100px"><button class="btn btn--light">Afficher</button></form>
    </div>
    <div class="table-wrap"><table>
      <thead><tr><th>Employe</th><th class="num">Jours</th><th class="num">Salaire calcule</th><th class="num">Primes</th><th class="num">Deductions</th><th class="num">Net a payer</th><th>Paye le</th><th></th></tr></thead>
      <tbody>
      <?php if (!$paies): ?><tr><td colspan="8" class="empty">Aucune paie generee pour cette periode.</td></tr><?php endif; ?>
      <?php foreach ($paies as $p): ?>
        <tr>
          <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="maj_paie"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <input type="hidden" name="mois" value="<?= $mois ?>"><input type="hidden" name="annee" value="<?= $annee ?>">
          <td><strong><?= e($p['prenom'] . ' ' . $p['nom']) ?></strong><br><span class="muted"><?= e($p['poste'] ?? '') ?></span></td>
          <td class="num"><?= $p['jours_travailles'] ?>/<?= $p['jours_ouvrables'] ?></td>
          <td class="num"><?= money($p['salaire_calcule']) ?></td>
          <td><input type="number" step="0.001" name="primes" value="<?= e($p['primes']) ?>" style="width:110px"></td>
          <td><input type="number" step="0.001" name="deductions" value="<?= e($p['deductions']) ?>" style="width:110px"></td>
          <td class="num"><strong><?= money($p['net_a_payer']) ?></strong></td>
          <td><input type="date" name="date_paiement" value="<?= e($p['date_paiement'] ?? '') ?>" style="width:150px"></td>
          <td><button class="btn btn--light btn--sm">Enregistrer</button></td>
          </form>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($paies): ?><tfoot><tr><td colspan="5">Masse salariale nette</td><td class="num"><?= money($masse) ?></td><td colspan="2"></td></tr></tfoot><?php endif; ?>
    </table></div>
  </section>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
