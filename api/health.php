<?php
/**
 * api/health.php
 *
 * GET /api/health.php - simple uptime/config check for QA and deployment.
 */

require_once __DIR__ . '/../includes/helpers.php';

applyCors();
sendJson(['success' => true, 'message' => 'Mfano Bora Resources API is running.']);
