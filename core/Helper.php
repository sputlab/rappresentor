<?php
declare(strict_types=1);

namespace Rappresentor\Core;

use PDO;

/**
 * Funzioni di supporto, formattazione, CSRF e sessioni
 * Versione SemVer: 1.2.0
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
     * Calcola il riepilogo finanziario della cassa di classe:
     * - Totale quote versate dagli alunni (entrate automatiche da rappresentor_payments)
     * - Movimenti manuali registrati nel registro cassa (entrate ed uscite in rappresentor_cashbox_transactions)
     * - Totale Entrate complessivo = Entrate Quote + Entrate Manuali
     * - Totale Uscite complessivo = Uscite Manuali
     * - Saldo Cassa Disponibile = Totale Entrate - Totale Uscite
     */
    public static function getClassCashSummary(PDO $db, int $classId): array
    {
        // 1. Entrate automatiche da quote versate dagli alunni
        $stmt = $db->prepare("SELECT COALESCE(SUM(p.amount_paid), 0) 
            FROM rappresentor_payments p 
            JOIN rappresentor_campaigns c ON p.campaign_id = c.id 
            WHERE c.class_id = :cid");
        $stmt->execute(['cid' => $classId]);
        $entrateQuote = (float)$stmt->fetchColumn();

        // 2. Movimenti manuali registrati nel libro cassa (entrate ed uscite)
        $stmt = $db->prepare("SELECT 
            COALESCE(SUM(CASE WHEN type = 'entrata' THEN amount ELSE 0 END), 0) as manual_entrate,
            COALESCE(SUM(CASE WHEN type = 'uscita' THEN amount ELSE 0 END), 0) as manual_uscite
            FROM rappresentor_cashbox_transactions WHERE class_id = :cid");
        $stmt->execute(['cid' => $classId]);
        $manual = $stmt->fetch();
        $entrateManuali = (float)($manual['manual_entrate'] ?? 0);
        $usciteManuali = (float)($manual['manual_uscite'] ?? 0);

        $totalEntrate = $entrateQuote + $entrateManuali;
        $totalUscite = $usciteManuali;
        $saldo = $totalEntrate - $totalUscite;

        return [
            'entrate_quote' => $entrateQuote,
            'entrate_manuali' => $entrateManuali,
            'total_entrate' => $totalEntrate,
            'total_uscite' => $totalUscite,
            'saldo' => $saldo
        ];
    }

    /**
     * Recupera tutte le campagne quote attive per una classe e ne genera il riepilogo WhatsApp
     */
    public static function getActiveCampaignsSummary(PDO $db, int $classId): array
    {
        $stmt = $db->prepare("SELECT * FROM rappresentor_campaigns WHERE class_id = :cid AND is_closed = 0 ORDER BY deadline ASC, id ASC");
        $stmt->execute(['cid' => $classId]);
        $campaigns = $stmt->fetchAll();

        $titles = [];
        $lines = [];
        $deadlines = [];
        $totalQuota = 0.0;

        foreach ($campaigns as $c) {
            $q = (float)$c['quota_per_student'];
            $totalQuota += $q;
            $dl = $c['deadline'] ? self::formatDate($c['deadline']) : null;
            $titles[] = $c['title'];
            if ($dl) {
                $deadlines[] = "{$c['title']}: entro il {$dl}";
            }

            $line = "• *{$c['title']}*: " . self::formatMoney($q) . " per alunno";
            if ($dl) {
                $line .= " (scadenza: *{$dl}*)";
            }
            if (!empty($c['description'])) {
                $line .= "\n  _{$c['description']}_";
            }
            $lines[] = $line;
        }

        if (empty($campaigns)) {
            $formattedBlock = "Al momento non risultano raccolte quote attive per la nostra classe.";
            $quotaTitle = "Cassa Comune";
            $quotaAmount = "10,00";
            $quotaDeadline = "prossima settimana";
        } elseif (count($campaigns) === 1) {
            $single = $campaigns[0];
            $quotaTitle = $single['title'];
            $quotaAmount = number_format((float)$single['quota_per_student'], 2, ',', '.');
            $quotaDeadline = $single['deadline'] ? self::formatDate($single['deadline']) : 'prossima settimana';
            $formattedBlock = "📌 *{$single['title']}*\n💰 Quota: *" . self::formatMoney($single['quota_per_student']) . "* per alunno" . ($single['deadline'] ? "\n📅 Scadenza: *" . self::formatDate($single['deadline']) . "*" : "");
            if (!empty($single['description'])) {
                $formattedBlock .= "\n_{$single['description']}_";
            }
        } else {
            $quotaTitle = implode(", ", $titles);
            $quotaAmount = number_format($totalQuota, 2, ',', '.') . " (totale di " . count($campaigns) . " raccolte attive)";
            $quotaDeadline = !empty($deadlines) ? implode(" | ", $deadlines) : 'come indicato nelle singole scadenze';
            $formattedBlock = implode("\n", $lines) . "\n\n💰 *Totale complessivo quote richieste:* " . self::formatMoney($totalQuota) . " per alunno";
        }

        return [
            'campaigns' => $campaigns,
            'count' => count($campaigns),
            'total_quota' => $totalQuota,
            'titles' => $titles,
            'quota_title' => $quotaTitle,
            'quota_amount' => $quotaAmount,
            'quota_deadline' => $quotaDeadline,
            'formatted_block' => $formattedBlock
        ];
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
        return '1.2.0';
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

    /**
     * Restituisce il nome in italiano del mese specificato (1-12)
     */
    public static function monthName(int|string|null $month): string
    {
        $m = (int)$month;
        $months = [
            1 => 'Gennaio',
            2 => 'Febbraio',
            3 => 'Marzo',
            4 => 'Aprile',
            5 => 'Maggio',
            6 => 'Giugno',
            7 => 'Luglio',
            8 => 'Agosto',
            9 => 'Settembre',
            10 => 'Ottobre',
            11 => 'Novembre',
            12 => 'Dicembre'
        ];
        return $months[$m] ?? '';
    }

    /**
     * Alias per formattazione nome del mese
     */
    public static function formatMonthName(int|string|null $month): string
    {
        return self::monthName($month);
    }
}
