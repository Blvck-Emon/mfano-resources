/**
 * populate_categories.js
 * 
 * Data pipeline & Admin UI module:
 * - Dynamically computes API base paths and retrieves authentication keys from storage[cite: 11].
 * - Robustly fetches categories and sub-categories from API endpoints[cite: 1, 11].
 * - Populates #parentCategory, #resourceCategory, and #subCategory (supporting <optgroup> grouping)[cite: 1, 11].
 * - Updates UI statistics counter (#statCategories) and handles error states gracefully[cite: 1, 11].
 * - Exports global hooks (window.TaxonomyManager & window.populateAdminCategories)[cite: 1, 11].
 */

(function (window) {
  'use strict';

  const STORAGE_KEY = 'mfano_admin_api_key';

  // 1. Dynamically compute API_BASE relative to current page location[cite: 11]
  const API_BASE = (() => {
    const p = window.location.pathname || '/';
    const adminIndex = p.indexOf('/admin/');
    if (adminIndex !== -1) {
      return p.slice(0, adminIndex) + '/api';
    }
    return '../api';
  })();

  // 2. Read admin key from storage dynamically at request time[cite: 11]
  function getAdminKey() {
    try {
      return localStorage.getItem(STORAGE_KEY) || sessionStorage.getItem(STORAGE_KEY) || '';
    } catch (e) {
      return '';
    }
  }

  // 3. Centralized fetch wrapper attaching authorization headers & cache controls[cite: 1, 11]
  async function apiFetch(path, opts = {}) {
    const url = path.startsWith('http') ? path : `${API_BASE}${path.startsWith('/') ? path : '/' + path}`;
    const headers = Object.assign({
      'Accept': 'application/json'
    }, opts.headers || {});

    const key = getAdminKey();
    if (key) {
      headers['X-Api-Key'] = key;
      headers['X-Admin-Api-Key'] = key; // Compatibility fallback for older endpoints[cite: 11]
    }

    return fetch(url, Object.assign({
      credentials: 'same-origin',
      cache: 'no-store',
      headers
    }, opts));
  }

  // 4. Extract heterogeneous API response arrays[cite: 1, 11]
  function extractRows(payload, keys = ['data', 'categories']) {
    if (Array.isArray(payload)) return payload;
    if (payload && typeof payload === 'object') {
      for (const key of keys) {
        if (Array.isArray(payload[key])) return payload[key];
      }
    }
    return [];
  }

  // 5. Helper to populate flat <select> dropdowns[cite: 1, 11]
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

  /**
   * Primary Taxonomy Loading Function[cite: 1]
   */
  async function loadTaxonomyDropdowns() {
    const parentCategorySelect = document.getElementById('parentCategory');
    const resourceCategorySelect = document.getElementById('resourceCategory');
    const subCategorySelect = document.getElementById('subCategory');

    if (!parentCategorySelect && !subCategorySelect && !resourceCategorySelect) {
      console.warn('[Taxonomy] Target select elements not present in DOM.');
      return;
    }

    let categoriesLoaded = false;

    // Strategy A: Unified Public Endpoint with Nested Subcategories (/categories.php)[cite: 1]
    try {
      const res = await apiFetch('/categories.php');
      if (res.ok) {
        const raw = await res.json();
        const categories = extractRows(raw, ['data', 'categories']);

        if (categories.length > 0) {
          // Populate top-level category dropdowns[cite: 1, 11]
          populateSelect(parentCategorySelect, categories, 'id', 'name', 'Select Category');
          populateSelect(resourceCategorySelect, categories, 'id', 'name', 'Select Category');

          let totalSubcategoriesCount = 0;
          let hasNestedSubs = false;

          // Populate subcategory select using <optgroup> for nested structures[cite: 1]
          if (subCategorySelect) {
            subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';

            categories.forEach(category => {
              const subCats = category.sub_categories || category.subcategories || [];
              if (subCats.length > 0) {
                hasNestedSubs = true;
                const optGroup = document.createElement('optgroup');
                optGroup.label = category.name;

                subCats.forEach(sub => {
                  totalSubcategoriesCount++;
                  const subOption = document.createElement('option');
                  subOption.value = sub.id;
                  subOption.textContent = sub.name;
                  optGroup.appendChild(subOption);
                });

                subCategorySelect.appendChild(optGroup);
              }
            });
          }

          // Update UI stats counter[cite: 1]
          const categoryStatElement = document.getElementById('statCategories');
          if (categoryStatElement) {
            categoryStatElement.textContent = categories.length;
          }

          categoriesLoaded = true;
          console.log(`[Taxonomy] Populated ${categories.length} categories.`);
          
          if (hasNestedSubs) {
            console.log(`[Taxonomy] Populated ${totalSubcategoriesCount} nested sub-categories via optgroups.`);
            return; // Successfully finished via nested payload[cite: 1]
          }
        }
      }
    } catch (e) {
      console.warn('[Taxonomy] Public endpoint fetch error, trying dedicated admin endpoints:', e);
    }

    // Strategy B: Dedicated Admin Endpoints (/admin/categories.php & /admin/subcategories.php)[cite: 11]
    if (!categoriesLoaded) {
      try {
        const catRes = await apiFetch('/admin/categories.php');
        if (!catRes.ok) throw new Error(`HTTP ${catRes.status}`);

        const json = await catRes.json();
        const rows = extractRows(json, ['data', 'categories']);
        populateSelect(parentCategorySelect, rows, 'id', 'name', 'Select Category');
        populateSelect(resourceCategorySelect, rows, 'id', 'name', 'Select Category');

        const categoryStatElement = document.getElementById('statCategories');
        if (categoryStatElement) categoryStatElement.textContent = rows.length;
      } catch (err) {
        console.error('[Taxonomy] Failed to load parent categories:', err);
        if (parentCategorySelect) parentCategorySelect.innerHTML = '<option value="">Failed to load categories</option>';
        if (resourceCategorySelect) resourceCategorySelect.innerHTML = '<option value="">Failed to load categories</option>';
      }
    }

    // Fetch standalone subcategories if not populated via optgroups[cite: 11]
    if (subCategorySelect && subCategorySelect.children.length <= 1) {
      try {
        const subRes = await apiFetch('/admin/subcategories.php');
        if (!subRes.ok) throw new Error(`HTTP ${subRes.status}`);

        const json = await subRes.json();
        const subRows = extractRows(json, ['data', 'sub_categories', 'subCategories']);
        populateSelect(subCategorySelect, subRows, 'id', 'name', 'Select Sub-Category');
      } catch (err) {
        console.error('[Taxonomy] Failed to load sub-categories:', err);
        if (subCategorySelect) subCategorySelect.innerHTML = '<option value="">Failed to load sub-categories</option>';
      }
    }
  }

  // Export module hooks for global access[cite: 1, 11]
  window.TaxonomyManager = {
    load: loadTaxonomyDropdowns
  };
  window.populateAdminCategories = loadTaxonomyDropdowns;

  // Auto-initialize when DOM is ready[cite: 1]
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadTaxonomyDropdowns);
  } else {
    loadTaxonomyDropdowns();
  }
})(window);