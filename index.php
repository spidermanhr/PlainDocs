<?php
// DIJAGNOSTIKA - privremeno uključeno da se vidi točan uzrok problema.
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Putanja do mape sa stranicama i povijesti u istoj hijerarhiji
$pagesDir = __DIR__ . '/pages';
$historyDir = __DIR__ . '/history';

// Automatski kreiraj mapu "pages" i "history" ako ne postoje
if (!file_exists($pagesDir)) {
    mkdir($pagesDir, 0777, true);
}
if (!file_exists($historyDir)) {
    mkdir($historyDir, 0777, true);
}

// Putanja do conf mape i JSON datoteka
$confDir = __DIR__ . '/conf';
$metaFile = $confDir . '/structure.json';
$settingsFile = $confDir . '/settings.json';

// Automatski kreiraj mapu "conf" ako ne postoji
if (!file_exists($confDir)) {
    mkdir($confDir, 0777, true);
}

// Funkcija za čišćenje naziva u siguran slug
function slugify($text) {
    if (empty($text)) return 'neimenovana-stranica';

    $charMap = [
        'č' => 'c', 'Č' => 'c',
        'ć' => 'c', 'Ć' => 'c',
        'đ' => 'd', 'Đ' => 'd',
        'š' => 's', 'Š' => 's',
        'ž' => 'z', 'Ž' => 'z'
    ];

    $text = strtr($text, $charMap);
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9 -]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);

    return $text ?: 'neimenovana-stranica';
}

