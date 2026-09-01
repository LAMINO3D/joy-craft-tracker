<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function money($v): string
{
    return number_format((float) $v, 3, ',', ' ') . ' ' . APP_CURRENCY;
}

function qty($v): string
{
    return rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
}

function dmy(?string $d): string
{
    if (!$d) {
        return '-';
    }
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function post(string $key, $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : (string) $default;
}

function postf(string $key, float $default = 0.0): float
{
    return (float) str_replace(',', '.', (string) ($_POST[$key] ?? $default));
}

function posti(string $key, ?int $default = null): ?int
{
    $v = $_POST[$key] ?? null;
    if ($v === null || $v === '') {
        return $default;
    }
    return (int) $v;
}

function get(string $key, $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : (string) $default;
}

function flash(?string $msg = null, string $type = 'success'): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/* --------- Statuts --------- */

function statut_label(string $s): string
{
    return [
        'en_attente' => 'En attente',
        'en_cours'   => 'En cours',
        'livree'     => 'Livree',
        'annulee'    => 'Annulee',
        'present'    => 'Present',
        'absent'     => 'Absent',
        'conge'      => 'Conge',
        'retard'     => 'Retard',
    ][$s] ?? ucfirst($s);
}

function pill(string $status): string
{
    $map = [
        'en_attente' => 'warn',
        'en_cours'   => 'info',
        'livree'     => 'ok',
        'annulee'    => 'bad',
        'present'    => 'ok',
        'absent'     => 'bad',
        'conge'      => 'info',
        'retard'     => 'warn',
    ];
    $cls = $map[$status] ?? 'muted';
    return '<span class="pill pill--' . $cls . '">' . e(statut_label($status)) . '</span>';
}

/* --------- Stock --------- */

function recalc_stock(int $fournitureId): void
{
    $in  = (float) fetchValue('SELECT COALESCE(SUM(quantite),0) FROM mouvements_stock WHERE fourniture_id=? AND type="entree"', [$fournitureId]);
    $out = (float) fetchValue('SELECT COALESCE(SUM(quantite),0) FROM mouvements_stock WHERE fourniture_id=? AND type="sortie"', [$fournitureId]);
    q('UPDATE fournitures SET quantite = ? WHERE id = ?', [$in - $out, $fournitureId]);
}

function low_stock(): array
{
    return fetchAll('SELECT * FROM fournitures WHERE quantite <= seuil_critique ORDER BY quantite ASC');
}

function next_reference(string $prefix, string $table, string $col): string
{
    $n = (int) fetchValue("SELECT COUNT(*) FROM `$table`") + 1;
    do {
        $ref    = $prefix . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        $exists = (int) fetchValue("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?", [$ref]);
        $n++;
    } while ($exists > 0);
    return $ref;
}
