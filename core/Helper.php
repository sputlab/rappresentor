<?php
declare(strict_types=1);

namespace Rappresentor\Core;

use PDO;

/**
 * Funzioni di supporto, formattazione, CSRF e sessioni
 * Versione SemVer: 0.1.0
 */
class Helper
{
    /**
     * Sanitizzazione sicura per output HTML
     */
    public static function e(?string $string): string
    {
        return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Genera e memorizza il token CSRF in sessione
     */
    public static function csrfToken(): string
    {
        Auth::initSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Genera l'input hidden HTML per il form con token CSRF
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="csrf_token" value="' . self::e($token) . '">';
    }

    /**
     * Verifica la validità del token CSRF inviato via POST
     */
    public static function verifyCsrf(): bool
    {
        Auth::initSession();
        $submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($submittedToken) || empty($sessionToken)) {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Imposta un messaggio flash in sessione (success, error, warning, info)
     */
    public static function setFlash(string $type, string $message): void
    {
        Auth::initSession();
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    /**
     * Recupera e consuma il messaggio flash corrente
     */
    public static function getFlash(): ?array
    {
        Auth::initSession();
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    /**
     * Formatta un importo in valuta Euro (€ 1.250,50)
     */
    public static function formatMoney(float|int|string|null $amount): string
    {
        $val = (float)($amount ?? 0);
        return number_format($val, 2, ',', '.') . ' €';
    }

    /**
     * Formatta una data in formato italiano (DD/MM/YYYY)
     */
    public static function formatDate(?string $dateStr): string
    {
        if (empty($dateStr) || $dateStr === '0000-00-00') {
            return '-';
        }
        $ts = strtotime($dateStr);
        return $ts ? date('d/m/Y', $ts) : '-';
    }

    /**
     * Formatta data e ora in formato italiano (DD/MM/YYYY HH:ii)
     */
    public static function formatDateTime(?string $dateStr): string
    {
        if (empty($dateStr) || $dateStr === '0000-00-00 00:00:00') {
            return '-';
        }
        $ts = strtotime($dateStr);
        return $ts ? date('d/m/Y H:i', $ts) : '-';
    }

    /**
     * Restituisce etichetta testuale e colore badge per il tipo di scuola
     */
    public static function schoolTypeDetails(string $type): array
    {
        return match ($type) {
            'nido' => [
                'label' => 'Asilo Nido',
                'age' => '0-3 anni',
                'badge' => 'badge-nido',
                'icon' => '👶'
            ],
            'infanzia' => [
                'label' => 'Scuola dell\'Infanzia',
                'age' => '3-6 anni',
                'badge' => 'badge-infanzia',
                'icon' => '🎨'
            ],
            'primaria' => [
                'label' => 'Scuola Primaria',
                'age' => '6-11 anni',
                'badge' => 'badge-primaria',
                'icon' => '🎒'
            ],
            'secondaria_1' => [
                'label' => 'Scuola Secondaria 1° Grado',
                'age' => '11-14 anni',
                'badge' => 'badge-secondaria',
                'icon' => '📐'
            ],
            default => [
                'label' => 'Scuola',
                'age' => '',
                'badge' => 'badge-secondary',
                'icon' => '🏫'
            ]
        };
    }

    /**
     * Pulisce un numero di telefono italiano per WhatsApp (aggiungendo prefisso 39 se assente)
     */
    public static function cleanPhone(string $phone): string
    {
        $num = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($num, '0039')) {
            $num = substr($num, 4);
        }
        if (!str_starts_with($num, '39') && strlen($num) >= 9 && strlen($num) <= 10) {
            $num = '39' . $num;
        }
        return $num;
    }

    /**
     * Genera link diretto per aprire chat WhatsApp
     */
    public static function waLink(?string $phone, string $text = ''): string
    {
        if (empty($phone)) {
            return '#';
        }
        $clean = self::cleanPhone($phone);
        $encodedText = !empty($text) ? '&text=' . rawurlencode($text) : '';
        return "https://wa.me/{$clean}?send=1{$encodedText}";
    }

    /**
     * Gestisce la classe attiva selezionata in sessione per l'utente loggato
     */
    public static function getActiveClassId(PDO $db): ?int
    {
        Auth::initSession();
        $availableClasses = self::getAllClasses($db);

        if (empty($availableClasses)) {
            unset($_SESSION['active_class_id']);
            return null;
        }

        $availableIds = array_map('intval', array_column($availableClasses, 'id'));

        if (isset($_GET['switch_class'])) {
            $cid = (int)$_GET['switch_class'];
            if (in_array($cid, $availableIds, true)) {
                $_SESSION['active_class_id'] = $cid;
                return $cid;
            }
        }

        if (!empty($_SESSION['active_class_id'])) {
            $sessId = (int)$_SESSION['active_class_id'];
            if (in_array($sessId, $availableIds, true)) {
                return $sessId;
            }
        }

        $firstId = (int)$availableIds[0];
        $_SESSION['active_class_id'] = $firstId;
        return $firstId;
    }

    /**
     * Recupera le informazioni della classe attiva
     */
    public static function getActiveClass(PDO $db): ?array
    {
        $classId = self::getActiveClassId($db);
        if (!$classId) {
            return null;
        }
        $stmt = $db->prepare("SELECT * FROM rappresentor_classes WHERE id = :id");
        $stmt->execute(['id' => $classId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Recupera l'elenco di tutte le classi registrate nel sistema (senza filtro utente)
     */
    public static function getSystemClasses(PDO $db, bool $includeArchived = false): array
    {
        $sql = "SELECT * FROM rappresentor_classes";
        if (!$includeArchived) {
            $sql .= " WHERE is_archived = 0";
        }
        $sql .= " ORDER BY school_year DESC, name ASC";
        return $db->query($sql)->fetchAll();
    }

    /**
     * Recupera gli ID delle classi assegnate a uno specifico utente
     */
    public static function getUserClassIds(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT class_id FROM rappresentor_user_classes WHERE user_id = :uid");
        $stmt->execute(['uid' => $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Recupera le classi autorizzate per un dato utente (gli admin hanno accesso a tutte)
     */
    public static function getUserClasses(PDO $db, int $userId, bool $includeArchived = false): array
    {
        $stmt = $db->prepare("SELECT role FROM rappresentor_users WHERE id = :uid");
        $stmt->execute(['uid' => $userId]);
        $role = $stmt->fetchColumn();

        if ($role === 'admin') {
            return self::getSystemClasses($db, $includeArchived);
        }

        $sql = "SELECT c.* FROM rappresentor_classes c 
                JOIN rappresentor_user_classes uc ON c.id = uc.class_id 
                WHERE uc.user_id = :uid";
        if (!$includeArchived) {
            $sql .= " AND c.is_archived = 0";
        }
        $sql .= " ORDER BY c.school_year DESC, c.name ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Recupera l'elenco delle classi accessibili all'utente attualmente loggato.
     * Gli amministratori vedono tutte le classi, mentre rappresentanti e collaboratori
     * vedono esclusivamente le classi loro assegnate.
     */
    public static function getAllClasses(PDO $db, bool $includeArchived = false): array
    {
        Auth::initSession();
        $userId = Auth::id();

        if ($userId && !Auth::isAdmin()) {
            return self::getUserClasses($db, $userId, $includeArchived);
        }

        return self::getSystemClasses($db, $includeArchived);
    }

    /**
     * Assegna le classi a un utente (rappresentante o collaboratore).
     * Se l'assegnazione è effettuata da un rappresentante, vengono modificate solo le classi
     * gestite dal rappresentante stesso.
     */
    public static function assignUserClasses(PDO $db, int $userId, array $newClassIds, ?int $assignedBy = null): void
    {
        $newClassIds = array_unique(array_filter(array_map('intval', $newClassIds)));

        $assignerIsAdmin = true;
        $manageableClassIds = [];

        if ($assignedBy !== null) {
            $stmt = $db->prepare("SELECT role FROM rappresentor_users WHERE id = :id");
            $stmt->execute(['id' => $assignedBy]);
            $assignerRole = $stmt->fetchColumn();

            if ($assignerRole !== 'admin') {
                $assignerIsAdmin = false;
                $manageableClassIds = self::getUserClassIds($db, $assignedBy);
            }
        }

        if ($assignerIsAdmin) {
            // L'amministratore sovrascrive interamente le classi dell'utente
            $stmt = $db->prepare("DELETE FROM rappresentor_user_classes WHERE user_id = :uid");
            $stmt->execute(['uid' => $userId]);

            if (!empty($newClassIds)) {
                $insert = $db->prepare("INSERT INTO rappresentor_user_classes (user_id, class_id, assigned_by) VALUES (:uid, :cid, :by)");
                foreach ($newClassIds as $cid) {
                    $insert->execute(['uid' => $userId, 'cid' => $cid, 'by' => $assignedBy]);
                }
            }
        } else {
            // Il rappresentante può sincronizzare solo le classi di sua competenza
            if (!empty($manageableClassIds)) {
                $placeholders = implode(',', array_fill(0, count($manageableClassIds), '?'));
                $deleteStmt = $db->prepare("DELETE FROM rappresentor_user_classes WHERE user_id = ? AND class_id IN ({$placeholders})");
                $deleteStmt->execute(array_merge([$userId], $manageableClassIds));

                $validToAssign = array_intersect($newClassIds, $manageableClassIds);
                if (!empty($validToAssign)) {
                    $insert = $db->prepare("INSERT INTO rappresentor_user_classes (user_id, class_id, assigned_by) VALUES (:uid, :cid, :by)");
                    foreach ($validToAssign as $cid) {
                        $insert->execute(['uid' => $userId, 'cid' => $cid, 'by' => $assignedBy]);
                    }
                }
            }
        }
    }

    /**
     * Verifica se un utente può accedere/gestire una determinata classe
     */
    public static function canManageClass(PDO $db, int $classId, ?int $userId = null): bool
    {
        Auth::initSession();
        $uid = $userId ?? Auth::id();
        if (!$uid) {
            return false;
        }

        $stmt = $db->prepare("SELECT role FROM rappresentor_users WHERE id = :id");
        $stmt->execute(['id' => $uid]);
        $role = $stmt->fetchColumn();

        if ($role === 'admin') {
            return true;
        }

        $assigned = self::getUserClassIds($db, $uid);
        return in_array($classId, $assigned, true);
    }

    /**
     * Recupera tutti gli utenti assegnati a una classe (con rispettivo ruolo)
     */
    public static function getClassAssignedUsers(PDO $db, int $classId): array
    {
        $stmt = $db->prepare("SELECT u.id, u.name, u.email, u.role, u.is_active, uc.assigned_at
            FROM rappresentor_users u
            JOIN rappresentor_user_classes uc ON u.id = uc.user_id
            WHERE uc.class_id = :cid
            ORDER BY FIELD(u.role, 'admin', 'rappresentante', 'collaboratore'), u.name ASC");
        $stmt->execute(['cid' => $classId]);
        return $stmt->fetchAll();
    }

    /**
     * Recupera la versione corrente dell'app
     */
    public static function getAppVersion(): string
    {
        $versionFile = __DIR__ . '/../VERSION';
        if (file_exists($versionFile)) {
            return trim(file_get_contents($versionFile));
        }
        return '0.1.0';
    }

    /**
     * Calcola l'età dell'alunno in base alla data di nascita
     */
    public static function calculateAge(?string $birthDate): string
    {
        if (empty($birthDate) || $birthDate === '0000-00-00') {
            return '';
        }
        $dob = new \DateTime($birthDate);
        $now = new \DateTime();
        $diff = $now->diff($dob);

        if ($diff->y === 0) {
            return "{$diff->m} mesi";
        }
        return "{$diff->y} anni";
    }
}
