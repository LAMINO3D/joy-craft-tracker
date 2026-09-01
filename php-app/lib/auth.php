<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt.php';

/** Libelles des roles. */
function roles_labels(): array
{
    return [
        'admin'      => 'Administrateur',
        'achats'     => 'Responsable des achats',
        'stock'      => 'Gestionnaire de stock',
        'rh'         => 'Responsable RH',
        'commercial' => 'Commercial',
    ];
}

/**
 * Matrice d'acces : module => roles autorises.
 * L'administrateur a acces a tout.
 */
function access_matrix(): array
{
    return [
        'dashboard' => ['admin', 'achats', 'stock', 'rh', 'commercial'],
        'personnel' => ['admin', 'rh'],
        'stock'     => ['admin', 'stock', 'achats'],
        'ventes'    => ['admin', 'commercial'],
        'commandes' => ['admin', 'achats', 'commercial'],
        'admin'     => ['admin'],
    ];
}

function can(string $module, ?array $user = null): bool
{
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    if ($user['role'] === 'admin') {
        return true;
    }
    $matrix = access_matrix();
    return in_array($user['role'], $matrix[$module] ?? [], true);
}

function login_user(string $email, string $password): ?array
{
    $u = fetchOne('SELECT * FROM utilisateurs WHERE email = ? LIMIT 1', [$email]);
    if (!$u || !(int) $u['actif']) {
        return null;
    }
    if (!password_verify($password, $u['mot_de_passe'])) {
        return null;
    }

    $token = jwt_encode([
        'sub'    => (int) $u['id'],
        'email'  => $u['email'],
        'role'   => $u['role'],
        'nom'    => $u['nom'] . ' ' . $u['prenom'],
    ]);

    setcookie(JWT_COOKIE, $token, [
        'expires'  => time() + JWT_TTL,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    audit('connexion', 'utilisateur', (int) $u['id'], $u['email'], (int) $u['id']);
    return $u;
}

function logout_user(): void
{
    setcookie(JWT_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true]);
}

function current_user(): ?array
{
    static $cached = false;
    static $user   = null;
    if ($cached) {
        return $user;
    }
    $cached = true;

    $token = $_COOKIE[JWT_COOKIE] ?? null;
    if (!$token && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = preg_replace('/^Bearer\s+/i', '', $_SERVER['HTTP_AUTHORIZATION']);
    }
    $claims = jwt_decode($token);
    if (!$claims) {
        return null;
    }

    $row = fetchOne('SELECT id, nom, prenom, email, role, actif FROM utilisateurs WHERE id = ?', [(int) $claims['sub']]);
    if (!$row || !(int) $row['actif']) {
        return null;
    }
    $user = $row;
    return $user;
}

/** Exige une session valide, et optionnellement l'acces a un module. */
function require_auth(?string $module = null): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    if ($module !== null && !can($module, $user)) {
        http_response_code(403);
        require __DIR__ . '/../partials/forbidden.php';
        exit;
    }
    return $user;
}

function audit(string $action, string $entite, ?int $entiteId = null, ?string $details = null, ?int $userId = null): void
{
    $userId = $userId ?? (current_user()['id'] ?? null);
    q(
        'INSERT INTO audit_log (utilisateur_id, action, entite, entite_id, details) VALUES (?,?,?,?,?)',
        [$userId, $action, $entite, $entiteId, $details !== null ? mb_substr($details, 0, 500) : null]
    );
}

/* ---------------- CSRF ---------------- */

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sent = $_POST['_csrf'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Session expiree. Rechargez la page.');
    }
}
