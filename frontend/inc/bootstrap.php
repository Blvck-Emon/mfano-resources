<?php
/**
 * frontend/inc/bootstrap.php
 *
 * SINGLE SOURCE OF TRUTH BRIDGE
 * ------------------------------------------------------------------
 * Previously, frontend/index.php and frontend/documents.php contained
 * a hardcoded PHP array of categories/documents that had NO connection
 * to the SQLite database, the admin panel, or the /api endpoints.
 * That meant publishing, unpublishing, or deleting a resource in the
 * admin panel ("04 - Library / Existing Resources") had ZERO effect
 * on what the public site displayed — the two halves of the system
 * were not actually wired together.
 *
 * This file makes the public frontend read from the exact same PDO
 * connection / SQLite database file as the admin panel and the public
 * JSON API, so:
 *   - Resources created in the admin panel are only shown once
 *     is_published = 1 (i.e. once "Publish" is pressed).
 *   - The Category / Sub-Category shown on the frontend is always the
 *     live category/sub-category the admin assigned to that PDF.
 *   - Nothing about the resource catalogue is duplicated or
 *     hand-maintained in two places.
 *
 * It intentionally reuses the SAME db.php / helpers.php the backend
 * API uses (require across the "Backend+AdminPanel" folder) instead
 * of re-implementing a second connection, so there is only ever one
 * source of truth for schema + connection settings.
 */

declare(strict_types=1);

define('MFANO_BACKEND_DIR', __DIR__ . '/../../Backend+AdminPanel');
define('MFANO_UPLOADS_URL_BASE', '/Backend+AdminPanel/uploads/resources');
define('MFANO_DOWNLOAD_ENDPOINT', '/Backend+AdminPanel/api/download.php');

require_once MFANO_BACKEND_DIR . '/config/db.php';
require_once MFANO_BACKEND_DIR . '/includes/helpers.php';

/** Decorative icon per category id. Falls back to a generic icon for any
 *  category created later via the admin panel that isn't in this map, so
 *  new categories never render blank. */
function mfano_category_icon(int $categoryId): string
{
    static $icons = [
        1 => '📋', 2 => '💼', 3 => '💻', 4 => '🖥️', 5 => '🚚',
        6 => '🛣️', 7 => '🏆', 8 => '🏢', 9 => '📝', 10 => '📚',
    ];
    return $icons[$categoryId] ?? '📁';
}

/** Returns every category with a LIVE count of its published resources. */
function mfano_get_categories_with_counts(PDO $pdo): array
{
    $sql = "
        SELECT
            c.id, c.name, c.slug, c.description,
            COUNT(r.id) AS document_count
        FROM categories c
        LEFT JOIN sub_categories sc ON sc.category_id = c.id
        LEFT JOIN resources r ON r.sub_category_id = sc.id AND r.is_published = 1
        GROUP BY c.id
        ORDER BY c.id ASC
    ";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/** Resolves a `category` query param that may be a slug (preferred) or the
 *  legacy `category-<id>` form previously hardcoded in the frontend, so old
 *  bookmarked/shared links keep working after this rewrite. */
function mfano_resolve_category(PDO $pdo, string $categoryParam): ?array
{
    if ($categoryParam === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM categories WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $categoryParam]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }

    if (preg_match('/^category-(\d+)$/', $categoryParam, $m)) {
        $stmt = $pdo->prepare('SELECT * FROM categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $m[1]]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    return null;
}

/** Published sub-categories + resources for one category, grouped the same
 *  way the old hardcoded array was, so the existing markup/CSS keeps working. */
function mfano_get_published_resources_for_category(PDO $pdo, int $categoryId): array
{
    $stmt = $pdo->prepare("
        SELECT
            sc.id AS sub_category_id, sc.name AS sub_category_name,
            r.id, r.title, r.description, r.file_url, r.storage_type,
            r.file_size_kb, r.download_count, r.is_featured,
            r.publish_date, r.updated_at
        FROM sub_categories sc
        INNER JOIN resources r ON r.sub_category_id = sc.id AND r.is_published = 1
        WHERE sc.category_id = :category_id
        ORDER BY sc.id ASC, r.is_featured DESC, r.created_at DESC
    ");
    $stmt->execute(['category_id' => $categoryId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grouped = [];
    foreach ($rows as $row) {
        $subId = $row['sub_category_id'];
        if (!isset($grouped[$subId])) {
            $grouped[$subId] = [
                'name'  => $row['sub_category_name'],
                'files' => [],
            ];
        }
        $grouped[$subId]['files'][] = $row;
    }

    return array_values($grouped);
}

/** Builds the public download URL for a resource. Always routes through the
 *  backend's api/download.php so the click is logged in download_logs and
 *  shows up in the admin "05 - Activity / Download Logs" module — this is
 *  the single, canonical download+log path (see README "Data flow"). */
function mfano_download_url(int $resourceId): string
{
    return MFANO_DOWNLOAD_ENDPOINT . '?id=' . $resourceId;
}

function mfano_format_size(int $kb): string
{
    if ($kb <= 0) return '';
    if ($kb < 1024) return $kb . ' KB';
    return number_format($kb / 1024, 1) . ' MB';
}