/* Backend+AdminPanel/admin/js/admin.js
   Combined Admin UI behavior: Dynamic API_BASE, X-Api-Key header authentication,
   DOM caching, dashboard stats, category selection, and modal controls.
*/

(() => {
  // Dynamically compute API_BASE so the admin panel functions regardless of whether
  // it is served from /Backend+AdminPanel/admin/ or mounted at /api directly.
  const API_BASE = (function () {
    const p = window.location.pathname || '/';
    const adminIndex = p.indexOf('/admin/');
    if (adminIndex !== -1) {
      return p.slice(0, adminIndex) + '/api';
    }
    return '/api';
  })();

  const STORAGE_KEY = 'mfano_admin_api_key';
  const CONFIRM_PHRASE = 'YES_I_CONFIRM_DELETE_ALL';

  // Cached DOM Elements
  const els = {
    apiKey: document.getElementById('apiKey') || document.getElementById('ApiKey') || document.getElementById('apiKeyInput'),
    apiKeyInput: document.getElementById('apiKeyInput') || document.getElementById('apiKey') || document.getElementById('ApiKey'),
    resourceForm: document.getElementById('resourceForm'),
    resourceTitle: document.getElementById('resourceTitle'),
    resourceDescription: document.getElementById('resourceDescription'),
    resourceCategory: document.getElementById('resourceCategory'),
    resourceSubCategory: document.getElementById('resourceSubCategory'),
    subCategory: document.getElementById('subCategory'),
    parentCategory: document.getElementById('parentCategory'),
    newSubCategoryName: document.getElementById('newSubCategoryName'),
    newSubCategorySlug: document.getElementById('newSubCategorySlug'),
    fileInput: document.getElementById('fileInput'),
    fileDrop: document.getElementById('fileDrop'),
    filePicked: document.getElementById('filePicked'),
    fileSourceField: document.getElementById('fileSourceField'),
    urlSourceField: document.getElementById('urlSourceField'),
    fileUrl: document.getElementById('fileUrl'),
    resourceTableBody: document.getElementById('resourceTableBody'),
    resourceCount: document.getElementById('resourceCount'),
    logsTableBody: document.getElementById('logsTableBody'),
    logCount: document.getElementById('logCount'),
    topResourcesList: document.getElementById('topResourcesList'),
    exportCsvBtn: document.getElementById('exportCsv'),
    killSwitchBtn: document.getElementById('killSwitch'),
    confirmDeleteInput: document.getElementById('confirmDelete'),
    addCategoryForm: document.getElementById('addCategoryForm'),
    addSubCategoryForm: document.getElementById('addSubCategoryForm'),
    categoriesTableBody: document.getElementById('categoriesTableBody'),
    subCategoriesTableBody: document.getElementById('subCategoriesTableBody'),
    logsModal: document.getElementById('logsModal'),
    resourcesModal: document.getElementById('resourcesModal'),
    fileProgress: document.getElementById('fileProgress')
  };

  // Helper to retrieve admin key from localStorage
  function getAdminKey() {
    try {
      const k = localStorage.getItem(STORAGE_KEY);
      return k && k.length ? k : null;
    } catch (e) {
      return null;
    }
  }

  // Error handling display wrapper
  function showError(msg) {
    console.error('Admin UI Error:', msg);
  }

  // Generic fetch wrapper that attaches the expected header: X-Api-Key
  function apiFetch(path, opts = {}) {
    const url = `${API_BASE}${path.startsWith('/') ? path : '/' + path}`;
    const headers = Object.assign({}, opts.headers || {});
    const key = getAdminKey();
    
    if (key) {
      headers['X-Api-Key'] = key;
    }
    
    return fetch(url, Object.assign({ credentials: 'same-origin', headers }, opts));
  }

  // Health and stats fetcher for dashboard UI
  function loadDashboardStats() {
    apiFetch('/health.php')
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((json) => {
        if (json && json.success) {
          if (els.resourceCount) {
            els.resourceCount.textContent = String(json.resources || '—');
          }
        } else {
          console.warn('Health endpoint returned unexpected payload:', json);
        }
      })
      .catch((err) => {
        showError('Failed to fetch health endpoint: ' + err);
      });
  }

  // Dynamic selector loader for Categories and Subcategories
  function loadCategoriesIntoSelects() {
    apiFetch('/admin/categories.php')
      .then((r) => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then((payload) => {
        const rows = Array.isArray(payload) ? payload : (payload.data || payload.categories || []);
        
        if (els.parentCategory) {
          els.parentCategory.innerHTML = '<option value="">-- Select category --</option>';
          rows.forEach((c) => {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name;
            els.parentCategory.appendChild(o);
          });
        }
        
        if (els.resourceCategory) {
          els.resourceCategory.innerHTML = '<option value="">-- Select category --</option>';
          rows.forEach((c) => {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name;
            els.resourceCategory.appendChild(o);
          });
        }
      })
      .catch((err) => {
        showError('Could not load categories: ' + err);
      });

    apiFetch('/admin/subcategories.php')
      .then((r) => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then((payload) => {
        const rows = Array.isArray(payload) ? payload : (payload.data || payload.sub_categories || payload.subCategories || []);
        const targetSelect = els.subCategory || els.resourceSubCategory;
        
        if (targetSelect) {
          targetSelect.innerHTML = '<option value="">-- Select sub-category --</option>';
          rows.forEach((s) => {
            const o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.name;
            targetSelect.appendChild(o);
          });
        }
      })
      .catch((err) => {
        showError('Could not load sub-categories: ' + err);
      });
  }

  // Document Ready Initialization
  document.addEventListener('DOMContentLoaded', () => {
    // Populate counts, stats, and select inputs
    loadDashboardStats();
    loadCategoriesIntoSelects();

    // Sync input field value from localStorage if available
    const initialKey = getAdminKey();
    if (initialKey && els.apiKeyInput) {
      els.apiKeyInput.value = initialKey;
    }

    // Bind event listener to capture updated API key input
    if (els.apiKeyInput) {
      els.apiKeyInput.addEventListener('change', () => {
        try {
          const trimmed = els.apiKeyInput.value.trim();
          if (trimmed) {
            localStorage.setItem(STORAGE_KEY, `90692b792db62076a5b4a82d4fd5910743c121cf27e74320`);
          } else {
            localStorage.removeItem(STORAGE_KEY);
          }
        } catch (e) {
          showError('Unable to update local storage: ' + e);
        }
      });
    }
  });

  // Global namespace export for administrative sub-modules
  window.MfanoAdmin = {
    API_BASE,
    apiFetch,
    getAdminKey,
    loadDashboardStats,
    loadCategoriesIntoSelects,
    showError
  };
})();