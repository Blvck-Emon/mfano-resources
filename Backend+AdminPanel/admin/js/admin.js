/**
 * admin.js
 * 
 * Complete unified admin JavaScript managing dynamic API paths, authentication, 
 * resource management, category/subcategory creation, danger zone operations, 
 * and taxonomy UI synchronization.
 */

(() => {
    'use strict';

    // -------------------------------------------------------------------------
    // 1. Dynamic API Base & Key Management
    // -------------------------------------------------------------------------
    // Dynamically compute API_BASE relative to current page location[cite: 11]
    const API_BASE = (() => {
        const p = window.location.pathname || '/';
        const adminIndex = p.indexOf('/admin/');
        if (adminIndex !== -1) {
            return p.slice(0, adminIndex) + '/api';
        }
        return '../api';
    })();

    const STORAGE_KEY_SESSION = 'admin_api_key';
    const STORAGE_KEY_LOCAL = 'mfano_admin_api_key';
    const CONFIRM_PHRASE = 'YES_I_CONFIRM_DELETE_ALL';

    function getAdminKey() {
        try {
            return sessionStorage.getItem(STORAGE_KEY_SESSION) || 
                   localStorage.getItem(STORAGE_KEY_LOCAL) || '';
        } catch (e) {
            return '';
        }
    }

    function saveAdminKey(key) {
        const trimmed = key.trim();
        try {
            if (trimmed) {
                sessionStorage.setItem(STORAGE_KEY_SESSION, trimmed);
                localStorage.setItem(STORAGE_KEY_LOCAL, trimmed);
            } else {
                sessionStorage.removeItem(STORAGE_KEY_SESSION);
                localStorage.removeItem(STORAGE_KEY_LOCAL);
            }
        } catch (e) {
            console.error('[Admin UI] Unable to update storage key:', e);
        }
    }

    function getHeaders(includeContentType = true) {
        const headers = {};
        if (includeContentType) {
            headers['Content-Type'] = 'application/json';
        }
        const key = getAdminKey();
        if (key) {
            headers['X-Api-Key'] = key;
            headers['X-Admin-Api-Key'] = key; // Fallback support for legacy API endpoints[cite: 11]
        }
        return headers;
    }

    function apiFetch(path, opts = {}) {
        const url = path.startsWith('http') ? path : `${API_BASE}${path.startsWith('/') ? path : '/' + path}`;
        const headers = Object.assign({}, opts.headers || {});
        const key = getAdminKey();

        if (key) {
            headers['X-Api-Key'] = key;
            headers['X-Admin-Api-Key'] = key;
        }

        return fetch(url, Object.assign({ credentials: 'same-origin', headers }, opts));
    }

    // -------------------------------------------------------------------------
    // 2. DOM Ready Initialization & UI Event Listeners
    // -------------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', () => {
        // Element References
        const apiKeyInput = document.getElementById('apiKey') || document.getElementById('apiKeyInput');
        const resourceForm = document.getElementById('resourceForm');
        const categoryForm = document.getElementById('categoryForm') || document.getElementById('addCategoryForm');
        const subCategoryForm = document.getElementById('subCategoryForm') || document.getElementById('addSubCategoryForm');
        const descriptionTextarea = document.getElementById('description') || document.getElementById('resourceDescription');
        const descriptionCount = document.getElementById('descriptionCount');

        // Buttons
        const refreshResourcesBtn = document.getElementById('refreshResourcesBtn');
        const refreshLogsBtn = document.getElementById('refreshLogsBtn');
        const refreshOverviewBtn = document.getElementById('refreshOverviewBtn');
        const exportCsvBtn = document.getElementById('exportCsvBtn') || document.getElementById('exportCsv');
        const resetResourceBtn = document.getElementById('resetResourceBtn');

        // File Pickers
        const fileInput = document.getElementById('fileInput');
        const fileDrop = document.getElementById('fileDrop');
        const filePicked = document.getElementById('filePicked');

        // Danger Zone Modal
        const killAllBtn = document.getElementById('killAllBtn') || document.getElementById('killSwitch');
        const killPreviewBtn = document.getElementById('killPreviewBtn');
        const dangerModal = document.getElementById('dangerModal');
        const dangerModalBackdrop = document.getElementById('dangerModalBackdrop');
        const dangerCancelBtn = document.getElementById('dangerCancelBtn');
        const dangerConfirmBtn = document.getElementById('dangerConfirmBtn');
        const dangerConfirmInput = document.getElementById('dangerConfirmInput') || document.getElementById('confirmDelete');

        // Initial Key Binding
        if (apiKeyInput) {
            apiKeyInput.value = getAdminKey();
            apiKeyInput.addEventListener('input', (e) => saveAdminKey(e.target.value));
            apiKeyInput.addEventListener('change', (e) => saveAdminKey(e.target.value));
        }

        // Helper: Display Notifications
        function showMessage(elementId, text, isError = false) {
            const el = document.getElementById(elementId);
            if (!el) {
                if (isError) console.error(`[Admin UI] ${text}`);
                return;
            }
            el.textContent = text;
            el.className = `message ${isError ? 'message-error' : 'message-success'}`;
            el.style.display = 'block';
            setTimeout(() => {
                el.style.display = 'none';
            }, 5000);
        }

        // Helper: Trigger Taxonomy Dropdown Sync[cite: 1, 11]
        function syncTaxonomyDropdowns() {
            if (window.TaxonomyManager && typeof window.TaxonomyManager.load === 'function') {
                window.TaxonomyManager.load(); // Standard module trigger
            } else if (typeof window.populateAdminCategories === 'function') {
                window.populateAdminCategories(); // Global fallback function[cite: 11]
            }
        }

        // Character Counter
        if (descriptionTextarea && descriptionCount) {
            descriptionTextarea.addEventListener('input', () => {
                descriptionCount.textContent = `${descriptionTextarea.value.length} / 1000`;
            });
        }

        // Source Radio Toggle (File Upload vs URL)
        const sourceRadios = document.querySelectorAll('input[name="source"]');
        const fileSourceField = document.getElementById('fileSourceField');
        const urlSourceField = document.getElementById('urlSourceField');

        if (sourceRadios.length > 0) {
            sourceRadios.forEach(radio => {
                radio.addEventListener('change', (e) => {
                    if (e.target.value === 'file') {
                        if (fileSourceField) fileSourceField.hidden = false;
                        if (urlSourceField) urlSourceField.hidden = true;
                    } else {
                        if (fileSourceField) fileSourceField.hidden = true;
                        if (urlSourceField) urlSourceField.hidden = false;
                    }
                });
            });
        }

        // Drag & Drop File Upload Handler
        if (fileDrop && fileInput) {
            fileDrop.addEventListener('click', () => fileInput.click());

            fileDrop.addEventListener('dragover', (e) => {
                e.preventDefault();
                fileDrop.classList.add('drag-over');
            });

            ['dragleave', 'dragend'].forEach(evt => {
                fileDrop.addEventListener(evt, () => fileDrop.classList.remove('drag-over'));
            });

            fileDrop.addEventListener('drop', (e) => {
                e.preventDefault();
                fileDrop.classList.remove('drag-over');
                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    fileInput.files = e.dataTransfer.files;
                    if (filePicked) filePicked.textContent = fileInput.files[0].name;
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0 && filePicked) {
                    filePicked.textContent = fileInput.files[0].name;
                } else if (filePicked) {
                    filePicked.textContent = 'No file selected';
                }
            });
        }

        if (resetResourceBtn) {
            resetResourceBtn.addEventListener('click', () => {
                if (filePicked) filePicked.textContent = 'No file selected';
                if (descriptionCount) descriptionCount.textContent = '0 / 1000';
            });
        }

        // ---------------------------------------------------------------------
        // 3. Form Submissions (Categories, Subcategories, Resources)
        // ---------------------------------------------------------------------
        
        // Add Category Mutation
        if (categoryForm) {
            categoryForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const nameEl = document.getElementById('categoryName');
                const slugEl = document.getElementById('categorySlug');
                const name = nameEl ? nameEl.value.trim() : '';
                const slug = slugEl ? slugEl.value.trim() : '';

                try {
                    const res = await apiFetch('/admin/categories.php', {
                        method: 'POST',
                        headers: getHeaders(true),
                        body: JSON.stringify({ name, slug })
                    });
                    const data = await res.json();

                    if (res.ok && (data.success || data.id)) {
                        showMessage('categoryMessage', 'Category added successfully!');
                        categoryForm.reset();
                        syncTaxonomyDropdowns(); // Trigger sync upon completion
                    } else {
                        showMessage('categoryMessage', data.error || 'Failed to add category', true);
                    }
                } catch (err) {
                    showMessage('categoryMessage', 'Network error adding category', true);
                }
            });
        }

        // Add Sub-Category Mutation
        if (subCategoryForm) {
            subCategoryForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const parentCatEl = document.getElementById('parentCategory');
                const nameEl = document.getElementById('newSubCategoryName');
                const slugEl = document.getElementById('newSubCategorySlug');

                const category_id = parentCatEl ? parentCatEl.value : '';
                const name = nameEl ? nameEl.value.trim() : '';
                const slug = slugEl ? slugEl.value.trim() : '';

                if (!category_id) {
                    showMessage('subCategoryMessage', 'Please select a parent category.', true);
                    return;
                }

                try {
                    const res = await apiFetch('/admin/subcategories.php', {
                        method: 'POST',
                        headers: getHeaders(true),
                        body: JSON.stringify({ category_id, name, slug })
                    });
                    const data = await res.json();

                    if (res.ok && (data.success || data.id)) {
                        showMessage('subCategoryMessage', 'Sub-category added successfully!');
                        subCategoryForm.reset();
                        syncTaxonomyDropdowns(); // Trigger sync upon completion
                    } else {
                        showMessage('subCategoryMessage', data.error || 'Failed to add sub-category', true);
                    }
                } catch (err) {
                    showMessage('subCategoryMessage', 'Network error adding sub-category', true);
                }
            });
        }

        // Create Resource Submission
        if (resourceForm) {
            resourceForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const title = (document.getElementById('title') || document.getElementById('resourceTitle')).value.trim();
                const subCategoryEl = document.getElementById('subCategory') || document.getElementById('resourceSubCategory');
                const sub_category_id = subCategoryEl ? subCategoryEl.value : '';
                const description = (document.getElementById('description') || document.getElementById('resourceDescription')).value.trim();
                const isFeaturedEl = document.getElementById('isFeatured');
                const is_featured = isFeaturedEl && isFeaturedEl.checked ? 1 : 0;
                
                const sourceTypeRadio = document.querySelector('input[name="source"]:checked');
                const sourceType = sourceTypeRadio ? sourceTypeRadio.value : 'file';

                if (!sub_category_id) {
                    showMessage('message', 'Please select a valid sub-category.', true);
                    return;
                }

                const formData = new FormData();
                formData.append('title', title);
                formData.append('sub_category_id', sub_category_id);
                formData.append('description', description);
                formData.append('is_featured', is_featured);

                if (sourceType === 'file') {
                    if (!fileInput.files[0]) {
                        showMessage('message', 'Please select a PDF file to upload.', true);
                        return;
                    }
                    formData.append('file', fileInput.files[0]);
                } else {
                    const fileUrl = document.getElementById('fileUrl').value.trim();
                    if (!fileUrl) {
                        showMessage('message', 'Please enter a valid file URL.', true);
                        return;
                    }
                    formData.append('file_url', fileUrl);
                }

                try {
                    const headers = getHeaders(false); // Multipart form boundary handling
                    const res = await apiFetch('/admin/resources.php', {
                        method: 'POST',
                        headers,
                        body: formData
                    });
                    const data = await res.json();

                    if (res.ok && (data.success || data.id)) {
                        showMessage('message', 'Resource created successfully!');
                        resourceForm.reset();
                        if (filePicked) filePicked.textContent = 'No file selected';
                        if (descriptionCount) descriptionCount.textContent = '0 / 1000';
                        loadResources();
                        loadOverviewStats();
                    } else {
                        showMessage('message', data.error || 'Failed to create resource', true);
                    }
                } catch (err) {
                    showMessage('message', 'Network error submitting resource', true);
                }
            });
        }

        // ---------------------------------------------------------------------
        // 4. Data Fetching & Table Renderers
        // ---------------------------------------------------------------------
        async function loadResources() {
            const tbody = document.getElementById('resourceTableBody');
            const countEl = document.getElementById('resourceCount');
            const statResourcesEl = document.getElementById('statResources');

            if (!tbody) return;

            try {
                const res = await apiFetch('/resources.php', { cache: 'no-store' });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data = await res.json();
                const resources = Array.isArray(data) ? data : (data.data || []);

                tbody.innerHTML = '';
                if (countEl) countEl.textContent = resources.length;
                if (statResourcesEl) statResourcesEl.textContent = resources.length;

                if (resources.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No resources found.</td></tr>';
                    return;
                }

                resources.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${item.id}</td>
                        <td><strong>${item.title}</strong></td>
                        <td>${item.subcategory_name || item.sub_category_name || item.sub_category_id}</td>
                        <td><span class="badge">${item.storage_type || 'external'}</span></td>
                        <td>${item.download_count || 0}</td>
                        <td>${item.is_published ? 'Published' : 'Draft'}</td>
                        <td>${item.is_featured ? 'Yes' : 'No'}</td>
                        <td>
                            <button class="button button-danger button-small" onclick="deleteResource(${item.id})">Delete</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (err) {
                console.error('[Admin UI] Failed to load resources:', err);
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center; color:red;">Error loading resources.</td></tr>';
            }
        }

        async function loadLogs() {
            const tbody = document.getElementById('logsTableBody');
            const logCountEl = document.getElementById('logCount');

            if (!tbody) return;

            try {
                const res = await apiFetch('/admin/logs.php', { cache: 'no-store' });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                const data = await res.json();
                const logs = Array.isArray(data) ? data : (data.data || data.logs || []);

                tbody.innerHTML = '';
                if (logCountEl) logCountEl.textContent = logs.length;

                if (logs.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No download activity recorded.</td></tr>';
                    return;
                }

                logs.forEach(log => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${log.downloaded_at || log.created_at || '—'}</td>
                        <td>${log.resource_title || log.resource_id}</td>
                        <td><code>${log.ip_address || '—'}</code></td>
                        <td>${log.referrer || 'Direct'}</td>
                        <td><small>${(log.user_agent || '—').slice(0, 45)}...</small></td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (err) {
                console.error('[Admin UI] Failed to load logs:', err);
            }
        }

        async function loadOverviewStats() {
            try {
                // Fetch stats endpoint[cite: 11]
                const res = await apiFetch('/admin/stats.php', { cache: 'no-store' });
                if (res.ok) {
                    const stats = await res.json();
                    const statDownloads = document.getElementById('statDownloads');
                    const statDownloads24h = document.getElementById('statDownloads24h');

                    if (statDownloads && stats.total_downloads !== undefined) {
                        statDownloads.textContent = stats.total_downloads;
                    }
                    if (statDownloads24h && stats.downloads_24h !== undefined) {
                        statDownloads24h.textContent = stats.downloads_24h;
                    }

                    const topResourcesList = document.getElementById('topResourcesList');
                    if (topResourcesList && Array.isArray(stats.top_resources)) {
                        topResourcesList.innerHTML = '';
                        if (stats.top_resources.length === 0) {
                            topResourcesList.innerHTML = '<div class="loading-state">No downloads logged yet.</div>';
                        } else {
                            stats.top_resources.forEach(item => {
                                const div = document.createElement('div');
                                div.className = 'top-resource-item';
                                div.innerHTML = `<span>${item.title}</span><strong>${item.downloads} downloads</strong>`;
                                topResourcesList.appendChild(div);
                            });
                        }
                    }
                }

                // Check system health endpoint[cite: 11]
                const healthRes = await apiFetch('/health.php', { cache: 'no-store' });
                if (healthRes.ok) {
                    const health = await healthRes.json();
                    const resourceCountEl = document.getElementById('resourceCount');
                    if (health && health.success && resourceCountEl && !resourceCountEl.textContent) {
                        resourceCountEl.textContent = String(health.resources || '—');
                    }
                }
            } catch (err) {
                console.warn('[Admin UI] Stats update skipped:', err);
            }
        }

        // Global Resource Delete Handler
        window.deleteResource = async function (id) {
            if (!confirm(`Are you sure you want to delete resource #${id}?`)) return;
            try {
                const res = await apiFetch(`/admin/resources.php?id=${id}`, {
                    method: 'DELETE',
                    headers: getHeaders(true)
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    loadResources();
                    loadOverviewStats();
                } else {
                    alert(data.error || 'Failed to delete resource');
                }
            } catch (err) {
                alert('Error executing delete request');
            }
        };

        // ---------------------------------------------------------------------
        // 5. Danger Zone & Export Controls
        // ---------------------------------------------------------------------
        function toggleModal(show) {
            if (!dangerModal) return;
            dangerModal.style.display = show ? 'block' : 'none';
            dangerModal.setAttribute('aria-hidden', show ? 'false' : 'true');
            if (show && dangerConfirmInput) {
                dangerConfirmInput.value = '';
                dangerConfirmInput.focus();
            }
        }

        if (killAllBtn) killAllBtn.addEventListener('click', () => toggleModal(true));
        if (dangerCancelBtn) dangerCancelBtn.addEventListener('click', () => toggleModal(false));
        if (dangerModalBackdrop) dangerModalBackdrop.addEventListener('click', () => toggleModal(false));

        if (killPreviewBtn) {
            killPreviewBtn.addEventListener('click', async () => {
                try {
                    const res = await apiFetch('/admin/danger_delete.php?preview=true');
                    const data = await res.json();
                    alert(`Preview Deletion Target:\n- Categories: ${data.categories || 0}\n- Sub-categories: ${data.subcategories || 0}\n- Resources: ${data.resources || 0}\n- Storage Files: ${data.files || 0}`);
                } catch (err) {
                    alert('Failed to preview deletion targets.');
                }
            });
        }

        if (dangerConfirmBtn) {
            dangerConfirmBtn.addEventListener('click', async () => {
                const confirmVal = dangerConfirmInput ? dangerConfirmInput.value.trim() : '';
                if (confirmVal !== CONFIRM_PHRASE) {
                    showMessage('dangerModalMsg', 'Invalid confirmation phrase.', true);
                    return;
                }

                try {
                    const res = await apiFetch('/admin/danger_delete.php', {
                        method: 'DELETE',
                        headers: getHeaders(true)
                    });
                    const data = await res.json();

                    if (res.ok && data.success) {
                        toggleModal(false);
                        alert('All data and files permanently removed.');
                        loadResources();
                        loadLogs();
                        syncTaxonomyDropdowns();
                    } else {
                        showMessage('dangerModalMsg', data.error || 'Deletion failed', true);
                    }
                } catch (err) {
                    showMessage('dangerModalMsg', 'Network error executing wipe command', true);
                }
            });
        }

        if (exportCsvBtn) {
            exportCsvBtn.addEventListener('click', () => {
                const key = getAdminKey();
                window.location.href = `${API_BASE}/admin/export_csv.php?api_key=${encodeURIComponent(key)}`;
            });
        }

        // ---------------------------------------------------------------------
        // 6. Global Listeners & Execution
        // ---------------------------------------------------------------------
        if (refreshResourcesBtn) refreshResourcesBtn.addEventListener('click', loadResources);
        if (refreshLogsBtn) refreshLogsBtn.addEventListener('click', loadLogs);
        if (refreshOverviewBtn) {
            refreshOverviewBtn.addEventListener('click', () => {
                loadResources();
                loadLogs();
                loadOverviewStats();
                syncTaxonomyDropdowns(); // Refresh taxonomy dropdowns
            });
        }

        // Primary Load Operations
        loadResources();
        loadLogs();
        loadOverviewStats();
        syncTaxonomyDropdowns(); // Perform initial category/subcategory populate[cite: 1, 11]
    });

    // Global Module Export Namespace[cite: 11]
    window.MfanoAdmin = {
        API_BASE,
        apiFetch,
        getAdminKey,
        saveAdminKey,
        getHeaders
    };
})();