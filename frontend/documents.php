<?php
/**
 * frontend/documents.php
 *
 * Combined Implementation:
 * - Server-side category resolution and published resource fetching via bootstrap helpers.
 * - Modern two-column layout: Left column lists categories/subcategories/resources;
 *   Right column provides an in-page tabbed document viewer.
 * - Integrates tracking via Backend+AdminPanel/api/download.php for views and downloads.
 */

require_once __DIR__ . '/inc/bootstrap.php';

$pdo = getDbConnection();

$categoryParam = trim((string) ($_GET['category'] ?? ''));
$category = mfano_resolve_category($pdo, $categoryParam);

if (!$category) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Category not found | Mfano Africa ICT Hub</title>
        <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    </head>
    <body>
        <main class="container" style="padding:4rem 1rem;text-align:center;">
            <h1>Category not found</h1>
            <p>This resource category does not exist or may have been removed.</p>
            <a href="index.php" class="documents-button">← Back to Categories</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

$subcategories = mfano_get_published_resources_for_category($pdo, (int) $category['id']);
$totalDocuments = array_sum(array_map(fn($sc) => count($sc['files']), $subcategories));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category['name']); ?> | Mfano Africa ICT Hub</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Two-Column App & Viewer Layout Styles */
        .docs-app { display: flex; gap: 16px; padding: 16px; box-sizing: border-box; min-height: 80vh; align-items: flex-start; }
        .docs-left { width: 38%; max-width: 450px; max-height: calc(100vh - 160px); overflow-y: auto; border-right: 1px solid #e4e6eb; padding-right: 12px; }
        .docs-right { flex: 1; display: flex; flex-direction: column; gap: 8px; position: sticky; top: 16px; }
        
        .subcategory-block { margin-bottom: 20px; }
        .subcategory-title { font-size: 1.1rem; font-weight: 600; color: #333; margin-bottom: 10px; border-bottom: 2px solid #007bff; padding-bottom: 4px; }
        
        .document-card-item { padding: 12px; border: 1px solid #e1e4e8; margin-bottom: 10px; border-radius: 6px; background: #fff; transition: box-shadow 0.2s; }
        .document-card-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .document-card-item h4 { margin: 0 0 6px 0; font-size: 1rem; color: #24292e; }
        .document-card-item p { margin: 0 0 10px 0; font-size: 0.85rem; color: #586069; }
        
        .resource-controls { display: flex; gap: 8px; align-items: center; justify-content: space-between; font-size: 0.85rem; }
        .btn-action { padding: 6px 12px; cursor: pointer; border-radius: 4px; border: 1px solid #007bff; background: #007bff; color: white; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px; }
        .btn-action.btn-secondary { background: #f1f3f5; color: #333; border-color: #ced4da; }
        .btn-action.btn-secondary:hover { background: #e2e6ea; }
        .btn-action:hover { background: #0056b3; }

        /* Tabs & Viewer Styles */
        .tabs-bar { display: flex; gap: 6px; align-items: center; padding: 6px; border-bottom: 1px solid #ddd; overflow-x: auto; background: #f8f9fa; border-radius: 4px 4px 0 0; }
        .tab-item { padding: 6px 12px; border-radius: 4px 4px 0 0; background: #e9ecef; cursor: pointer; border: 1px solid #ced4da; border-bottom: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
        .tab-item.active { background: white; font-weight: 600; border-color: #007bff; color: #007bff; }
        .tab-close { font-size: 12px; padding: 0 4px; border-radius: 50%; color: #6c75d6; cursor: pointer; }
        .tab-close:hover { background: #dc3545; color: white; }
        
        .viewer-area { flex: 1; background: #fff; border: 1px solid #ddd; border-radius: 0 0 4px 4px; overflow: hidden; min-height: 600px; display: flex; flex-direction: column; }
        .viewer-empty { color: #6c757d; padding: 40px; text-align: center; margin: auto; font-size: 0.95rem; }
        .viewer-toolbar { display: flex; gap: 8px; align-items: center; padding: 8px 12px; background: #f8f9fa; border-bottom: 1px solid #eee; }
        .viewer-iframe { width: 100%; height: calc(100vh - 240px); border: 0; display: block; }
        
        @media(max-width: 900px) {
            .docs-app { flex-direction: column; }
            .docs-left, .docs-right { width: 100%; max-width: none; position: static; }
            .viewer-iframe { height: 500px; }
        }
    </style>
</head>

<body>

<header class="site-header">
    <div class="container header-container">
        <a href="index.php" class="logo">
            <div class="logo-mark"><img src="logo.png" alt="Mfano Africa logo"></div>
            <div class="logo-text">
                <strong>MFANO AFRICA</strong>
                <span>ICT HUB</span>
            </div>
        </a>
        <nav class="main-navigation">
            <a href="index.php">Resource Centre</a>
        </nav>
    </div>
</header>

<main>
<section class="resources-section" style="padding: 20px 0;">
<div class="container" style="max-width: 100%; padding: 0 20px;">

    <a href="index.php" class="back-link" style="display:inline-block; margin-bottom: 12px;">← Back to Categories</a>

    <div class="section-heading document-heading" style="margin-bottom: 16px;">
        <span class="section-label">DOCUMENTS</span>
        <h2><?php echo htmlspecialchars($category['name']); ?></h2>
        <p><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
    </div>

    <?php if ($totalDocuments === 0): ?>
        <div class="no-results" style="display:block; text-align:center; padding: 40px;">
            <div class="no-results-icon" style="font-size: 2rem;">📭</div>
            <h3>No published documents yet</h3>
            <p>This category doesn't have any published resources yet. Please check back soon.</p>
        </div>
    <?php else: ?>

        <div class="docs-app">
            <!-- Left Column: Navigation & Document List -->
            <aside class="docs-left">
                <?php foreach ($subcategories as $subcategory): ?>
                    <?php if (empty($subcategory['files'])) continue; ?>
                    <div class="subcategory-block">
                        <h3 class="subcategory-title"><?php echo htmlspecialchars($subcategory['name']); ?></h3>

                        <div>
                            <?php foreach ($subcategory['files'] as $file): ?>
                                <?php 
                                    $fileId = (int) $file['id'];
                                    $fileTitle = $file['title'];
                                    $fileDesc = $file['description'] ?? '';
                                    $viewUrl = mfano_download_url($fileId) . '&action=view';
                                    $downloadUrl = mfano_download_url($fileId) . '&action=download';
                                    $size = mfano_format_size((int) ($file['file_size_kb'] ?? 0));
                                    $isFeatured = (int) ($file['is_featured'] ?? 0);
                                ?>
                                <article class="document-card-item">
                                    <h4><?php echo $isFeatured ? '⭐ ' : ''; ?><?php echo htmlspecialchars($fileTitle); ?></h4>
                                    <?php if (!empty($fileDesc)): ?>
                                        <p><?php echo htmlspecialchars($fileDesc); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="resource-controls">
                                        <span style="font-size: 0.75rem; color: #6c757d;">
                                            PDF<?php echo $size ? ' · ' . $size : ''; ?>
                                        </span>
                                        <div style="display: flex; gap: 6px;">
                                            <button type="button" class="btn-action btn-secondary" 
                                                    onclick="openDocumentTab(<?php echo $fileId; ?>, '<?php echo htmlspecialchars(addslashes($fileTitle), ENT_QUOTES); ?>', '<?php echo htmlspecialchars($viewUrl, ENT_QUOTES); ?>')">
                                                View 👁️
                                            </button>
                                            <a href="<?php echo htmlspecialchars($downloadUrl); ?>" class="btn-action">
                                                Download ⬇
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </aside>

            <!-- Right Column: Tabbed Viewer Pane -->
            <section class="docs-right">
                <div class="tabs-bar" id="tabsBar" style="display:none;">
                    <div id="tabsHeader" style="display:flex; gap:6px; overflow-x:auto; flex:1;"></div>
                    <button type="button" class="btn-action btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="closeAllTabs()" title="Close all tabs">Close All</button>
                </div>
                
                <div class="viewer-area" id="viewerArea">
                    <div id="viewerEmpty" class="viewer-empty">
                        <div>📄</div>
                        <h3 style="margin: 8px 0 4px 0; color: #495057;">No document selected</h3>
                        <p style="margin: 0;">Click <strong>"View 👁️"</strong> on any resource to open it in an in-page tab on the right.</p>
                    </div>
                    <div id="viewerTabsContent" style="display:none; flex-direction:column; height: 100%;">
                        <div class="viewer-toolbar" id="viewerToolbar">
                            <span id="activeTabTitle" style="font-weight: 600; font-size: 0.9rem; color: #333;">—</span>
                            <div style="flex:1;"></div>
                            <a id="openExternalBtn" href="#" target="_blank" class="btn-action btn-secondary" style="font-size: 0.75rem; padding: 4px 8px;">Open in Browser Tab ↗</a>
                            <button type="button" class="btn-action btn-secondary" style="font-size: 0.75rem; padding: 4px 8px;" onclick="closeActiveTab()">Close Tab</button>
                        </div>
                        <div id="iframesContainer" style="flex:1; position:relative;">
                            <!-- Dynamic iframes injected here -->
                        </div>
                    </div>
                </div>
            </section>
        </div>

    <?php endif; ?>

</div>
</section>
</main>

<footer class="site-footer">
    <div class="footer-bottom">
        <div class="container">
            <p>© <?php echo date("Y"); ?> Mfano Bora Africa. All Rights Reserved.</p>
        </div>
    </div>
</footer>

<script>
    // Tabbed Viewer Client-Side Logic
    const openTabs = new Map();
    let activeTabId = null;

    function openDocumentTab(id, title, url) {
        const tabsBar = document.getElementById('tabsBar');
        const tabsHeader = document.getElementById('tabsHeader');
        const viewerEmpty = document.getElementById('viewerEmpty');
        const viewerTabsContent = document.getElementById('viewerTabsContent');
        const iframesContainer = document.getElementById('iframesContainer');
        const activeTabTitle = document.getElementById('activeTabTitle');
        const openExternalBtn = document.getElementById('openExternalBtn');

        viewerEmpty.style.display = 'none';
        viewerTabsContent.style.display = 'flex';
        tabsBar.style.display = 'flex';

        if (openTabs.has(id)) {
            switchTab(id);
            return;
        }

        // Create Tab element
        const tabEl = document.createElement('div');
        tabEl.className = 'tab-item active';
        tabEl.id = `tab-btn-${id}`;
        tabEl.innerHTML = `
            <span onclick="switchTab(${id})" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;" title="${title}">${title}</span>
            <span class="tab-close" onclick="event.stopPropagation(); closeTab(${id})">&times;</span>
        `;
        tabEl.onclick = () => switchTab(id);
        tabsHeader.appendChild(tabEl);

        // Create Iframe element
        const iframeEl = document.createElement('iframe');
        iframeEl.className = 'viewer-iframe';
        iframeEl.id = `iframe-${id}`;
        iframeEl.src = url;
        iframeEl.style.display = 'none';
        iframesContainer.appendChild(iframeEl);

        openTabs.set(id, { title, url, tabEl, iframeEl });
        switchTab(id);
    }

    function switchTab(id) {
        openTabs.forEach((data, key) => {
            if (key === id) {
                data.tabEl.classList.add('active');
                data.iframeEl.style.display = 'block';
                activeTabId = id;
                document.getElementById('activeTabTitle').textContent = data.title;
                document.getElementById('openExternalBtn').href = data.url;
            } else {
                data.tabEl.classList.remove('active');
                data.iframeEl.style.display = 'none';
            }
        });
    }

    function closeTab(id) {
        const data = openTabs.get(id);
        if (!data) return;

        data.tabEl.remove();
        data.iframeEl.remove();
        openTabs.delete(id);

        if (openTabs.size === 0) {
            activeTabId = null;
            document.getElementById('tabsBar').style.display = 'none';
            document.getElementById('viewerTabsContent').style.display = 'none';
            document.getElementById('viewerEmpty').style.display = 'block';
        } else {
            const nextId = openTabs.keys().next().value;
            switchTab(nextId);
        }
    }

    function closeActiveTab() {
        if (activeTabId !== null) {
            closeTab(activeTabId);
        }
    }

    function closeAllTabs() {
        openTabs.forEach((data) => {
            data.tabEl.remove();
            data.iframeEl.remove();
        });
        openTabs.clear();
        activeTabId = null;
        document.getElementById('tabsBar').style.display = 'none';
        document.getElementById('viewerTabsContent').style.display = 'none';
        document.getElementById('viewerEmpty').style.display = 'block';
    }
</script>

</body>
</html>