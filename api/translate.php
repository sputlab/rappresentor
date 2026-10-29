<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

use Rappresentor\Core\Auth;
use Rappresentor\Core\Helper;

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autenticato.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Helper::verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Richiesta non valida o token CSRF scaduto.']);
    exit;
}

$text = trim($_POST['text'] ?? '');
$targetLang = trim($_POST['lang'] ?? '');

$allowedLangs = [
    'en' => 'English',
    'ro' => 'Română',
    'hr' => 'Hrvatski',
    'sq' => 'Shqip',
    'bn' => 'বাংলা',
    'tr' => 'Türkçe'
];

if (!isset($allowedLangs[$targetLang])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lingua di destinazione non supportata.']);
    exit;
}

if ($text === '') {
    echo json_encode([
        'success' => true,
        'lang' => $targetLang,
        'translatedText' => ''
    ]);
    exit;
}

// Inizializza cache di sessione per le traduzioni se non presente
if (!isset($_SESSION['_trans_cache']) || !is_array($_SESSION['_trans_cache'])) {
    $_SESSION['_trans_cache'] = [];
}

$cacheKey = md5($targetLang . '::' . $text);
if (isset($_SESSION['_trans_cache'][$cacheKey])) {
    echo json_encode([
        'success' => true,
        'lang' => $targetLang,
        'translatedText' => $_SESSION['_trans_cache'][$cacheKey],
        'cached' => true
    ]);
    exit;
}

/**
 * Traduce un singolo frammento di testo tramite API
 */
function translateFragment(string $fragment, string $lang): string {
    $trimmed = trim($fragment);
    if ($trimmed === '') {
        return '';
    }

    $url = "https://api.mymemory.translated.net/get?q=" . urlencode($trimmed) . "&langpair=it|" . urlencode($lang);
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 8,
            'user_agent' => 'RappresentorApp/1.1 (https://github.com/sputlab/rappresentor)'
        ]
    ]);

    $res = @file_get_contents($url, false, $ctx);
    if (!$res) {
        return $fragment;
    }

    $data = json_decode($res, true);
    if (!isset($data['responseData']['translatedText'])) {
        return $fragment;
    }

    $translated = $data['responseData']['translatedText'];
    return html_entity_decode((string)$translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Suddivide il testo in paragrafi per evitare limiti di caratteri per richiesta
$paragraphs = explode("\n\n", $text);
$translatedParagraphs = [];

foreach ($paragraphs as $p) {
    $pTrim = trim($p);
    if ($pTrim === '') {
        $translatedParagraphs[] = '';
        continue;
    }

    // Se un paragrafo è insolitamente lungo (> 400 caratteri), suddividi ulteriormente per riga
    if (mb_strlen($pTrim) > 400) {
        $lines = explode("\n", $pTrim);
        $translatedLines = [];
        foreach ($lines as $line) {
            $translatedLines[] = translateFragment($line, $targetLang);
        }
        $translatedParagraphs[] = implode("\n", $translatedLines);
    } else {
        $translatedParagraphs[] = translateFragment($pTrim, $targetLang);
    }
}

$finalTranslated = implode("\n\n", $translatedParagraphs);

// Salva in cache di sessione
$_SESSION['_trans_cache'][$cacheKey] = $finalTranslated;

echo json_encode([
    'success' => true,
    'lang' => $targetLang,
    'translatedText' => $finalTranslated,
    'cached' => false
]);
exit;
