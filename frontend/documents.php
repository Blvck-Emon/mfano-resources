<?php
/**
 * frontend/documents.php
 *
 * REWRITTEN: this used to hold a hardcoded array of ~100 documents pointing
 * at static file paths (resources/category-1/....pdf) that mostly did not
 * exist on disk and had no relationship to the admin panel or database.
 *
 * It now:
 *   - Resolves ?category= as a category slug (or the legacy category-N form)
 *     against the categories table.
 *   - Lists only PUBLISHED resources (is_published = 1), grouped under the
 *     exact sub-category the admin assigned when adding the PDF.
 *   - Sends every "View / Download Document" click through
 *     Backend+AdminPanel/api/download.php, which streams the file AND
 *     writes a row to download_logs — visible immediately in the admin
 *     panel's "05 - Activity / Download Logs" module.
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
<section class="resources-section">
<div class="container">

<a href="index.php" class="back-link">← Back to Categories</a>

<div class="section-heading document-heading">
    <span class="section-label">DOCUMENTS</span>
    <h2><?php echo htmlspecialchars($category['name']); ?></h2>
    <p><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
</div>

<?php if ($totalDocuments === 0): ?>

    <div class="no-results" style="display:block;">
        <div class="no-results-icon">📭</div>
        <h3>No published documents yet</h3>
        <p>This category doesn't have any published resources yet. Please check back soon.</p>
    </div>

<?php else: ?>

    <?php foreach ($subcategories as $subcategory): ?>
        <?php if (empty($subcategory['files'])) continue; ?>
        <div class="subcategory-block">
            <h3 class="subcategory-title"><?php echo htmlspecialchars($subcategory['name']); ?></h3>

            <div class="categories-grid">
                <?php foreach ($subcategory['files'] as $file): ?>
                    <article class="category-card document-card">
                        <div class="category-icon">
                            <?php echo ((int) ($file['is_featured'] ?? 0)) ? '⭐' : '📄'; ?>
                        </div>

                        <div class="category-content">
                            <h3><?php echo htmlspecialchars($file['title']); ?></h3>
                            <p><?php echo htmlspecialchars($file['description']); ?></p>
                        </div>

                        <div class="category-footer">
                            <span class="document-count">
                                PDF<?php $size = mfano_format_size((int) ($file['file_size_kb'] ?? 0)); ?><?php echo $size ? ' · ' . $size : ''; ?>
                            </span>

                            <a href="<?php echo htmlspecialchars(mfano_download_url((int) $file['id'])); ?>"
                               class="documents-button">
                                View / Download <span>→</span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

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

</body>
</html>