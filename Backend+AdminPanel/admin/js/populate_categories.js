// Backend+AdminPanel/admin/js/populate_categories.js
/*
  Standalone script to populate category and subcategory select elements.
  Can be included directly in index.html after admin.js or used independently.
*/

(() => {
  // Compute API_BASE dynamically based on current URI location
  const API_BASE = (() => {
    const p = window.location.pathname || '/';
    const adminIndex = p.indexOf('/admin/');
    if (adminIndex !== -1) {
      return p.slice(0, adminIndex) + '/api';
    }
    return '/api';
  })();

  const STORAGE_KEY = 'mfano_admin_api_key';

  // Read key dynamically at call time so updates during active session take immediate effect
  function getAdminKey() {
    try {
      return localStorage.getItem(STORAGE_KEY) || '';
    } catch (e) {
      return '';
    }
  }

  // Generic fetch wrapper attaching expected authorization headers
  function apiFetch(path, opts = {}) {
    const url = `${API_BASE}${path.startsWith('/') ? path : '/' + path}`;
    const headers = Object.assign({}, opts.headers || {});
    const key = getAdminKey();
    
    if (key) {
      headers['X-Api-Key'] = key;
      headers['X-Admin-Api-Key'] = key; // Fallback support for older API endpoints
    }
    
    return fetch(url, Object.assign({ credentials: 'same-origin', headers }, opts));
  }

  // Helper to safely clear and populate HTMLSelectElement options
  function populateSelect(selectEl, rows = [], idField = 'id', labelField = 'name', placeholder = '-- Select --') {
    if (!selectEl) return;
    
    selectEl.innerHTML = '';
    
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.textContent = placeholder;
    selectEl.appendChild(defaultOpt);

    rows.forEach(item => {
      if (item && item[idField] !== undefined) {
        const option = document.createElement('option');
        option.value = item[idField];
        option.textContent = item[labelField] || item[idField];
        selectEl.appendChild(option);
      }
    });
  }

  // Fetch categories and subcategories from API endpoints
  function loadCategoriesAndSubcategories() {
    // 1. Load Primary Categories
    apiFetch('/admin/categories.php')
      .then(res => {
        if (!res.ok) throw new Error(`HTTP status ${res.status}`);
        return res.json();
      })
      .then(json => {
        const rows = Array.isArray(json) ? json : (json.data || json.categories || []);
        populateSelect(document.querySelector('#parentCategory'), rows, 'id', 'name', 'Select category');
        populateSelect(document.querySelector('#resourceCategory'), rows, 'id', 'name', 'Select category');
      })
      .catch(err => {
        console.warn('[populate_categories] Failed to load categories:', err);
      });

    // 2. Load Sub-categories
    apiFetch('/admin/subcategories.php')
      .then(res => {
        if (!res.ok) throw new Error(`HTTP status ${res.status}`);
        return res.json();
      })
      .then(json => {
        const rows = Array.isArray(json) ? json : (json.data || json.sub_categories || json.subCategories || []);
        populateSelect(document.querySelector('#subCategory'), rows, 'id', 'name', 'Select sub-category');
      })
      .catch(err => {
        console.warn('[populate_categories] Failed to load sub-categories:', err);
      });
  }

  // Execute on DOM Ready
  document.addEventListener('DOMContentLoaded', loadCategoriesAndSubcategories);

  // Expose global hook for manual or programmatic triggers (e.g. after adding new category)
  window.populateAdminCategories = loadCategoriesAndSubcategories;
})();