// API OBRADA (POST Zahtjevi od JavaScripta)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    // 1. DOHVAĆANJE LISTE STRANICA I STRUKTURE
    if ($action === 'get_pages') {
        $structure = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : [];
        if (!is_array($structure)) $structure = [];
        
        // Skeniraj fizičke datoteke u /pages
        $files = array_diff(scandir($pagesDir), ['.', '..']);
        $existingFiles = [];
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                $existingFiles[] = $file;
                if (!isset($structure[$file])) {
                    $structure[$file] = [
                        'title' => ucfirst(str_replace(['-', '.html'], [' ', ''], $file)),
                        'parent' => ''
                    ];
                }
            }
        }

        // Ukloni iz strukture datoteke koje više ne postoje na disku
        foreach ($structure as $fileKey => $val) {
            if (!in_array($fileKey, $existingFiles)) {
                unset($structure[$fileKey]);
            }
        }

        // Spremi pročišćenu strukturu
        file_put_contents($metaFile, json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Dohvati postavke ako postoje, uz sve nove opcije
        $defaultSettings = [
            'fontFamily' => 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif',
            'fontSize' => '14px',
            'lineHeight' => '1.6',
            'theme' => 'light',
            'tableStyle' => 'grid',
            'tableWidth' => '100%',
            'tableHover' => '1',
            'tableSticky' => '0',
            'sidebarState' => 'collapsed',
            'rememberPage' => '1',
            'contentMaxWidth' => 'normal',
            'autoSave' => '0',
            'newTemplate' => ''
        ];
        $settings = file_exists($settingsFile) ? json_decode(file_get_contents($settingsFile), true) : [];
        if (!is_array($settings)) $settings = [];
        $settings = array_merge($defaultSettings, $settings);

        echo json_encode([
            'files' => array_values($existingFiles),
            'structure' => $structure,
            'settings' => $settings
        ]);
        exit;
    }

    // 2. SPREMANJE POSTAVKI
    if ($action === 'save_settings') {
        $defaultSettings = [
            'fontFamily' => 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif',
            'fontSize' => '14px',
            'lineHeight' => '1.6',
            'theme' => 'light',
            'tableStyle' => 'grid',
            'tableWidth' => '100%',
            'tableHover' => '1',
            'tableSticky' => '0',
            'sidebarState' => 'collapsed',
            'rememberPage' => '1',
            'contentMaxWidth' => 'normal',
            'autoSave' => '0',
            'newTemplate' => ''
        ];

        $settings = [
            'fontFamily' => trim($_POST['font_family'] ?? $defaultSettings['fontFamily']),
            'fontSize' => trim($_POST['font_size'] ?? $defaultSettings['fontSize']),
            'lineHeight' => trim($_POST['line_height'] ?? $defaultSettings['lineHeight']),
            'theme' => trim($_POST['theme'] ?? $defaultSettings['theme']),
            'tableStyle' => trim($_POST['table_style'] ?? $defaultSettings['tableStyle']),
            'tableWidth' => trim($_POST['table_width'] ?? $defaultSettings['tableWidth']),
            'tableHover' => trim($_POST['table_hover'] ?? $defaultSettings['tableHover']),
            'tableSticky' => trim($_POST['table_sticky'] ?? $defaultSettings['tableSticky']),
            'sidebarState' => trim($_POST['sidebar_state'] ?? $defaultSettings['sidebarState']),
            'rememberPage' => trim($_POST['remember_page'] ?? $defaultSettings['rememberPage']),
            'contentMaxWidth' => trim($_POST['content_max_width'] ?? $defaultSettings['contentMaxWidth']),
            'autoSave' => trim($_POST['auto_save'] ?? $defaultSettings['autoSave']),
            'newTemplate' => $_POST['new_template'] ?? $defaultSettings['newTemplate']
        ];

        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'settings' => $settings]);
        exit;
    }

    // 3. UČITAVANJE POJEDINAČNE STRANICE
    if ($action === 'load_page') {
        $filename = basename($_POST['filename'] ?? '');
        $filepath = $pagesDir . '/' . $filename;

        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            echo json_encode(['success' => true, 'content' => $content]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Datoteka ne postoji.']);
        }
        exit;
    }

    // 4. SPREMANJE STRANICE
    if ($action === 'save_page') {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $oldFilename = basename($_POST['old_filename'] ?? '');
        $parentId = trim($_POST['parent_id'] ?? '');
        $isAutosave = ($_POST['is_autosave'] ?? '0') === '1';

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Naslov je obavezan.']);
            exit;
        }

        $slug = slugify($title);
        $newFilename = $slug . '.html';
        $filepath = $pagesDir . '/' . $newFilename;

        // Sudar naziva: ako novi slug odgovara POSTOJEĆOJ, ALI DRUGOJ stranici
        // (nije riječ o ažuriranju iste stranice), odbij spremanje umjesto
        // tihog prepisivanja tuđe stranice.
        if (file_exists($filepath) && $newFilename !== $oldFilename) {
            echo json_encode([
                'success' => false,
                'error' => 'Stranica s nazivom "' . $title . '" (datoteka ' . $newFilename . ') već postoji. Odaberite drugačiji naslov.'
            ]);
            exit;
        }

        // Sigurnosnu kopiju u /history radimo samo kod SVJESNOG (ručnog) spremanja.
        // Autosave ne stvara history zapis jer bi svakih 30-60 sekundi gomilao
        // beskorisne verzije - trenutni sadržaj je već zaštićen jer autosave
        // odmah piše u aktivnu datoteku.
        if (file_exists($filepath) && !$isAutosave) {
            $timestamp = date('Y-m-d_H-i-s');
            $historyFilename = pathinfo($newFilename, PATHINFO_FILENAME) . '_' . $timestamp . '.html';
            copy($filepath, $historyDir . '/' . $historyFilename);
        }

        // Ako je naslov promijenjen, ukloni staru datoteku s diska
        if (!empty($oldFilename) && $oldFilename !== $newFilename && file_exists($pagesDir . '/' . $oldFilename)) {
            unlink($pagesDir . '/' . $oldFilename);
        }

        // HTML Predložak koji se sprema
        $htmlDocument = "<!DOCTYPE html>\n<html lang=\"hr\">\n<head>\n" .
            "    <meta charset=\"UTF-8\">\n" .
            "    <title>" . htmlspecialchars($title) . "</title>\n" .
            "    <style>\n" .
            "        body { font-family: sans-serif; line-height: 1.6; margin: 40px; }\n" .
            "        table { border-collapse: collapse; width: 100%; margin: 15px 0; }\n" .
            "        table th, table td { border: 1px solid #ccc; padding: 8px; text-align: left; }\n" .
            "        table th { background-color: #f5f5f5; font-weight: bold; }\n" .
            "        table tr:nth-child(even) { background-color: #fafafa; }\n" .
            "    </style>\n" .
            "</head>\n<body>\n" .
            "    <h1>" . htmlspecialchars($title) . "</h1>\n" .
            "    " . $content . "\n" .
            "</body>\n</html>";

        // Spremi HTML datoteku u /pages
        file_put_contents($filepath, $htmlDocument);

        // Ažuriraj strukturu u conf/structure.json
        $structure = file_exists($metaFile) ? json_decode(file_get_contents($metaFile), true) : [];
        if (!is_array($structure)) $structure = [];
        
        // Ako je ime promijenjeno, ažuriraj i sve podređene stranice koje su pokazivale na staro ime
        if (!empty($oldFilename) && $oldFilename !== $newFilename) {
            foreach ($structure as $f => $meta) {
                if (($meta['parent'] ?? '') === $oldFilename) {
                    $structure[$f]['parent'] = $newFilename;
                }
            }
            unset($structure[$oldFilename]);
        }

        $structure[$newFilename] = [
            'title' => $title,
            'parent' => $parentId
        ];

        file_put_contents($metaFile, json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode(['success' => true, 'filename' => $newFilename]);
        exit;
    }

    // 5. BRISANJE STRANICE
    if ($action === 'delete_page') {
        $filename = basename($_POST['filename'] ?? '');
        $filepath = $pagesDir . '/' . $filename;

        if (file_exists($filepath)) {
            unlink($filepath);
        }

        if (file_exists($metaFile)) {
            $structure = json_decode(file_get_contents($metaFile), true);
            if (is_array($structure)) {
                // Ako obrišemo roditelja, podređene stranice prebaci u korijen
                foreach ($structure as $f => $meta) {
                    if (($meta['parent'] ?? '') === $filename) {
                        $structure[$f]['parent'] = '';
                    }
                }
                unset($structure[$filename]);
                file_put_contents($metaFile, json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    // 6. DOHVAĆANJE POVIJESTI VERZIJA ZA STRANICU
    if ($action === 'get_history') {
        $filename = basename($_POST['filename'] ?? '');
        $baseNameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        
        $historyFiles = [];
        if (is_dir($historyDir)) {
            $allFiles = array_diff(scandir($historyDir), ['.', '..']);
            foreach ($allFiles as $file) {
                // Tražimo datoteke oblika: slugg_YYYY-MM-DD_HH-MM-SS.html
                if (strpos($file, $baseNameWithoutExt . '_') === 0 && pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                    $filePath = $historyDir . '/' . $file;
                    $fileSize = filesize($filePath);
                    
                    // Izvlačenje datuma i sata iz naziva datoteke
                    $timestampStr = str_replace([$baseNameWithoutExt . '_', '.html'], '', $file);
                    // Format: YYYY-MM-DD_HH-MM-SS -> YYYY-MM-DD HH:MM:SS
                    $formattedDate = str_replace('_', ' ', $timestampStr);
                    $formattedDate = preg_replace('/^(\d{4})-(\d{2})-(\d{2}) (\d{2})-(\d{2})-(\d{2})/', '$1-$2-$3 $4:$5:$6', $formattedDate);

                    $historyFiles[] = [
                        'filename' => $file,
                        'date' => $formattedDate,
                        'size' => $fileSize
                    ];
                }
            }
        }

        // Sortiraj od najnovije prema najstarijoj
        usort($historyFiles, function($a, $b) {
            return strcmp($b['filename'], $a['filename']);
        });

        echo json_encode(['success' => true, 'history' => $historyFiles]);
        exit;
    }

    // 7. UČITAVANJE POVIJESNE VERZIJE (ZA PREGLED)
    if ($action === 'load_history_version') {
        $histFilename = basename($_POST['hist_filename'] ?? '');
        $filepath = $historyDir . '/' . $histFilename;

        if (file_exists($filepath)) {
            $content = file_get_contents($filepath);
            echo json_encode(['success' => true, 'content' => $content]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Arhivirana verzija ne postoji.']);
        }
        exit;
    }

    // 8. VRAĆANJE (RESTORING) POVIJESNE VERZIJE
    if ($action === 'restore_version') {
        $histFilename = basename($_POST['hist_filename'] ?? '');
        $targetFilename = basename($_POST['target_filename'] ?? '');
        
        $histFilepath = $historyDir . '/' . $histFilename;
        $targetFilepath = $pagesDir . '/' . $targetFilename;

        if (!file_exists($histFilepath)) {
            echo json_encode(['success' => false, 'error' => 'Arhivirana datoteka nije pronađena.']);
            exit;
        }

        // Prije vraćanja, trenutnu verziju spremi u history da se ne izgubi
        if (file_exists($targetFilepath)) {
            $timestamp = date('Y-m-d_H-i-s');
            $backupFilename = pathinfo($targetFilename, PATHINFO_FILENAME) . '_' . $timestamp . '.html';
            copy($targetFilepath, $historyDir . '/' . $backupFilename);
        }

        // Prekopiraj arhiviranu datoteku na mjesto aktivne stranice
        copy($histFilepath, $targetFilepath);

        echo json_encode(['success' => true]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flat-File CMS / Bilješke</title>
    <!-- CKEditor 5 Build -->
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    <style>
        :root {
            --bg-dark: #1e1e2e;
            --bg-sidebar: #ffffff;
            --bg-hover: #f0f4f9;
            --text-main: #333333;
            --accent: #2b579a;
            --border: #dcdcdc;
            --danger: #d9534f;
            --app-font: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            --app-font-size: 14px;
            --app-line-height: 1.6;
            --content-max-width: 100%;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: var(--app-font);
        }

        body {
            display: flex;
            height: 100vh;
            background-color: #f5f5f5;
            color: var(--text-main);
            font-size: var(--app-font-size);
            overflow: hidden;
        }

        /* Teme */
        body.theme-dark {
            --bg-dark: #11111b;
            --bg-sidebar: #181825;
            --bg-hover: #313244;
            --text-main: #cdd6f4;
            --accent: #89b4fa;
            --border: #45475a;
            --danger: #f38ba8;
            background-color: #11111b;
        }

        body.theme-dark #main-content {
            background-color: #1e1e2e;
        }

        body.theme-dark input[type="text"], 
        body.theme-dark select,
        body.theme-dark textarea {
            background-color: #181825;
            color: var(--text-main);
            border-color: var(--border);
        }

        body.theme-dark #confirm-modal-box,
        body.theme-dark #settings-modal-box,
        body.theme-dark #history-modal-box {
            background: #181825;
            color: var(--text-main);
            border: 1px solid var(--border);
        }

        body.theme-dark #confirm-modal-cancel {
            background: #313244;
            color: var(--text-main);
        }

        body.theme-dark #confirm-modal-cancel:hover {
            background: #45475a;
        }

        body.theme-dark .settings-tab-btn {
            background: #181825;
            color: var(--text-main);
            border-color: var(--border);
        }
        body.theme-dark .settings-tab-btn:hover {
            background: #313244;
        }
        body.theme-dark .settings-tab-btn.active {
            background: var(--accent);
            color: #11111b;
            border-color: var(--accent);
        }

        body.theme-dark .ck.ck-editor {
            color: #cdd6f4;
        }

        body.theme-dark .ck.ck-toolbar {
            background: #181825 !important;
            border-color: var(--border) !important;
        }

        body.theme-dark .ck.ck-toolbar .ck-button,
        body.theme-dark .ck.ck-toolbar .ck-button span,
        body.theme-dark .ck.ck-toolbar .ck-dropdown__button {
            color: #cdd6f4 !important;
            fill: #cdd6f4 !important;
        }

        body.theme-dark .ck.ck-toolbar .ck-button svg {
            fill: #cdd6f4 !important;
        }

        body.theme-dark .ck.ck-toolbar__button:hover {
            background: #313244 !important;
        }

        body.theme-dark .ck.ck-button.ck-on {
            background: #313244 !important;
            color: var(--accent) !important;
        }

        body.theme-dark .ck.ck-button.ck-on svg {
            fill: var(--accent) !important;
        }

        body.theme-dark .ck.ck-dropdown__panel {
            background: #181825 !important;
            border-color: var(--border) !important;
        }

        body.theme-dark .ck.ck-list {
            background: #181825 !important;
        }

        body.theme-dark .ck.ck-list__item {
            color: #cdd6f4 !important;
        }

        body.theme-dark .ck.ck-list__item:hover {
            background: #313244 !important;
        }

        body.theme-dark .ck-editor__editable_inline {
            background: #1e1e2e !important;
            color: #cdd6f4 !important;
            border-color: var(--border) !important;
        }

        #sidebar {
            width: 260px;
            min-width: 180px;
            max-width: 550px;
            background-color: var(--bg-sidebar);
            display: flex;
            flex-direction: column;
            padding: 15px 10px;
            user-select: none;
            position: relative;
        }

        #sidebar-resizer {
            position: absolute;
            top: 0;
            right: 0;
            width: 5px;
            height: 100%;
            cursor: ew-resize;
            background-color: transparent;
            z-index: 10;
            transition: background-color 0.2s;
        }

        #sidebar-resizer:hover,
        #sidebar-resizer.resizing {
            background-color: var(--accent);
        }

        #sidebar h2 {
            font-size: 1.1rem;
            margin-bottom: 12px;
            color: var(--text-main);
            padding-left: 5px;
        }

        .btn {
            background-color: var(--accent);
            color: #fff;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-danger {
            background-color: var(--danger);
            color: #fff;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        #page-tree {
            flex-grow: 1;
            overflow-y: auto;
            margin-top: 6px;
        }

        .sidebar-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-left: 5px;
            padding-right: 5px;
        }

        .sidebar-header-row h2 {
            margin-bottom: 0;
            padding-left: 0;
        }

        .tree-toggle-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 6px;
            margin-bottom: 10px;
            padding-right: 5px;
        }

        .tree-toggle-link {
            font-size: 0.78rem;
            color: #888;
            text-decoration: none;
            cursor: pointer;
        }

        .tree-toggle-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .tree-group {
            list-style: none;
            padding-left: 16px;
        }

        .tree-group.root {
            padding-left: 0;
        }

        .tree-item-wrapper {
            display: flex;
            align-items: center;
            padding: 3px 6px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.9em;
            color: var(--text-main);
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .tree-item-wrapper:hover {
            background-color: var(--bg-hover);
        }

        .tree-item-wrapper.active {
            background-color: var(--bg-hover);
            color: var(--accent);
            font-weight: 600;
        }

        .toggle-arrow {
            width: 16px;
            height: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 4px;
            font-size: 0.75rem;
            color: var(--text-main);
            flex-shrink: 0;
        }

        .toggle-arrow.empty {
            visibility: hidden;
        }

        .icon-folder {
            width: 16px;
            height: 16px;
            margin-right: 5px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23eec159'%3E%3Cpath d='M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z'/%3E%3C/svg%3E") no-repeat center center;
            background-size: contain;
            flex-shrink: 0;
        }

        .icon-file {
            width: 16px;
            height: 16px;
            margin-right: 5px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23888888' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/%3E%3Cpolyline points='14 2 14 8 20 8'/%3E%3Cline x1='16' y1='13' x2='8' y2='13'/%3E%3Cline x1='16' y1='17' x2='8' y2='17'/%3E%3Cline x1='10' y1='9' x2='8' y2='9'/%3E%3C/svg%3E") no-repeat center center;
            background-size: contain;
            flex-shrink: 0;
        }

        #main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            padding: 25px;
            overflow-y: auto;
            background-color: #ffffff;
            border-left: 1px solid var(--border);
            align-items: center;
        }

        .content-inner-wrapper {
            width: 100%;
            max-width: var(--content-max-width);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .form-group {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }

        input[type="text"], select, textarea {
            background-color: #fff;
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 9px 12px;
            border-radius: 4px;
            outline: none;
            font-size: var(--app-font-size);
        }

        input[type="text"] {
            flex-grow: 1;
        }

        .actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .editor-container {
            flex-grow: 1;
            color: var(--text-main);
            display: flex;
            flex-direction: column;
        }

        .editor-container .ck.ck-editor {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .editor-container .ck.ck-editor__main {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .ck-editor__editable_inline {
            flex-grow: 1;
            min-height: 420px;
            font-family: var(--app-font) !important;
            font-size: var(--app-font-size) !important;
            line-height: var(--app-line-height) !important;
        }

        #view-content {
            line-height: var(--app-line-height);
        }

        /* DINAMIČKI STILOVI TABLICA */
        table {
            width: var(--table-width, 100%);
            margin: 15px 0;
            border-collapse: collapse;
        }

        body[data-table-style="grid"] table th,
        body[data-table-style="grid"] table td,
        .ck-content[data-table-style="grid"] table th,
        .ck-content[data-table-style="grid"] table td {
            border: 1px solid var(--border);
            padding: 8px;
            text-align: left;
        }
        body[data-table-style="grid"] table th,
        .ck-content[data-table-style="grid"] table th {
            background-color: var(--bg-hover);
            font-weight: bold;
        }

        body[data-table-style="clean"] table th,
        body[data-table-style="clean"] table td,
        .ck-content[data-table-style="clean"] table th,
        .ck-content[data-table-style="clean"] table td {
            border: none;
            border-bottom: 1px solid var(--border);
            padding: 10px 8px;
            text-align: left;
        }
        body[data-table-style="clean"] table th,
        .ck-content[data-table-style="clean"] table th {
            border-bottom: 2px solid var(--accent);
            font-weight: bold;
            background: transparent;
        }

        body[data-table-style="zebra"] table th,
        body[data-table-style="zebra"] table td,
        .ck-content[data-table-style="zebra"] table th,
        .ck-content[data-table-style="zebra"] table td {
            border: 1px solid var(--border);
            padding: 8px;
            text-align: left;
        }
        body[data-table-style="zebra"] table th,
        .ck-content[data-table-style="zebra"] table th {
            background-color: var(--accent);
            color: #fff;
            font-weight: bold;
        }
        body[data-table-style="zebra"] table tr:nth-child(even),
        .ck-content[data-table-style="zebra"] table tr:nth-child(even) {
            background-color: var(--bg-hover);
        }

        body[data-table-style="dense"] table th,
        body[data-table-style="dense"] table td,
        .ck-content[data-table-style="dense"] table td {
            border: 1px solid var(--border);
            padding: 4px 6px;
            font-size: 0.9em;
            text-align: left;
        }
        body[data-table-style="dense"] table th,
        .ck-content[data-table-style="dense"] table th {
            border: 1px solid var(--border);
            padding: 5px 6px;
            background-color: var(--bg-hover);
            font-weight: bold;
        }

        body[data-table-hover="1"] table tr:hover,
        .ck-content[data-table-hover="1"] table tr:hover {
            background-color: var(--bg-hover) !important;
            transition: background 0.15s;
        }

        body[data-table-sticky="1"] table th,
        .ck-content[data-table-sticky="1"] table th {
            position: sticky;
            top: 0;
            z-index: 5;
        }

        #empty-state {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: #888;
            font-size: 1.2rem;
            text-align: center;
            width: 100%;
        }

        #settings-modal-overlay,
        #history-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.45);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        /* Confirm modal mora biti iznad history/settings modala jer se
           može otvoriti "preko" njih (npr. potvrda vraćanja verzije).
           Isti z-index kao ostali overlayi je uzrok bugova gdje se
           confirm dijalog vizualno i klikabilno zaglavi ispod
           history/settings modala. */
        #confirm-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.45);
            align-items: center;
            justify-content: center;
            z-index: 1100;
        }

        #confirm-modal-overlay.visible,
        #settings-modal-overlay.visible,
        #history-modal-overlay.visible {
            display: flex;
        }

        #confirm-modal-box {
            background: #fff;
            border-radius: 8px;
            padding: 22px 24px;
            width: 360px;
            max-width: 90vw;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        #confirm-modal-box p {
            margin-bottom: 18px;
            color: var(--text-main);
            font-size: 0.98rem;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-actions button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        #confirm-modal-cancel {
            background: #e9e9e9;
            color: #333;
        }

        #confirm-modal-cancel:hover {
            background: #dcdcdc;
        }

        #confirm-modal-ok {
            background: var(--danger);
            color: #fff;
        }

        #confirm-modal-ok:hover {
            opacity: 0.9;
        }

        #settings-modal-box {
            background: var(--bg-sidebar);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 8px;
            padding: 20px 24px;
            width: 540px;
            max-width: 95vw;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        /* History Modal stilovi */
        #history-modal-box {
            background: var(--bg-sidebar);
            border: 1px solid var(--border);
            color: var(--text-main);
            border-radius: 8px;
            padding: 20px 24px;
            width: 750px;
            max-width: 95vw;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
        }

        .history-layout {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            flex-grow: 1;
            min-height: 350px;
            max-height: 55vh;
        }

        .history-list-pane {
            width: 260px;
            border-right: 1px solid var(--border);
            padding-right: 12px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .history-preview-pane {
            flex-grow: 1;
            overflow-y: auto;
            padding: 10px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 4px;
            color: #333;
            font-size: 0.9rem;
        }

        body.theme-dark .history-preview-pane {
            background: #1e1e2e;
            color: #cdd6f4;
        }

        .history-item {
            padding: 8px 10px;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid var(--border);
            font-size: 0.85rem;
            background: var(--bg-sidebar);
            transition: background 0.15s;
        }

        .history-item:hover {
            background: var(--bg-hover);
        }

        .history-item.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .settings-tabs-header {
            display: flex;
            gap: 6px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 12px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .settings-tab-btn {
            background: #f1f3f5;
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: background 0.2s, color 0.2s;
        }

        .settings-tab-btn:hover {
            background: #e2e6ea;
        }

        .settings-tab-btn.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }

        .settings-tab-content {
            display: none;
            flex-grow: 1;
            overflow-y: auto;
            max-height: 50vh;
            padding-right: 4px;
        }

        .settings-tab-content.active {
            display: block;
        }

        .settings-row {
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .settings-row label {
            font-weight: 600;
            font-size: 0.88rem;
        }

        .settings-row select,
        .settings-row textarea {
            width: 100%;
        }

        .settings-checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
        }

        .settings-checkbox-row input {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 10px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
        }

        #autosave-status {
            font-size: 0.82rem;
            color: #888;
            margin-left: auto;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div id="sidebar">
        <div class="sidebar-header-row">
            <h2>📁 Dokumenti</h2>
        </div>
        <button class="btn" onclick="showNewPageForm()" style="width: 100%; margin-bottom: 4px;">+ Nova stranica</button>
        <div class="tree-toggle-container">
            <a href="javascript:void(0);" id="tree-toggle-link" class="tree-toggle-link" onclick="toggleAllCollapse()">Prikaži sve</a>
        </div>
        <div id="page-tree"></div>
        
        <div class="sidebar-footer">
            <button class="btn btn-secondary" onclick="openSettingsModal()" style="font-size: 0.85rem; padding: 6px 10px; width: 100%;">⚙️ Postavke</button>
        </div>
        <!-- Ručkica za povlačenje -->
        <div id="sidebar-resizer"></div>
    </div>

    <!-- Glavni dio -->
    <div id="main-content">
        <div id="empty-state">Odaberite ili izradite novu stranicu.</div>

        <div id="view-wrapper" style="display: none; height: 100%; flex-direction: column;" class="content-inner-wrapper">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid var(--border);">
                <h2 id="view-title" style="color: var(--text-main); margin-bottom: 0;"></h2>
                <div class="actions">
                    <button class="btn btn-secondary" onclick="openHistoryModal()">🕒 Povijest</button>
                    <button class="btn" onclick="enterEditMode()">✏️ Uredi</button>
                    <button class="btn btn-danger" onclick="deleteCurrentPage()">Izbriši</button>
                    <span id="view-autosave-status" style="font-size: 0.82rem; color: #888; margin-left: 8px; white-space: nowrap;"></span>
                </div>
            </div>
            <div id="view-content" style="height: auto; overflow-y: visible; padding: 5px 0; flex-grow: 1;"></div>
        </div>

        <div id="editor-wrapper" style="display: none; height: 100%; flex-direction: column;" class="content-inner-wrapper">
            <div class="form-group">
                <input type="text" id="page-title" placeholder="Naziv stranice..." oninput="updateFilenamePreview()">
                <select id="page-parent">
                    <option value="">-- Bez roditelja (Glavni fajl) --</option>
                </select>
            </div>

            <div style="font-size: 0.85rem; color: #888; margin-bottom: 12px; display: flex; align-items: center;">
                <span>Putanja na disku: <strong id="filename-preview" style="color: var(--accent);">pages/--</strong></span>
                <span id="autosave-status"></span>
            </div>

            <div class="editor-container">
                <textarea id="editor"></textarea>
            </div>

            <div class="actions" style="margin-top: 15px;">
                <button class="btn" onclick="saveCurrentPage()">💾 Spremi stranicu</button>
                <button class="btn btn-secondary" onclick="revertChanges()">🔄 Vrati izvorno</button>
                <button class="btn btn-danger" onclick="deleteCurrentPage()">Izbriši</button>
            </div>
        </div>
    </div>

    <!-- Prilagođeni modal za potvrdu brisanja -->
    <div id="confirm-modal-overlay">
        <div id="confirm-modal-box">
            <p id="confirm-modal-message">Jeste li sigurni?</p>
            <div class="modal-actions">
                <button id="confirm-modal-cancel">Odustani</button>
                <button id="confirm-modal-ok">Izbriši</button>
            </div>
        </div>
    </div>

    <!-- Modal za povijest verzija -->
    <div id="history-modal-overlay">
        <div id="history-modal-box">
            <h3 style="margin-bottom: 10px; font-size: 1.1rem;">🕒 Povijest izmjena stranice</h3>
            <p style="font-size: 0.85rem; color: #888; margin-bottom: 12px;">Odaberite željenu verziju s popisa kako biste vidjeli njen sadržaj i vratili je po potrebi.</p>
            
            <div class="history-layout">
                <div id="history-list" class="history-list-pane">
                    <!-- Ovdje se dinamički ucitavaju verzije -->
                </div>
                <div id="history-preview" class="history-preview-pane">
                    <span style="color: #888;">Odaberite verziju s lijeve strane za pregled sadržaja.</span>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 18px; border-top: 1px solid var(--border); padding-top: 12px;">
                <button class="btn btn-secondary" onclick="closeHistoryModal()">Odustani</button>
                <button id="btn-restore-version" class="btn" onclick="restoreSelectedVersion()" disabled>Vrati ovu verziju</button>
            </div>
        </div>
    </div>

    <!-- Modal za postavke organiziran u tabove -->
    <div id="settings-modal-overlay">
        <div id="settings-modal-box">
            <h3 style="margin-bottom: 12px; font-size: 1.1rem;">⚙️ Postavke aplikacije</h3>
            
            <div class="settings-tabs-header">
                <button class="settings-tab-btn active" onclick="switchSettingsTab('appearance', event)">🎨 Izgled</button>
                <button class="settings-tab-btn" onclick="switchSettingsTab('tables', event)">📊 Tablice</button>
                <button class="settings-tab-btn" onclick="switchSettingsTab('interface', event)">🖥️ Sučelje</button>
                <button class="settings-tab-btn" onclick="switchSettingsTab('editor', event)">✏️ Uređivač</button>
            </div>

            <!-- Tab 1: Izgled -->
            <div id="tab-appearance" class="settings-tab-content active">
                <div class="settings-row">
                    <label for="setting-font-family">Font:</label>
                    <select id="setting-font-family">
                        <option value="Segoe UI, Tahoma, Geneva, Verdana, sans-serif">Segoe UI (Standardni)</option>
                        <option value="Arial, Helvetica, sans-serif">Arial</option>
                        <option value="Georgia, serif">Georgia (Serif)</option>
                        <option value="'Courier New', Courier, monospace">Courier New (Monospace)</option>
                        <option value="'Fira Code', Consolas, monospace">Fira Code / Consolas</option>
                    </select>
                </div>

                <div class="settings-row">
                    <label for="setting-font-size">Veličina fonta:</label>
                    <select id="setting-font-size">
                        <option value="12px">12px (Sitno)</option>
                        <option value="14px">14px (Normalno)</option>
                        <option value="16px">16px (Veliko)</option>
                        <option value="18px">18px (Vrlo veliko)</option>
                    </select>
                </div>

                <div class="settings-row">
                    <label for="setting-line-height">Prored (Line-height):</label>
                    <select id="setting-line-height">
                        <option value="1.4">1.4 (Gusto)</option>
                        <option value="1.6">1.6 (Normalno)</option>
                        <option value="1.8">1.8 (Raskošno)</option>
                        <option value="2.0">2.0 (Dvostruko)</option>
                    </select>
                </div>

                <div class="settings-row">
                    <label for="setting-theme">Tema:</label>
                    <select id="setting-theme">
                        <option value="light">Svijetla tema</option>
                        <option value="dark">Tamna tema (Dark Mode)</option>
                    </select>
                </div>
            </div>

            <!-- Tab 2: Tablice -->
            <div id="tab-tables" class="settings-tab-content">
                <div class="settings-row">
                    <label for="setting-table-style">Stil tablice:</label>
                    <select id="setting-table-style">
                        <option value="grid">Standardna (Grid / Rubovi)</option>
                        <option value="clean">Minimalistička (Clean)</option>
                        <option value="zebra">Zebra (Prugasti redovi)</option>
                        <option value="dense">Kompaktna (Dense)</option>
                    </select>
                </div>

                <div class="settings-row">
                    <label for="setting-table-width">Širina tablice:</label>
                    <select id="setting-table-width">
                        <option value="100%">Puna širina (100%)</option>
                        <option value="auto">Automatska širina prema sadržaju</option>
                    </select>
                </div>

                <label class="settings-checkbox-row">
                    <input type="checkbox" id="setting-table-hover"> Omogući hover efekt (isticanje retka mišem)
                </label>

                <label class="settings-checkbox-row">
                    <input type="checkbox" id="setting-table-sticky"> Fiksno zaglavlje (sticky header) kod skrolanja
                </label>
            </div>

            <!-- Tab 3: Sučelje -->
            <div id="tab-interface" class="settings-tab-content">
                <div class="settings-row">
                    <label for="setting-sidebar-state">Zadano stanje bočnog izbornika:</label>
                    <select id="setting-sidebar-state">
                        <option value="collapsed">Sažeto (Collapsed)</option>
                        <option value="expanded">Prošireno (Expanded)</option>
                    </select>
                </div>

                <label class="settings-checkbox-row">
                    <input type="checkbox" id="setting-remember-page"> Pamti zadnje otvorenu stranicu pri ponovnom pokretanju
                </label>

                <div class="settings-row">
                    <label for="setting-content-max-width">Širina radne površine (sadržaja):</label>
                    <select id="setting-content-max-width">
                        <option value="750px">Usko (750px za lakše čitanje)</option>
                        <option value="1100px">Normalno (1100px)</option>
                        <option value="100%">Puni ekran (100%)</option>
                    </select>
                </div>
            </div>

            <!-- Tab 4: Uređivač -->
            <div id="tab-editor" class="settings-tab-content">
                <div class="settings-row">
                    <label for="setting-auto-save">Automatsko spremanje:</label>
                    <select id="setting-auto-save">
                        <option value="0">Isključeno (Ručno spremanje)</option>
                        <option value="30">Svakih 30 sekundi</option>
                        <option value="60">Svaku minutu</option>
                    </select>
                </div>

                <div class="settings-row">
                    <label for="setting-new-template">Zadani predložak za nove stranice:</label>
                    <textarea id="setting-new-template" rows="4" placeholder="Unesite HTML ili tekst koji se automatski umeće..."></textarea>
                </div>
            </div>

            <div class="modal-actions" style="margin-top: 18px; border-top: 1px solid var(--border); padding-top: 12px;">
                <button id="settings-modal-cancel" class="btn btn-secondary" onclick="closeSettingsModal()">Odustani</button>
                <button id="settings-modal-save" class="btn" onclick="saveSettings()">Spremi postavke</button>
            </div>
        </div>
    </div>

    <script>
        let pagesData = { files: [], structure: {}, settings: {} };
        let currentFilename = null;
        let currentPageData = null;
        let editorInstance = null;
        let collapsedNodes = new Set();
        let allCollapsedState = true;
        let autoSaveTimer = null;
        let lastSavedContent = '';
        let selectedHistoryFilename = null;
        // Bilježi vrijeme zadnjeg spremanja (ručnog ili automatskog) po nazivu datoteke,
        // da bi se moglo prikazati u pregledu stranice.
        let lastSaveInfo = {};

        const sidebar = document.getElementById('sidebar');
        const resizer = document.getElementById('sidebar-resizer');
        let isResizing = false;

        resizer.addEventListener('mousedown', (e) => {
            isResizing = true;
            resizer.classList.add('resizing');
            document.body.style.cursor = 'ew-resize';
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return;
            let newWidth = e.clientX;
            if (newWidth < 180) newWidth = 180;
            if (newWidth > 550) newWidth = 550;
            sidebar.style.width = newWidth + 'px';
        });

        document.addEventListener('mouseup', () => {
            if (isResizing) {
                isResizing = false;
                resizer.classList.remove('resizing');
                document.body.style.cursor = 'default';
            }
        });

        function switchSettingsTab(tabName, event) {
            document.querySelectorAll('.settings-tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.settings-tab-btn').forEach(el => el.classList.remove('active'));

            document.getElementById('tab-' + tabName).classList.add('active');
            if (event && event.target) {
                event.target.classList.add('active');
            }
        }

        function applySettingsToUI(settings) {
            if (!settings) return;
            const root = document.documentElement;
            if (settings.fontFamily) root.style.setProperty('--app-font', settings.fontFamily);
            if (settings.fontSize) root.style.setProperty('--app-font-size', settings.fontSize);
            if (settings.lineHeight) root.style.setProperty('--app-line-height', settings.lineHeight);
            if (settings.contentMaxWidth) root.style.setProperty('--content-max-width', settings.contentMaxWidth);

            if (settings.theme === 'dark') {
                document.body.classList.add('theme-dark');
            } else {
                document.body.classList.remove('theme-dark');
            }

            const tableStyle = settings.tableStyle || 'grid';
            const tableWidth = settings.tableWidth || '100%';
            const tableHover = settings.tableHover === '1' ? '1' : '0';
            const tableSticky = settings.tableSticky === '1' ? '1' : '0';

            document.body.setAttribute('data-table-style', tableStyle);
            document.body.setAttribute('data-table-hover', tableHover);
            document.body.setAttribute('data-table-sticky', tableSticky);
            root.style.setProperty('--table-width', tableWidth);

            if (editorInstance) {
                const editable = editorInstance.ui.view.editable.element;
                if (editable) {
                    editable.setAttribute('data-table-style', tableStyle);
                    editable.setAttribute('data-table-hover', tableHover);
                    editable.setAttribute('data-table-sticky', tableSticky);
                }
            }

            setupAutoSaveInterval(settings.autoSave);
        }

        function setupAutoSaveInterval(intervalSec) {
            if (autoSaveTimer) clearInterval(autoSaveTimer);
            const sec = parseInt(intervalSec) || 0;
            if (sec > 0) {
                autoSaveTimer = setInterval(() => {
                    const editorWrapper = document.getElementById('editor-wrapper');
                    if (editorWrapper && editorWrapper.style.display === 'flex' && currentFilename) {
                        const currentContent = editorInstance ? editorInstance.getData() : '';
                        if (currentContent !== lastSavedContent) {
                            saveCurrentPage(true);
                        }
                    }
                }, sec * 1000);
            }
        }

        function openSettingsModal() {
            const settings = pagesData.settings || {};
            if (settings.fontFamily) document.getElementById('setting-font-family').value = settings.fontFamily;
            if (settings.fontSize) document.getElementById('setting-font-size').value = settings.fontSize;
            if (settings.lineHeight) document.getElementById('setting-line-height').value = settings.lineHeight;
            if (settings.theme) document.getElementById('setting-theme').value = settings.theme;

            if (settings.tableStyle) document.getElementById('setting-table-style').value = settings.tableStyle;
            if (settings.tableWidth) document.getElementById('setting-table-width').value = settings.tableWidth;
            document.getElementById('setting-table-hover').checked = (settings.tableHover === '1');
            document.getElementById('setting-table-sticky').checked = (settings.tableSticky === '1');

            if (settings.sidebarState) document.getElementById('setting-sidebar-state').value = settings.sidebarState;
            document.getElementById('setting-remember-page').checked = (settings.rememberPage === '1');
            if (settings.contentMaxWidth) document.getElementById('setting-content-max-width').value = settings.contentMaxWidth;

            if (settings.autoSave) document.getElementById('setting-auto-save').value = settings.autoSave;
            if (settings.newTemplate !== undefined) document.getElementById('setting-new-template').value = settings.newTemplate;

            switchSettingsTab('appearance', { target: document.querySelector('.settings-tab-btn') });

            document.getElementById('settings-modal-overlay').classList.add('visible');
        }

        function closeSettingsModal() {
            document.getElementById('settings-modal-overlay').classList.remove('visible');
        }

        async function saveSettings() {
            const formData = new FormData();
            formData.append('action', 'save_settings');
            formData.append('font_family', document.getElementById('setting-font-family').value);
            formData.append('font_size', document.getElementById('setting-font-size').value);
            formData.append('line_height', document.getElementById('setting-line-height').value);
            formData.append('theme', document.getElementById('setting-theme').value);

            formData.append('table_style', document.getElementById('setting-table-style').value);
            formData.append('table_width', document.getElementById('setting-table-width').value);
            formData.append('table_hover', document.getElementById('setting-table-hover').checked ? '1' : '0');
            formData.append('table_sticky', document.getElementById('setting-table-sticky').checked ? '1' : '0');

            formData.append('sidebar_state', document.getElementById('setting-sidebar-state').value);
            formData.append('remember_page', document.getElementById('setting-remember-page').checked ? '1' : '0');
            formData.append('content_max_width', document.getElementById('setting-content-max-width').value);

            formData.append('auto_save', document.getElementById('setting-auto-save').value);
            formData.append('new_template', document.getElementById('setting-new-template').value);

            const res = await apiRequest(formData);
            if (res.success) {
                pagesData.settings = res.settings;
                applySettingsToUI(res.settings);
                closeSettingsModal();
            }
        }

        // UPRAVLJANJE POVIJEŠĆU VERZIJA
        async function openHistoryModal() {
            if (!currentFilename) return;
            selectedHistoryFilename = null;
            document.getElementById('btn-restore-version').disabled = true;
            document.getElementById('history-preview').innerHTML = '<span style="color: #888;">Odaberite verziju s lijeve strane za pregled sadržaja.</span>';

            const formData = new FormData();
            formData.append('action', 'get_history');
            formData.append('filename', currentFilename);

            const res = await apiRequest(formData);
            const listContainer = document.getElementById('history-list');
            listContainer.innerHTML = '';

            if (res.success && res.history && res.history.length > 0) {
                res.history.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'history-item';
                    div.innerHTML = `<strong>${item.date}</strong><br><span style="font-size:0.75rem; color:#888;">(${Math.round(item.size / 1024 * 10) / 10} KB)</span>`;
                    div.onclick = () => {
                        document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
                        div.classList.add('active');
                        selectedHistoryFilename = item.filename;
                        document.getElementById('btn-restore-version').disabled = false;
                        loadHistoryVersionPreview(item.filename);
                    };
                    listContainer.appendChild(div);
                });
            } else {
                listContainer.innerHTML = '<div style="font-size: 0.85rem; color: #888; padding: 10px;">Nema spremljenih starih verzija za ovu stranicu.</div>';
            }

            document.getElementById('history-modal-overlay').classList.add('visible');
        }

        function closeHistoryModal() {
            document.getElementById('history-modal-overlay').classList.remove('visible');
        }

        async function loadHistoryVersionPreview(histFilename) {
            const formData = new FormData();
            formData.append('action', 'load_history_version');
            formData.append('hist_filename', histFilename);

            const res = await apiRequest(formData);
            if (res.success) {
                const doc = new DOMParser().parseFromString(res.content, 'text/html');
                const h1 = doc.querySelector('h1');
                if (h1) h1.remove();
                document.getElementById('history-preview').innerHTML = doc.body.innerHTML;
            } else {
                document.getElementById('history-preview').innerHTML = '<span style="color: red;">Greška pri učitavanju verzije.</span>';
            }
        }

        async function restoreSelectedVersion() {
            if (!selectedHistoryFilename || !currentFilename) return;

            const confirmed = await showConfirmModal('Jeste li sigurni da želite vratiti ovu verziju? Trenutni sadržaj stranice bit će automatski spremljen u povijest prije vraćanja.');
            if (confirmed) {
                const formData = new FormData();
                formData.append('action', 'restore_version');
                formData.append('hist_filename', selectedHistoryFilename);
                formData.append('target_filename', currentFilename);

                const res = await apiRequest(formData);
                if (res.success) {
                    closeHistoryModal();
                    loadPage(currentFilename); // Ponovno učitaj stranicu
                } else {
                    alert(res.error);
                }
            }
        }

        function showConfirmModal(message) {
            return new Promise((resolve) => {
                const overlay = document.getElementById('confirm-modal-overlay');
                const msgEl = document.getElementById('confirm-modal-message');
                const okBtn = document.getElementById('confirm-modal-ok');
                const cancelBtn = document.getElementById('confirm-modal-cancel');

                msgEl.textContent = message;
                overlay.classList.add('visible');

                function cleanup(result) {
                    overlay.classList.remove('visible');
                    okBtn.removeEventListener('click', onOk);
                    cancelBtn.removeEventListener('click', onCancel);
                    overlay.removeEventListener('click', onOverlayClick);
                    resolve(result);
                }
                function onOk() { cleanup(true); }
                function onCancel() { cleanup(false); }
                function onOverlayClick(e) {
                    if (e.target === overlay) cleanup(false);
                }

                okBtn.addEventListener('click', onOk);
                cancelBtn.addEventListener('click', onCancel);
                overlay.addEventListener('click', onOverlayClick);
            });
        }

        ClassicEditor
            .create(document.querySelector('#editor'), {
                toolbar: [
                    'heading', '|', 
                    'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 
                    'insertTable', 'tableColumn', 'tableRow', 'mergeTableCells', '|', 
                    'undo', 'redo'
                ]
            })
            .then(editor => {
                editorInstance = editor;
                if (pagesData.settings) {
                    applySettingsToUI(pagesData.settings);
                }
            });

        function slugify(text) {
            if (!text) return 'neimenovana-stranica';
            const charMap = { 'č':'c','Č':'c','ć':'c','Ć':'c','đ':'d','Đ':'d','š':'s','Š':'s','ž':'z','Ž':'z' };
            let str = text.split('').map(char => charMap[char] || char).join('');
            return str.toLowerCase().trim().replace(/[^a-z0-9 -]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-');
        }

        async function apiRequest(formData) {
            let response;
            try {
                response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
            } catch (networkErr) {
                alert('Zahtjev prema serveru nije uspio (mrežna greška): ' + networkErr.message);
                throw networkErr;
            }

            const rawText = await response.text();
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                alert('Server nije vratio ispravan JSON (vjerojatno PHP greška).\n\nStatus: ' + response.status + '\n\nOdgovor servera:\n' + rawText.substring(0, 800));
                throw parseErr;
            }
            return data;
        }

        async function fetchPages(skipAutoOpen = false) {
            const formData = new FormData();
            formData.append('action', 'get_pages');
            pagesData = await apiRequest(formData);

            if (pagesData.settings) {
                applySettingsToUI(pagesData.settings);
            }

            const files = pagesData.files || [];
            const structure = pagesData.structure || {};
            const sidebarState = pagesData.settings.sidebarState || 'collapsed';

            collapsedNodes.clear();
            if (sidebarState === 'collapsed') {
                files.forEach(filename => {
                    const hasChildren = files.some(f => (structure[f] ? structure[f].parent : '') === filename);
                    if (hasChildren) {
                        collapsedNodes.add(filename);
                    }
                });
                allCollapsedState = true;
            } else {
                allCollapsedState = false;
            }

            updateToggleLinkText();
            renderSidebar();

            // Automatsko otvaranje zadnje stranice se preskače kad je poziv
            // došao iz tihog auto-save-a unutar uređivača - u suprotnom bi
            // svaki autosave izbacio korisnika iz editora natrag na pregled.
            if (!skipAutoOpen && pagesData.settings.rememberPage === '1' && files.length > 0) {
                const lastPage = localStorage.getItem('last_opened_page');
                if (lastPage && files.includes(lastPage)) {
                    loadPage(lastPage);
                }
            }
        }

        function updateFilenamePreview() {
            const title = document.getElementById('page-title').value;
            const slug = slugify(title);
            document.getElementById('filename-preview').textContent = `pages/${slug}.html`;
        }

        function updateToggleLinkText() {
            const link = document.getElementById('tree-toggle-link');
            if (link) {
                link.textContent = allCollapsedState ? 'Prikaži sve' : 'Sažmi sve';
            }
        }

        function toggleAllCollapse() {
            const files = pagesData.files || [];
            const structure = pagesData.structure || {};

            if (allCollapsedState) {
                collapsedNodes.clear();
                allCollapsedState = false;
            } else {
                files.forEach(filename => {
                    const hasChildren = files.some(f => (structure[f] ? structure[f].parent : '') === filename);
                    if (hasChildren) {
                        collapsedNodes.add(filename);
                    }
                });
                allCollapsedState = true;
            }
            updateToggleLinkText();
            renderSidebar();
        }

        function renderSidebar() {
            const container = document.getElementById('page-tree');
            container.innerHTML = '';

            const files = pagesData.files || [];
            const structure = pagesData.structure || {};

            function buildTree(parentFilename, targetElement) {
                const children = files.filter(f => {
                    const parent = structure[f] ? (structure[f].parent || '') : '';
                    return parent === parentFilename;
                });

                if (children.length === 0) return;

                const ul = document.createElement('ul');
                ul.className = parentFilename ? 'tree-group' : 'tree-group root';

                children.forEach(filename => {
                    const li = document.createElement('li');
                    const pageMeta = structure[filename] || {};
                    const title = pageMeta.title || filename.replace('.html', '');

                    const hasChildren = files.some(f => (structure[f] ? structure[f].parent : '') === filename);
                    const isCollapsed = collapsedNodes.has(filename);

                    const itemWrapper = document.createElement('div');
                    itemWrapper.className = `tree-item-wrapper ${filename === currentFilename ? 'active' : ''}`;
                    itemWrapper.title = title;

                    const arrowSpan = document.createElement('span');
                    arrowSpan.className = `toggle-arrow ${hasChildren ? '' : 'empty'}`;
                    arrowSpan.textContent = hasChildren ? (isCollapsed ? '+' : '-') : '';
                    
                    if (hasChildren) {
                        arrowSpan.onclick = (e) => {
                            e.stopPropagation();
                            if (collapsedNodes.has(filename)) {
                                collapsedNodes.delete(filename);
                            } else {
                                collapsedNodes.add(filename);
                            }
                            renderSidebar();
                        };
                    }

                    const iconSpan = document.createElement('span');
                    iconSpan.className = hasChildren ? 'icon-folder' : 'icon-file';

                    const titleSpan = document.createElement('span');
                    titleSpan.textContent = title;

                    itemWrapper.appendChild(arrowSpan);
                    itemWrapper.appendChild(iconSpan);
                    itemWrapper.appendChild(titleSpan);

                    itemWrapper.onclick = () => {
                        loadPage(filename);
                    };

                    li.appendChild(itemWrapper);

                    if (hasChildren && !isCollapsed) {
                        buildTree(filename, li);
                    }

                    ul.appendChild(li);
                });

                targetElement.appendChild(ul);
            }

            buildTree('', container);
            updateParentDropdown();
        }

        function updateParentDropdown() {
            const select = document.getElementById('page-parent');
            const selectedVal = select.value;
            select.innerHTML = '<option value="">-- Bez roditelja (Glavni fajl) --</option>';

            const files = pagesData.files || [];
            const structure = pagesData.structure || {};

            files.forEach(filename => {
                if (filename !== currentFilename) {
                    const title = (structure[filename] && structure[filename].title) ? structure[filename].title : filename;
                    const option = document.createElement('option');
                    option.value = filename;
                    option.textContent = title;
                    select.appendChild(option);
                }
            });

            select.value = selectedVal;
        }

        function updateViewAutosaveStatus(filename) {
            const el = document.getElementById('view-autosave-status');
            if (!el) return;
            const info = lastSaveInfo[filename];
            el.textContent = info ? `${info.auto ? 'Auto-spremljeno' : 'Spremljeno'} ${info.time}` : '';
        }

        function hideAllPanels() {
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('view-wrapper').style.display = 'none';
            document.getElementById('editor-wrapper').style.display = 'none';
        }

        function showNewPageForm() {
            currentFilename = null;
            currentPageData = null;
            lastSavedContent = '';
            hideAllPanels();
            document.getElementById('editor-wrapper').style.display = 'flex';
            document.getElementById('autosave-status').textContent = '';

            document.getElementById('page-title').value = '';
            document.getElementById('page-parent').value = '';
            
            const templateContent = (pagesData.settings && pagesData.settings.newTemplate) ? pagesData.settings.newTemplate : '';
            if (editorInstance) editorInstance.setData(templateContent);

            updateFilenamePreview();
            updateParentDropdown();
            renderSidebar();
        }

        async function loadPage(filename) {
            currentFilename = filename;
            if (pagesData.settings && pagesData.settings.rememberPage === '1') {
                localStorage.setItem('last_opened_page', filename);
            }

            const formData = new FormData();
            formData.append('action', 'load_page');
            formData.append('filename', filename);

            const res = await apiRequest(formData);

            if (res.success) {
                const structure = pagesData.structure[filename] || {};
                const doc = new DOMParser().parseFromString(res.content, 'text/html');
                const h1 = doc.querySelector('h1');
                if (h1) h1.remove();

                currentPageData = {
                    title: structure.title || filename.replace('.html', ''),
                    parent: structure.parent || '',
                    bodyHtml: doc.body.innerHTML
                };
                lastSavedContent = doc.body.innerHTML;

                hideAllPanels();
                document.getElementById('view-wrapper').style.display = 'flex';
                document.getElementById('view-title').textContent = currentPageData.title;
                document.getElementById('view-content').innerHTML = currentPageData.bodyHtml;
                updateViewAutosaveStatus(filename);

                renderSidebar();
            } else {
                alert(res.error);
            }
        }

        function enterEditMode() {
            if (!currentPageData) return;
            hideAllPanels();
            document.getElementById('editor-wrapper').style.display = 'flex';
            document.getElementById('autosave-status').textContent = '';

            document.getElementById('page-title').value = currentPageData.title;
            updateParentDropdown();
            document.getElementById('page-parent').value = currentPageData.parent;
            updateFilenamePreview();

            if (editorInstance) editorInstance.setData(currentPageData.bodyHtml);
            lastSavedContent = currentPageData.bodyHtml;
        }

        function revertChanges() {
            if (!currentPageData) {
                if (editorInstance) editorInstance.setData('');
                return;
            }
            document.getElementById('page-title').value = currentPageData.title;
            document.getElementById('page-parent').value = currentPageData.parent;
            if (editorInstance) editorInstance.setData(currentPageData.bodyHtml);
            updateFilenamePreview();
            document.getElementById('autosave-status').textContent = 'Vraćeno na izvorno.';
        }

        async function saveCurrentPage(isSilent = false) {
            const title = document.getElementById('page-title').value.trim();
            const parentId = document.getElementById('page-parent').value;
            const content = editorInstance ? editorInstance.getData() : '';

            if (!title) {
                if (!isSilent) alert('Unesite naslov stranice.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'save_page');
            formData.append('title', title);
            formData.append('content', content);
            formData.append('old_filename', currentFilename || '');
            formData.append('parent_id', parentId);
            formData.append('is_autosave', isSilent ? '1' : '0');

            const res = await apiRequest(formData);

            if (res.success) {
                currentFilename = res.filename;
                lastSavedContent = content;
                
                currentPageData = {
                    title: title,
                    parent: parentId,
                    bodyHtml: content
                };

                await fetchPages(isSilent);

                const now = new Date();
                const timeString = now.toLocaleString('hr-HR');
                lastSaveInfo[currentFilename] = { time: timeString, auto: isSilent };

                if (isSilent) {
                    document.getElementById('autosave-status').textContent = `Automatski spremljeno u ${now.toLocaleTimeString()}`;
                } else {
                    updateViewAutosaveStatus(currentFilename);
                }
            } else {
                if (!isSilent) alert(res.error);
            }
        }

        async function deleteCurrentPage() {
            if (!currentFilename) return;

            const potvrdjeno = await showConfirmModal('Jeste li sigurni da želite izbrisati ovu stranicu s diska? Ova radnja se ne može poništiti.');
            if (potvrdjeno) {
                const formData = new FormData();
                formData.append('action', 'delete_page');
                formData.append('filename', currentFilename);

                const res = await apiRequest(formData);

                if (res.success) {
                    currentFilename = null;
                    currentPageData = null;
                    lastSavedContent = '';
                    localStorage.removeItem('last_opened_page');
                    hideAllPanels();
                    document.getElementById('empty-state').style.display = 'flex';
                    await fetchPages();
                }
            }
        }

        // Presretanje klikova na interne linkove unutar prikaza stranice (TOC/poveznice
        // između stranica). Ako link (npr. iz "insert link" u CKEditor-u) pokazuje na
        // naziv datoteke koji odgovara postojećoj stranici (npr. "moje-poglavlje.html"
        // ili "pages/moje-poglavlje.html"), otvara se kroz loadPage() unutar aplikacije
        // umjesto punog preusmjeravanja na sirovi statički HTML izvan sučelja.
        document.getElementById('view-content').addEventListener('click', function (e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href) return;

            // Vanjski linkovi, sidro-linkovi i posebni protokoli - ne diramo, normalno ponašanje
            if (href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || /^[a-z][a-z0-9+.-]*:\/\//i.test(href)) {
                return;
            }

            const filenameCandidate = href.split('/').pop().split('?')[0].split('#')[0];
            const files = pagesData.files || [];

            if (filenameCandidate.endsWith('.html') && files.includes(filenameCandidate)) {
                e.preventDefault();
                loadPage(filenameCandidate);
            }
        });

        fetchPages();
    </script>
</body>
</html>
