<?php
declare(strict_types=1);

namespace Rappresentor\Core;

use PDO;
use Exception;

/**
 * Gestore Autenticazione e Utenti
 * Versione SemVer: 0.1.0
 */
class Auth
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
        self::initSession();
    }

    /**
     * Inizializza la sessione in modo sicuro
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', '1');
            }
            session_start();
        }
    }

    /**
     * Tenta il login dell'utente
     */
    public function login(string $email, string $password): array
    {
        $email = trim(strtolower($email));
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Inserisci email e password.'];
        }

        $stmt = $this->db->prepare("SELECT id, name, email, password_hash, role, is_active FROM rappresentor_users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Credenziali non valide.'];
        }

        if ((int)$user['is_active'] !== 1) {
            return ['success' => false, 'message' => 'Il tuo account è disattivato. Contatta l\'amministratore.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Credenziali non valide.'];
        }

        // Rigenera ID di sessione contro session fixation
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        return ['success' => true, 'user' => $user];
    }

    /**
     * Esegue il logout
     */
    public static function logout(): void
    {
        self::initSession();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Verifica se un utente è attualmente loggato
     */
    public static function check(): bool
    {
        self::initSession();
        return !empty($_SESSION['user_id']);
    }

    /**
     * Restituisce i dati dell'utente autenticato
     */
    public static function user(): ?array
    {
        self::initSession();
        if (!self::check()) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'] ?? 0,
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'collaboratore'
        ];
    }

    /**
     * Restituisce l'ID dell'utente loggato
     */
    public static function id(): ?int
    {
        self::initSession();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Verifica se l'utente loggato ha ruolo admin
     */
    public static function isAdmin(): bool
    {
        self::initSession();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    /**
     * Verifica se l'utente loggato ha ruolo rappresentante
     */
    public static function isRepresentative(): bool
    {
        self::initSession();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'rappresentante';
    }

    /**
     * Verifica se l'utente loggato ha ruolo collaboratore
     */
    public static function isCollaborator(): bool
    {
        self::initSession();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'collaboratore';
    }

    /**
     * Restituisce il ruolo dell'utente loggato
     */
    public static function role(): string
    {
        self::initSession();
        return $_SESSION['user_role'] ?? 'ospite';
    }

    /**
     * Verifica se l'utente ha permessi per gestire utenti/collaboratori (admin o rappresentante)
     */
    public static function canManageUsers(): bool
    {
        return self::isAdmin() || self::isRepresentative();
    }

    /**
     * Richiede che l'utente sia autenticato, altrimenti redirect al login
     */
    public static function requireAuth(string $redirect = 'login.php'): void
    {
        if (!self::check()) {
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Richiede che l'utente sia admin
     */
    public static function requireAdmin(string $redirect = 'index.php'): void
    {
        self::requireAuth();
        if (!self::isAdmin()) {
            Helper::setFlash('danger', 'Accesso non autorizzato. Questa sezione è riservata all\'amministratore.');
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Richiede che l'utente sia admin o rappresentante
     */
    public static function requireCanManageUsers(string $redirect = 'index.php'): void
    {
        self::requireAuth();
        if (!self::canManageUsers()) {
            Helper::setFlash('danger', 'Accesso non autorizzato. Questa sezione è riservata ad amministratori e rappresentanti di classe.');
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Conta il numero di utenti attivi nel sistema
     */
    public function getActiveUsersCount(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM rappresentor_users WHERE is_active = 1");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Verifica se è possibile aggiungere un nuovo collaboratore (limite massimo 5)
     */
    public function canAddUser(): bool
    {
        $stmt = $this->db->query("SELECT key_value FROM rappresentor_settings WHERE key_name = 'max_users'");
        $maxUsers = (int)($stmt->fetchColumn() ?: 5);
        return $this->getActiveUsersCount() < $maxUsers;
    }

    /**
     * Genera un token sicuro per il recupero password e lo salva a DB
     */
    public function generateResetToken(string $email): ?array
    {
        $email = trim(strtolower($email));
        $stmt = $this->db->prepare("SELECT id, name, email FROM rappresentor_users WHERE email = :email AND is_active = 1 LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 ora di validità

        $update = $this->db->prepare("UPDATE rappresentor_users SET reset_token = :token, reset_expires_at = :expires WHERE id = :id");
        $update->execute([
            'token' => $token,
            'expires' => $expiresAt,
            'id' => $user['id']
        ]);

        return [
            'user' => $user,
            'token' => $token,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Verifica la validità di un token di reset
     */
    public function verifyResetToken(string $token): ?array
    {
        if (empty($token) || strlen($token) !== 64) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT id, name, email FROM rappresentor_users WHERE reset_token = :token AND reset_expires_at > NOW() AND is_active = 1 LIMIT 1");
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Resetta la password dell'utente e invalida il token
     */
    public function resetPassword(string $token, string $newPassword): bool
    {
        $user = $this->verifyResetToken($token);
        if (!$user) {
            return false;
        }

        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE rappresentor_users SET password_hash = :hash, reset_token = NULL, reset_expires_at = NULL WHERE id = :id");
        return $stmt->execute([
            'hash' => $hash,
            'id' => $user['id']
        ]);
    }
}
