<?php
/**
 * frontend/index.php
 *
 * REWRITTEN: this used to hold a hardcoded $categories array with fake
 * document counts, fully disconnected from the SQLite database. It now
 * pulls the live category list — with a real, live count of published
 * resources — straight from the same database the admin panel writes to.
 * See frontend/inc/bootstrap.php for the connection + query helpers.
 */

require_once __DIR__ . '/inc/bootstrap.php';

$pdo = getDbConnection();
$categories = mfano_get_categories_with_counts($pdo);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Mfano Bora Africa Resource Centre - Access useful documents, guides, publications and learning resources.">
    <title>Mfano Africa ICT Hub | Resource Centre</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
</head>

<body>

    <!-- ================= HEADER ================= -->
    <header class="site-header">
        <div class="container header-container">
            <a href="index.php" class="logo">
                <div class="logo-mark">
                    <img src="logo.png" alt="Mfano Africa logo">
                </div>
                <div class="logo-text">
                    <strong>MFANO AFRICA</strong>
                    <span>ICT HUB</span>
                </div>
            </a>

            <nav class="main-navigation">
                <a href="#">Home</a>
                <a href="#resources" class="active">Resources</a>
                <a href="#about">About</a>
                <a href="#contact">Contact</a>
            </nav>

            <button class="mobile-menu-button" id="mobileMenuButton" aria-label="Open navigation menu">☰</button>
        </div>
    </header>

    <!-- ================= HERO ================= -->
    <section class="hero">
        <div class="container hero-content">
            <div class="hero-text">
                <span class="hero-label">MFANO AFRICA RESOURCE CENTRE</span>
                <h1>
                    <span>Find the knowledge</span>
                    <span>to build your future</span>
                    <span>through <em>learning.</em></span>
                </h1>
                <p>Access useful documents, guides, publications and learning materials designed to support knowledge, innovation and development.</p>
                <div class="hero-actions">
                    <a href="#resources" class="hero-button">Explore Resources <span aria-hidden="true">&#8594;</span></a>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= RESOURCE SECTION ================= -->
    <main id="resources">
        <section class="resources-section">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <span class="section-label">KNOWLEDGE HUB</span>
                        <h2>Browse Documents</h2>
                        <p>Select a category to access its available documents and resources.</p>
                    </div>
                </div>

                <!-- SEARCH -->
                <div class="resource-controls">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" id="resourceSearch" placeholder="Search categories..." autocomplete="off">
                    </div>
                </div>

                <!-- CATEGORY GRID -->
                <div class="categories-grid" id="categoriesGrid">
                    <?php if (empty($categories)): ?>
                        <p class="empty-state">No categories have been configured yet. Add one from the admin panel.</p>
                    <?php endif; ?>

                    <?php foreach ($categories as $index => $category): ?>
                        <article class="category-card"
                                 data-title="<?php echo strtolower(htmlspecialchars($category['name'])); ?>"
                                 data-description="<?php echo strtolower(htmlspecialchars($category['description'] ?? '')); ?>">
                            <div class="category-top">
                                <span class="category-number"><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
                                <div class="category-icon"><?php echo mfano_category_icon((int) $category['id']); ?></div>
                            </div>

                            <div class="category-content">
                                <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                <p><?php echo htmlspecialchars($category['description'] ?? ''); ?></p>
                            </div>

                            <div class="category-footer">
                                <span class="document-count">
                                    <?php echo (int) $category['document_count']; ?>
                                    <?php echo ((int) $category['document_count'] === 1) ? 'Document' : 'Documents'; ?>
                                </span>

                                <a href="documents.php?category=<?php echo urlencode($category['slug']); ?>" class="documents-button">
                                    View Documents <span>→</span>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- NO RESULTS -->
                <div class="no-results" id="noResults">
                    <div class="no-results-icon">🔎</div>
                    <h3>No categories found</h3>
                    <p>Try using a different search term.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- ================= FOOTER ================= -->
    <footer class="site-footer">
        <div class="container footer-container">
            <div class="footer-brand">
                <p>Mfano Bora Africa Ltd is a leading training, consultancy, and innovation company committed to developing future-ready professionals through ICT training, industrial attachments, business consultancy, digital transformation, and industry-focused initiatives across East Africa.</p>
                <h4>Follow Us</h4>
                <div class="social-links">
                    <a href="https://www.facebook.com/groups/487030952227422/" target="_blank" rel="noopener">Facebook</a>
                    <a href="https://twitter.com/LtdMfano" target="_blank" rel="noopener">X</a>
                    <a href="https://www.instagram.com/mfano_bora_africa" target="_blank" rel="noopener">Instagram</a>
                    <a href="https://www.linkedin.com/company/mfano-bora-africa-ltd" target="_blank" rel="noopener">LinkedIn</a>
                </div>
            </div>

            <div class="footer-links">
                <h4>Quick Links</h4>
                <a href="https://www.mfanoboraafrica.com/">Home</a>
                <a href="https://www.mfanoboraafrica.com/hub/">The Hub</a>
                <a href="https://www.mfanoboraafrica.com/consultancy/">Consultancy</a>
                <a href="https://www.mfanoboraafrica.com/attachment/">Industrial Attachments</a>
                <a href="https://www.mfanoboraafrica.com/awards/">Logistics &amp; Transport Awards</a>
                <a href="https://www.mfanoboraafrica.com/blog/">Blog</a>
                <a href="https://www.mfanoboraafrica.com/contact-us/">Contact Us</a>
            </div>

            <div class="footer-links">
                <h4>Services</h4>
                <a href="https://www.mfanoboraafrica.com/hub/">ICT Training</a>
                <a href="https://www.mfanoboraafrica.com/attachment/">Industrial Attachments</a>
                <a href="https://www.mfanoboraafrica.com/consultancy/">Business Consultancy</a>
                <a href="https://www.mfanoboraafrica.com/consultancy/digital-transformation/">Digital Marketing</a>
                <a href="https://www.mfanoboraafrica.com/portfolio/">Software Development</a>
                <a href="https://www.mfanoboraafrica.com/awards/">Transport &amp; Logistics Awards</a>
            </div>

            <div class="footer-contact" id="contact">
                <h4>Contact</h4>
                <p><strong>Location</strong><br>Mfano House<br>Ole Sein Road<br>Near Africa Nazarene University<br>Ongata Rongai, Nairobi, Kenya</p>
                <p><strong>Email</strong><br><a href="mailto:info@mfanoboraafrica.com">info@mfanoboraafrica.com</a></p>
                <p><strong>Operating Hours</strong><br>Monday - Friday: 7:00 AM - 5:00 PM<br>Saturday: 8:00 AM - 1:00 PM<br>Sunday: Closed</p>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <p>© <?php echo date("Y"); ?> Mfano Bora Africa Ltd. All Rights Reserved.</p>
                <div class="legal-links">
                    <a href="https://www.mfanoboraafrica.com/privacy-policy/">Privacy Policy</a>
                    <span>|</span>
                    <a href="https://www.mfanoboraafrica.com/cookie-policy/">Cookie Policy</a>
                    <span>|</span>
                    <a href="https://www.mfanoboraafrica.com/terms-conditions/">Terms &amp; Conditions</a>
                    <span>|</span>
                    <a href="https://www.mfanoboraafrica.com/disclaimer/">Disclaimer</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="js/resources.js"></script>
</body>
</html>
