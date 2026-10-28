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
     * Gestisce la classe attiva selezionata in sessione
     */
    public static function getActiveClassId(PDO $db): ?int
    {
        Auth::initSession();

        if (isset($_GET['switch_class'])) {
            $cid = (int)$_GET['switch_class'];
            $stmt = $db->prepare("SELECT id FROM rappresentor_classes WHERE id = :id AND is_archived = 0");
            $stmt->execute(['id' => $cid]);
            if ($stmt->fetch()) {
                $_SESSION['active_class_id'] = $cid;
                return $cid;
            }
        }

        if (!empty($_SESSION['active_class_id'])) {
            // Verifica se esiste ancora
            $stmt = $db->prepare("SELECT id FROM rappresentor_classes WHERE id = :id AND is_archived = 0");
            $stmt->execute(['id' => (int)$_SESSION['active_class_id']]);
            if ($stmt->fetch()) {
                return (int)$_SESSION['active_class_id'];
            }
        }

        // Se non impostata o non valida, prendi la prima classe attiva
        $stmt = $db->query("SELECT id FROM rappresentor_classes WHERE is_archived = 0 ORDER BY id ASC LIMIT 1");
        $firstId = $stmt->fetchColumn();
        if ($firstId) {
            $_SESSION['active_class_id'] = (int)$firstId;
            return (int)$firstId;
        }

        return null;
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
     * Recupera l'elenco di tutte le classi disponibili
     */
    public static function getAllClasses(PDO $db, bool $includeArchived = false): array
    {
        $sql = "SELECT * FROM rappresentor_classes";
        if (!$includeArchived) {
            $sql .= " WHERE is_archived = 0";
        }
        $sql .= " ORDER BY school_year DESC, name ASC";
        return $db->query($sql)->fetchAll();
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
