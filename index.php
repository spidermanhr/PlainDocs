<?php
// DIJAGNOSTIKA - privremeno uključeno da se vidi točan uzrok problema.
// Ukloni ova dva reda kad sve proradi (ne ostavljati uključeno na javnom serveru).
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Putanja do mape sa stranicama
$pagesDir = __DIR__ . '/pages';

// Automatski kreiraj mapu "pages" ako ne postoji
if (!file_exists($pagesDir)) {
    mkdir($pagesDir, 0777, true);
}

// Putanja do conf mape i JSON datoteke
$confDir = __DIR__ . '/conf';
$metaFile = $confDir . '/structure.json';

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
        $writeResult = file_put_contents($metaFile, json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'files' => array_values($existingFiles),
            'structure' => $structure,
            'debug' => [
                'dir' => __DIR__,
                'metaFile' => $metaFile,
                'dir_writable' => is_writable($confDir),
                'write_bytes' => $writeResult
            ]
        ]);
        exit;
    }

    // 2. UČITAVANJE POJEDINAČNE STRANICE
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

    // 3. SPREMANJE STRANICE
    if ($action === 'save_page') {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $oldFilename = basename($_POST['old_filename'] ?? '');
        $parentId = trim($_POST['parent_id'] ?? '');

        if (empty($title)) {
            echo json_encode(['success' => false, 'error' => 'Naslov je obavezan.']);
            exit;
        }

        $slug = slugify($title);
        $newFilename = $slug . '.html';
        $filepath = $pagesDir . '/' . $newFilename;

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

    // 4. BRISANJE STRANICE
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
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            background-color: #f5f5f5;
            color: var(--text-main);
            overflow: hidden;
        }

        #sidebar {
            width: 340px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            padding: 15px 10px;
            user-select: none;
        }

        #sidebar h2 {
            font-size: 1.1rem;
            margin-bottom: 12px;
            color: #444;
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
            margin-bottom: 15px;
            transition: background 0.2s;
        }

        .btn:hover {
            background-color: #1e3d6b;
        }

        .btn-danger {
            background-color: var(--danger);
            color: #fff;
        }

        #page-tree {
            flex-grow: 1;
            overflow-y: auto;
        }

        /* TreeView Dizajn prema slici */
        .tree-group {
            list-style: none;
            padding-left: 20px;
        }

        .tree-group.root {
            padding-left: 0;
        }

        .tree-item-wrapper {
            display: flex;
            align-items: center;
            padding: 4px 6px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.93rem;
            color: #333;
            line-height: 1.4;
        }

        .tree-item-wrapper:hover {
            background-color: var(--bg-hover);
        }

        .tree-item-wrapper.active {
            background-color: #e2edfc;
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
            color: #666;
            transition: transform 0.15s ease;
        }

        .toggle-arrow.collapsed {
            /*transform: rotate(-90deg);*/
        }

        .toggle-arrow.empty {
            visibility: hidden;
        }

        .icon-folder {
            width: 18px;
            height: 18px;
            margin-right: 6px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23eec159'%3E%3Cpath d='M20 6h-8l-2-2H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2z'/%3E%3C/svg%3E") no-repeat center center;
            background-size: contain;
            flex-shrink: 0;
        }

        .icon-file {
            width: 18px;
            height: 18px;
            margin-right: 6px;
            background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23555555' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/%3E%3Cpolyline points='14 2 14 8 20 8'/%3E%3Cline x1='16' y1='13' x2='8' y2='13'/%3E%3Cline x1='16' y1='17' x2='8' y2='17'/%3E%3C/svg%3E") no-repeat center center;
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
        }

        .form-group {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }

        input[type="text"], select {
            background-color: #fff;
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 9px 12px;
            border-radius: 4px;
            outline: none;
        }

        input[type="text"] {
            flex-grow: 1;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .editor-container {
            flex-grow: 1;
            color: #000;
        }

        .ck-editor__editable_inline {
            min-height: 420px;
        }

        /* Stil za tablice - i u editoru (CKEditor) i u read-only pregledu */
        #view-content table,
        .ck-content table {
            border-collapse: collapse;
            width: 100%;
            margin: 15px 0;
        }

        #view-content table th,
        #view-content table td,
        .ck-content table th,
        .ck-content table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        #view-content table th,
        .ck-content table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        #view-content table tr:nth-child(even),
        .ck-content table tr:nth-child(even) {
            background-color: #fafafa;
        }

        #empty-state {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            color: #888;
            font-size: 1.2rem;
            text-align: center;
        }

        /* Prilagođeni modal za potvrdu (npr. brisanje) */
        #confirm-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.45);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        #confirm-modal-overlay.visible {
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
            background: #b8443f;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div id="sidebar">
        <h2>📁 Dokumenti</h2>
        <button class="btn" onclick="showNewPageForm()">+ Nova stranica</button>
        <div id="page-tree"></div>
    </div>

    <!-- Glavni dio -->
    <div id="main-content">
        <div id="empty-state">Odaberite ili izradite novu stranicu.</div>

        <div id="view-wrapper" style="display: none; height: 100%; flex-direction: column;">
            <h2 id="view-title" style="margin-bottom: 15px; color: var(--text-main);"></h2>
            <div id="view-content" style="flex-grow: 1; overflow-y: auto; padding: 5px 0; line-height: 1.6;"></div>
            <div class="actions" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                <button class="btn" onclick="enterEditMode()">✏️ Uredi</button>
                <button class="btn btn-danger" onclick="deleteCurrentPage()">Izbriši</button>
            </div>
        </div>

        <div id="editor-wrapper" style="display: none; height: 100%; flex-direction: column;">
            <div class="form-group">
                <input type="text" id="page-title" placeholder="Naziv stranice..." oninput="updateFilenamePreview()">
                <select id="page-parent">
                    <option value="">-- Bez roditelja (Glavni fajl) --</option>
                </select>
            </div>

            <div style="font-size: 0.85rem; color: #666; margin-bottom: 12px;">
                Putanja na disku: <strong id="filename-preview" style="color: var(--accent);">pages/--</strong>
            </div>

            <div class="editor-container">
                <textarea id="editor"></textarea>
            </div>

            <div class="actions" style="margin-top: 15px;">
                <button class="btn" onclick="saveCurrentPage()">💾 Spremi stranicu</button>
                <button class="btn btn-danger" onclick="deleteCurrentPage()">Izbriši</button>
            </div>
        </div>
    </div>

    <!-- Prilagođeni modal za potvrdu -->
    <div id="confirm-modal-overlay">
        <div id="confirm-modal-box">
            <p id="confirm-modal-message">Jeste li sigurni?</p>
            <div class="modal-actions">
                <button id="confirm-modal-cancel">Odustani</button>
                <button id="confirm-modal-ok">Izbriši</button>
            </div>
        </div>
    </div>

    <script>
        let pagesData = { files: [], structure: {} };
        let currentFilename = null;
        let currentPageData = null; // { title, parent, bodyHtml } za trenutno prikazanu stranicu (view mode)
        let editorInstance = null;
        let collapsedNodes = new Set(); // Sprema stanje otvorenih/zatvorenih mapa

        // Prilagođeni modal za potvrdu - vraća Promise<boolean>
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

        // Inicijalizacija CKEditora
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

        async function fetchPages() {
            const formData = new FormData();
            formData.append('action', 'get_pages');
            pagesData = await apiRequest(formData);
            if (pagesData.debug) {
                console.log('DIJAGNOSTIKA get_pages:', pagesData.debug);
                if (pagesData.debug.dir_writable === false) {
                    alert('UPOZORENJE: Mapa "' + pagesData.debug.dir + '" NIJE upisiva (writable) za PHP proces.\nZato se structure.json ne može spremiti.\nPostavi dozvole (npr. chmod 755/775, ili provjeri vlasnika mape) i pokušaj ponovno.');
                } else if (pagesData.debug.write_bytes === false) {
                    alert('UPOZORENJE: Pokušaj pisanja u "' + pagesData.debug.metaFile + '" nije uspio iz nepoznatog razloga (write_bytes = false), iako se mapa čini upisivom.');
                }
            }
            renderSidebar();
        }

        function updateFilenamePreview() {
            const title = document.getElementById('page-title').value;
            const slug = slugify(title);
            document.getElementById('filename-preview').textContent = `pages/${slug}.html`;
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

                    // Provjeri ima li ova stranica vlastite pod-stranice (ponaša se kao mapa)
                    const hasChildren = files.some(f => (structure[f] ? structure[f].parent : '') === filename);
                    const isCollapsed = collapsedNodes.has(filename);

                    const itemWrapper = document.createElement('div');
                    itemWrapper.className = `tree-item-wrapper ${filename === currentFilename ? 'active' : ''}`;

                    // Strelica za rasklapanje/sklapanje
                    const arrowSpan = document.createElement('span');
                    arrowSpan.className = `toggle-arrow ${hasChildren ? (isCollapsed ? 'collapsed' : '') : 'empty'}`;
                    arrowSpan.textContent = isCollapsed ? '+' : '-';
                    
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

                    // Ikonica (Mapa ako ima djece ili ako je navedena kao roditelj, inače datoteka)
                    const iconSpan = document.createElement('span');
                    iconSpan.className = hasChildren ? 'icon-folder' : 'icon-file';

                    // Naslov
                    const titleSpan = document.createElement('span');
                    titleSpan.textContent = title;

                    itemWrapper.appendChild(arrowSpan);
                    itemWrapper.appendChild(iconSpan);
                    itemWrapper.appendChild(titleSpan);

                    itemWrapper.onclick = () => {
                        loadPage(filename);
                    };

                    li.appendChild(itemWrapper);

                    // Rekurzivno dodaj podređene elemente ako nije sklopljeno
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

        function hideAllPanels() {
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('view-wrapper').style.display = 'none';
            document.getElementById('editor-wrapper').style.display = 'none';
        }

        function showNewPageForm() {
            currentFilename = null;
            currentPageData = null;
            hideAllPanels();
            document.getElementById('editor-wrapper').style.display = 'flex';

            document.getElementById('page-title').value = '';
            document.getElementById('page-parent').value = '';
            if (editorInstance) editorInstance.setData('');

            updateFilenamePreview();
            updateParentDropdown();
            renderSidebar();
        }

        // Klik na stranicu u stablu -> prikaži je READ-ONLY (samo za pregled)
        async function loadPage(filename) {
            currentFilename = filename;

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

                hideAllPanels();
                document.getElementById('view-wrapper').style.display = 'flex';
                document.getElementById('view-title').textContent = currentPageData.title;
                document.getElementById('view-content').innerHTML = currentPageData.bodyHtml;

                renderSidebar();
            } else {
                alert(res.error);
            }
        }

        // Klik na "Uredi" iz pregleda -> tek sad se otvara editor
        function enterEditMode() {
            if (!currentPageData) return;
            hideAllPanels();
            document.getElementById('editor-wrapper').style.display = 'flex';

            document.getElementById('page-title').value = currentPageData.title;
            updateParentDropdown();
            document.getElementById('page-parent').value = currentPageData.parent;
            updateFilenamePreview();

            if (editorInstance) editorInstance.setData(currentPageData.bodyHtml);
        }

        async function saveCurrentPage() {
            const title = document.getElementById('page-title').value.trim();
            const parentId = document.getElementById('page-parent').value;
            const content = editorInstance.getData();

            if (!title) {
                alert('Unesite naslov stranice.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'save_page');
            formData.append('title', title);
            formData.append('content', content);
            formData.append('old_filename', currentFilename || '');
            formData.append('parent_id', parentId);

            const res = await apiRequest(formData);

            if (res.success) {
                currentFilename = res.filename;
                await fetchPages();
                await loadPage(currentFilename); // natrag na pregled, ne ostavljamo editor otvoren
            } else {
                alert(res.error);
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
                    hideAllPanels();
                    document.getElementById('empty-state').style.display = 'flex';
                    await fetchPages();
                }
            }
        }

        // Inicijalno učitavanje pri otvaranju stranice
        fetchPages();
    </script>
</body>
</html>
