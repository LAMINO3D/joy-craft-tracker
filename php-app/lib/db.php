<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>Erreur base de donnees</title>'
            . '<div style="font-family:system-ui;max-width:680px;margin:80px auto;padding:24px;'
            . 'border:1px solid #dbe2ea;border-radius:12px;background:#fff;color:#132132">'
            . '<h1 style="margin:0 0 8px">Connexion a la base impossible</h1>'
            . '<p>Verifiez que <b>MySQL</b> est demarre dans XAMPP et que la base <b>' . DB_NAME
            . '</b> a bien ete importee depuis <code>sql/smt_du_sahel.sql</code>.</p>'
            . '<p style="color:#64748b;font-size:13px">' . htmlspecialchars($e->getMessage()) . '</p></div>';
        exit;
    }

    return $pdo;
}

/** Raccourci : requete preparee + execution. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function fetchAll(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function fetchOne(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function fetchValue(string $sql, array $params = [], $default = 0)
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false || $v === null ? $default : $v;
}
