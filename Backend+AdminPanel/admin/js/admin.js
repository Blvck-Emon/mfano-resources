/* admin/js/admin.js
   Combined Admin UI behavior: API key handling, export CSV, 
   full resource/category management, dynamic API base resolution,
   and modal-based kill-switch flow.
*/

(() => {
  // Dynamically compute API_BASE so requests resolve correctly whether 
  // served from /Backend+AdminPanel/admin/ or directly under document root /
  const API_BASE = (function(){
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
    apiKey: document.getElementById('apiKey') || document.getElementById('ApiKey'),
    apiKeyInput: document.getElementById('apiKey') || document.getElementById('ApiKey'),
    resourceForm: document.getElementById('resourceForm'),
    categoryForm: document.getElementById('categoryForm'),
    subCategoryForm: document.getElementById('subCategoryForm'),
    submitBtn: document.getElementById('submitBtn'),
    categorySubmitBtn: document.getElementById('categorySubmitBtn'),
    subCategorySubmitBtn: document.getElementById('subCategorySubmitBtn'),
    title: document.getElementById('title'),
    description: document.getElementById('description'),
    descriptionCount: document.getElementById('descriptionCount'),
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
    message: document.getElementById('message'),
    categoryMessage: document.getElementById('categoryMessage'),
    subCategoryMessage: document.getElementById('subCategoryMessage'),
    statResources: document.getElementById('statResources'),
    statDownloads: document.getElementById('statDownloads'),
    statDownloads24h: document.getElementById('statDownloads24h'),
    statCategories: document.getElementById('statCategories'),
    killAllBtn: document.getElementById('killAllBtn'),
    killPreviewBtn: document.getElementById('killPreviewBtn'),
    exportCsvBtn: document.getElementById('exportCsvBtn'),
    // Modal Elements
    dangerModal: document.getElementById('dangerModal'),
    dangerBackdrop: document.getElementById('dangerModalBackdrop'),
    dangerInput: document.getElementById('dangerConfirmInput'),
    dangerCancelBtn: document.getElementById('dangerCancelBtn'),
    dangerConfirmBtn: document.getElementById('dangerConfirmBtn'),
    dangerModalMsg: document.getElementById('dangerModalMsg'),
  };

  const state = {
    categories: [],
    resources: [],
  };

  // ---------------------------------------------------------------------------
  // Utilities & API Key Management
  // ---------------------------------------------------------------------------
  function getStoredApiKey() {
    const fromSession = sessionStorage.getItem(STORAGE_KEY);
    if (fromSession && fromSession.trim() !== '') return fromSession.trim();
    if (els.apiKey && els.apiKey.value.trim() !== '') return els.apiKey.value.trim();
    return '';
  }

  function storeApiKey(val) {
    if (!val) {
      sessionStorage.removeItem(STORAGE_KEY);
      return;
    }
    sessionStorage.setItem(STORAGE_KEY, val.trim());
  }

  if (els.apiKey) {
    els.apiKey.value = getStoredApiKey();
    els.apiKey.addEventListener('input', () => storeApiKey(els.apiKey.value));
    els.apiKey.addEventListener('paste', () => {
      setTimeout(() => storeApiKey(els.apiKey.value), 5);
    });
  }

  function adminHeaders(extra = {}) {
    return {
      ...extra,
      'X-Api-Key': getStoredApiKey(),
    };
  }

  function setMessage(target, text = '', type = '') {
    if (!target) {
      console.log('[admin] message:', text);
      return;
    }
    target.textContent = text;
    target.className = 'message' + (text ? ` visible ${type}` : '');
  }

  function setControlsDisabled(state) {
    [els.killAllBtn, els.killPreviewBtn, els.exportCsvBtn, els.dangerConfirmBtn].forEach((b) => {
      if (b) b.disabled = !!state;
    });
  }

  async function readJson(response) {
    const text = await response.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch {
      throw new Error(`Unexpected server response (${response.status}).`);
    }

    if (!response.ok || json.success === false) {
      throw new Error(json.error || json.message || `Request failed (${response.status}).`);
    }

    return json;
  }

  async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    return readJson(response);
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value ?? '';
    return div.innerHTML;
  }

  function number(value) {
    return new Intl.NumberFormat().format(Number(value) || 0);
  }

  function formatBytesFromKb(kb) {
    const value = Number(kb) || 0;
    if (!value) return '—';
    if (value < 1024) return `${value} KB`;
    return `${(value / 1024).toFixed(1)} MB`;
  }

  function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(date);
  }

  function slugify(value) {
    return String(value || '')
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .replace(/-{2,}/g, '-');
  }

  // ---------------------------------------------------------------------------
  // Categories & Taxonomy
  // ---------------------------------------------------------------------------
  async function fetchCategories() {
    const json = await fetchJson(`${API_BASE}/categories.php`);
    state.categories = Array.isArray(json.data) ? json.data : [];
    renderCategoryOptions();
  }

  function renderCategoryOptions() {
    if (!els.subCategory || !els.parentCategory) return;
    els.subCategory.innerHTML = '<option value="">Select Sub-Category</option>';
    els.parentCategory.innerHTML = '<option value="">Select Category</option>';

    state.categories.forEach((category) => {
      const parentOption = document.createElement('option');
      parentOption.value = category.id;
      parentOption.textContent = category.name;
      els.parentCategory.appendChild(parentOption);

      const group = document.createElement('optgroup');
      group.label = category.name;

      (category.subcategories || []).forEach((sub) => {
        const option = document.createElement('option');
        option.value = sub.id;
        option.textContent = sub.name;
        group.appendChild(option);
      });

      els.subCategory.appendChild(group);
    });

    if (els.statCategories) els.statCategories.textContent = number(state.categories.length);
  }

  // ---------------------------------------------------------------------------
  // Resources Management
  // ---------------------------------------------------------------------------
  async function fetchResources() {
    const json = await fetchJson(`${API_BASE}/resources.php`);
    state.resources = Array.isArray(json.data) ? json.data : [];
    renderResourceTable();
    if (els.statResources) els.statResources.textContent = number(state.resources.length);
  }

  function renderResourceTable() {
    if (!els.resourceTableBody || !els.resourceCount) return;
    els.resourceCount.textContent = number(state.resources.length);
    els.resourceTableBody.innerHTML = '';

    if (!state.resources.length) {
      els.resourceTableBody.innerHTML = `
        <tr class="empty-row">
          <td colspan="8">No resources found.</td>
        </tr>`;
      return;
    }

    state.resources.forEach((resource) => {
      const tr = document.createElement('tr');
      const storageClass = resource.storage_type === 'local' ? 'local' : 'external';
      const storageLabel = resource.storage_type === 'local' ? 'Local PDF' : 'External URL';
      const statusClass = Number(resource.is_published) ? 'published' : 'draft';
      const statusLabel = Number(resource.is_published) ? 'Published' : 'Draft';

      tr.innerHTML = `
        <td>#${escapeHtml(resource.id)}</td>
        <td class="title-cell">
          <div>${escapeHtml(resource.title)}</div>
          <div class="field-note">${escapeHtml(formatBytesFromKb(resource.file_size_kb))}</div>
        </td>
        <td class="category-cell">${escapeHtml(resource.category_name || '—')}<br><span class="field-note">${escapeHtml(resource.sub_category_name || '—')}</span></td>
        <td><span class="pill ${storageClass}">${storageLabel}</span></td>
        <td>${number(resource.download_count)}</td>
        <td><span class="pill ${statusClass}">${statusLabel}</span></td>
        <td>${Number(resource.is_featured) ? '<span class="pill featured">Featured</span>' : '<span class="field-note">No</span>'}</td>
        <td>
          <div class="action-row">
            <button class="table-action" type="button" data-action="view" data-id="${escapeHtml(resource.id)}">View</button>
            <button class="table-action" type="button" data-action="toggle" data-id="${escapeHtml(resource.id)}">${Number(resource.is_published) ? 'Unpublish' : 'Publish'}</button>
            <button class="table-action danger" type="button" data-action="delete" data-id="${escapeHtml(resource.id)}">Delete</button>
          </div>
        </td>
      `;
      els.resourceTableBody.appendChild(tr);
    });
  }

  async function addResource(event) {
    event.preventDefault();
    setMessage(els.message);

    const apiKey = getStoredApiKey();
    if (!apiKey) {
      setMessage(els.message, 'Enter the Admin API Key before saving.', 'error');
      els.apiKey?.focus();
      return;
    }

    const title = els.title.value.trim();
    const description = els.description.value.trim();
    const subCategoryId = els.subCategory.value;
    const isFeatured = document.getElementById('isFeatured')?.checked || false;
    const source = document.querySelector('input[name="source"]:checked')?.value || 'file';

    const formData = new FormData();
    formData.append('title', title);
    formData.append('description', description);
    formData.append('sub_category_id', subCategoryId);
    formData.append('is_featured', String(isFeatured));

    if (source === 'file') {
      const file = els.fileInput?.files[0];
      if (!file) {
        setMessage(els.message, 'Choose a PDF file to upload.', 'error');
        return;
      }
      formData.append('file', file);
    } else {
      const fileUrl = els.fileUrl.value.trim();
      if (!fileUrl) {
        setMessage(els.message, 'Enter the hosted PDF URL.', 'error');
        els.fileUrl?.focus();
        return;
      }
      formData.append('file_url', fileUrl);
    }

    setButtonBusy(els.submitBtn, 'Saving…');

    try {
      await fetchJson(`${API_BASE}/admin/resources.php`, {
        method: 'POST',
        headers: adminHeaders(),
        body: formData,
      });

      setMessage(els.message, 'Resource added successfully.', 'success');
      els.resourceForm.reset();
      resetFileUi();
      updateDescriptionCount();
      toggleSourcePanels();
      await Promise.all([fetchResources(), fetchLogs()]);
    } catch (error) {
      setMessage(els.message, error.message, 'error');
    } finally {
      setButtonBusy(els.submitBtn, 'Add Resource', false);
    }
  }

  async function deleteResource(id) {
    const resource = state.resources.find((item) => String(item.id) === String(id));
    if (!resource) return;

    if (!window.confirm(`Delete “${resource.title}”? This cannot be undone.`)) return;

    try {
      await fetchJson(`${API_BASE}/admin/resources.php?id=${encodeURIComponent(id)}`, {
        method: 'DELETE',
        headers: adminHeaders(),
      });
      await Promise.all([fetchResources(), fetchLogs()]);
    } catch (error) {
      window.alert(error.message);
    }
  }

  async function togglePublished(id) {
    const resource = state.resources.find((item) => String(item.id) === String(id));
    if (!resource) return;

    try {
      await fetchJson(`${API_BASE}/admin/resources.php?id=${encodeURIComponent(id)}`, {
        method: 'PUT',
        headers: adminHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
          sub_category_id: resource.sub_category_id,
          title: resource.title,
          description: resource.description,
          file_url: resource.file_url,
          is_featured: Boolean(Number(resource.is_featured)),
          is_published: !Boolean(Number(resource.is_published)),
        }),
      });
      await fetchResources();
    } catch (error) {
      window.alert(error.message);
    }
  }

  function handleResourceTableClick(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const id = button.dataset.id;

    if (button.dataset.action === 'delete') deleteResource(id);
    if (button.dataset.action === 'toggle') togglePublished(id);
    if (button.dataset.action === 'view') {
      const resource = state.resources.find((item) => String(item.id) === String(id));
      if (resource?.file_url) window.open(resource.file_url, '_blank', 'noopener,noreferrer');
    }
  }

  // ---------------------------------------------------------------------------
  // Categories & Sub-Categories Creation
  // ---------------------------------------------------------------------------
  async function addCategory(event) {
    event.preventDefault();
    setMessage(els.categoryMessage);
    setButtonBusy(els.categorySubmitBtn, 'Saving…');

    try {
      await fetchJson(`${API_BASE}/admin/categories.php`, {
        method: 'POST',
        headers: adminHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
          name: document.getElementById('categoryName').value.trim(),
          slug: document.getElementById('categorySlug').value.trim(),
        }),
      });

      setMessage(els.categoryMessage, 'Category added successfully.', 'success');
      els.categoryForm.reset();
      await fetchCategories();
    } catch (error) {
      setMessage(els.categoryMessage, error.message, 'error');
    } finally {
      setButtonBusy(els.categorySubmitBtn, 'Add Category', false);
    }
  }

  async function addSubCategory(event) {
    event.preventDefault();
    setMessage(els.subCategoryMessage);
    setButtonBusy(els.subCategorySubmitBtn, 'Saving…');

    try {
      await fetchJson(`${API_BASE}/admin/subcategories.php`, {
        method: 'POST',
        headers: adminHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({
          category_id: els.parentCategory.value,
          name: els.newSubCategoryName.value.trim(),
          slug: els.newSubCategorySlug.value.trim(),
        }),
      });

      setMessage(els.subCategoryMessage, 'Sub-category added successfully.', 'success');
      els.subCategoryForm.reset();
      await fetchCategories();
    } catch (error) {
      setMessage(els.subCategoryMessage, error.message, 'error');
    } finally {
      setButtonBusy(els.subCategorySubmitBtn, 'Add Sub-Category', false);
    }
  }

  // ---------------------------------------------------------------------------
  // Download Logs & Stats
  // ---------------------------------------------------------------------------
  async function fetchLogs() {
    const json = await fetchJson(`${API_BASE}/admin/logs.php?limit=100`, {
      headers: adminHeaders(),
    });

    const logs = Array.isArray(json.logs) ? json.logs : [];
    if (els.statDownloads) els.statDownloads.textContent = number(json.total);
    if (els.statDownloads24h) els.statDownloads24h.textContent = number(json.last_24h);
    if (els.logCount) els.logCount.textContent = number(logs.length);
    renderLogs(logs);
    renderTopResources(Array.isArray(json.top_resources) ? json.top_resources : []);
  }

  function renderLogs(logs) {
    if (!els.logsTableBody) return;
    els.logsTableBody.innerHTML = '';

    if (!logs.length) {
      els.logsTableBody.innerHTML = '<tr class="empty-row"><td colspan="5">No download activity has been recorded yet.</td></tr>';
      return;
    }

    logs.forEach((log) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${escapeHtml(formatDateTime(log.downloaded_at))}</td>
        <td class="title-cell">${escapeHtml(log.resource_title)}</td>
        <td>${escapeHtml(log.ip_address || '—')}</td>
        <td>${escapeHtml(log.referrer || 'Direct')}</td>
        <td class="category-cell">${escapeHtml(log.user_agent || '—')}</td>
      `;
      els.logsTableBody.appendChild(tr);
    });
  }

  function renderTopResources(resources) {
    if (!els.topResourcesList) return;
    if (!resources.length) {
      els.topResourcesList.innerHTML = '<div class="loading-state">No downloads recorded yet.</div>';
      return;
    }

    els.topResourcesList.innerHTML = resources.map((item, index) => `
      <div class="top-resource">
        <div>
          <div class="top-resource-title">${index + 1}. ${escapeHtml(item.title)}</div>
          <div class="top-resource-meta">Resource #${escapeHtml(item.id)}</div>
        </div>
        <div class="top-resource-count">${number(item.downloads)} downloads</div>
      </div>
    `).join('');
  }

  async function refreshOverview() {
    try {
      await Promise.all([fetchCategories(), fetchResources(), fetchLogs()]);
    } catch (error) {
      setMessage(els.message, error.message, 'error');
    }
  }

  // ---------------------------------------------------------------------------
  // Source Toggle & File UI Helpers
  // ---------------------------------------------------------------------------
  function toggleSourcePanels() {
    const source = document.querySelector('input[name="source"]:checked')?.value || 'file';
    if (els.fileSourceField) els.fileSourceField.hidden = source !== 'file';
    if (els.urlSourceField) els.urlSourceField.hidden = source !== 'url';

    if (source === 'file') {
      if (els.fileUrl) els.fileUrl.value = '';
    } else {
      if (els.fileInput) els.fileInput.value = '';
      resetFileUi();
    }
  }

  function resetFileUi() {
    if (els.filePicked) els.filePicked.textContent = 'No file selected';
  }

  function handleFile(file) {
    if (!file) return;
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    if (!isPdf) {
      setMessage(els.message, 'Only PDF files can be uploaded.', 'error');
      if (els.fileInput) els.fileInput.value = '';
      resetFileUi();
      return;
    }

    const maxBytes = 25 * 1024 * 1024;
    if (file.size > maxBytes) {
      setMessage(els.message, 'The selected PDF is larger than the backend 25MB limit.', 'error');
      if (els.fileInput) els.fileInput.value = '';
      resetFileUi();
      return;
    }

    setMessage(els.message);
    if (els.filePicked) els.filePicked.textContent = `${file.name} · ${formatBytesFromKb(file.size / 1024)}`;
  }

  function setButtonBusy(button, text, busy = true) {
    if (!button) return;
    if (busy) {
      button.dataset.originalText = button.textContent;
      button.disabled = true;
      button.textContent = text;
    } else {
      button.disabled = false;
      button.textContent = text || button.dataset.originalText || button.textContent;
    }
  }

  function updateDescriptionCount() {
    if (els.descriptionCount && els.description) {
      els.descriptionCount.textContent = `${els.description.value.length} / 1000`;
    }
  }

  // ---------------------------------------------------------------------------
  // Export CSV Handler
  // ---------------------------------------------------------------------------
  async function exportCsv() {
    const apiKey = getStoredApiKey();
    if (!apiKey) {
      setMessage(els.message, 'Admin API key required in this tab (paste it into the Admin API Key field).', 'error');
      els.apiKey?.focus();
      return;
    }

    setControlsDisabled(true);
    setMessage(els.message, 'Preparing CSV export...', 'info');

    try {
      const res = await fetch(`${API_BASE}/admin/export_csv.php`, {
        method: 'GET',
        headers: adminHeaders(),
      });

      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        setMessage(els.message, body.error || 'Export failed', 'error');
        setControlsDisabled(false);
        return;
      }

      const blob = await res.blob();
      const disposition = res.headers.get('content-disposition') || '';
      let filename = 'resources_export.csv';
      const m = /filename="([^"]+)"/i.exec(disposition);
      if (m) filename = m[1];

      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);

      setMessage(els.message, 'Exported CSV downloaded. Server saved a copy in database/exports/.', 'success');
    } catch (err) {
      console.error('[admin] exportCsv error', err);
      setMessage(els.message, 'Unexpected error during export: ' + (err.message || err), 'error');
    } finally {
      setControlsDisabled(false);
    }
  }

  // ---------------------------------------------------------------------------
  // Danger Zone / Modal Kill Switch Flow
  // ---------------------------------------------------------------------------
  function openDangerModal() {
    if (!els.dangerModal) return;
    els.dangerModal.setAttribute('aria-hidden', 'false');
    els.dangerModal.style.display = 'flex';
    if (els.dangerInput) els.dangerInput.value = '';
    if (els.dangerModalMsg) els.dangerModalMsg.textContent = '';
    setTimeout(() => els.dangerInput?.focus(), 60);
  }

  function closeDangerModal() {
    if (!els.dangerModal) return;
    els.dangerModal.setAttribute('aria-hidden', 'true');
    els.dangerModal.style.display = 'none';
  }

  async function confirmKill() {
    const apiKey = getStoredApiKey();
    if (!apiKey) {
      setMessage(els.message, 'Admin API key required in this tab (paste it into the Admin API Key field).', 'error');
      els.apiKey?.focus();
      closeDangerModal();
      return;
    }

    const typed = (els.dangerInput?.value || '').trim();
    if (typed !== CONFIRM_PHRASE) {
      if (els.dangerModalMsg) {
        els.dangerModalMsg.textContent = 'Confirmation mismatch — type the exact phrase to proceed.';
        els.dangerModalMsg.className = 'message message-error';
      }
      return;
    }

    setControlsDisabled(true);
    if (els.dangerConfirmBtn) els.dangerConfirmBtn.textContent = 'Deleting...';
    if (els.dangerModalMsg) {
      els.dangerModalMsg.textContent = 'Deleting...';
      els.dangerModalMsg.className = 'message message-info';
    }

    try {
      const res = await fetch(`${API_BASE}/admin/kill_switch.php`, {
        method: 'POST',
        headers: adminHeaders({ 'Content-Type': 'application/json' }),
        body: JSON.stringify({ confirm: CONFIRM_PHRASE }),
      });

      const payload = await res.json().catch(() => null);
      if (!res.ok) {
        setMessage(els.message, payload?.error || 'Kill switch failed on the server.', 'error');
        if (els.dangerModalMsg) {
          els.dangerModalMsg.textContent = payload?.error || 'Kill switch failed on the server.';
          els.dangerModalMsg.className = 'message message-error';
        }
        return;
      }

      setMessage(els.message, payload?.message || 'All sample data removed.', 'success');
      if (els.dangerModalMsg) {
        els.dangerModalMsg.textContent = payload?.message || 'All sample data removed.';
        els.dangerModalMsg.className = 'message message-success';
      }

      closeDangerModal();
      setTimeout(() => location.reload(), 1200);
    } catch (err) {
      console.error('[admin] confirmKill error', err);
      setMessage(els.message, 'Unexpected error: ' + (err.message || err), 'error');
      if (els.dangerModalMsg) {
        els.dangerModalMsg.textContent = 'Unexpected error: ' + (err.message || err);
        els.dangerModalMsg.className = 'message message-error';
      }
    } finally {
      setControlsDisabled(false);
      if (els.dangerConfirmBtn) els.dangerConfirmBtn.textContent = 'Confirm delete';
    }
  }

  async function previewKill() {
    setMessage(els.message, 'Preview not implemented server-side. Use caution before running delete.', 'info');
  }

  // ---------------------------------------------------------------------------
  // Event Wiring
  // ---------------------------------------------------------------------------
  document.addEventListener('DOMContentLoaded', () => {
    if (els.resourceForm) els.resourceForm.addEventListener('submit', addResource);
    if (els.categoryForm) els.categoryForm.addEventListener('submit', addCategory);
    if (els.subCategoryForm) els.subCategoryForm.addEventListener('submit', addSubCategory);
    if (els.resourceTableBody) els.resourceTableBody.addEventListener('click', handleResourceTableClick);

    document.querySelectorAll('input[name="source"]').forEach((input) => {
      input.addEventListener('change', toggleSourcePanels);
    });

    if (els.fileDrop) {
      els.fileDrop.addEventListener('click', () => els.fileInput?.click());
      els.fileDrop.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          els.fileInput?.click();
        }
      });
      ['dragenter', 'dragover'].forEach((eventName) => {
        els.fileDrop.addEventListener(eventName, (event) => {
          event.preventDefault();
          els.fileDrop.classList.add('dragover');
        });
      });
      ['dragleave', 'drop'].forEach((eventName) => {
        els.fileDrop.addEventListener(eventName, (event) => {
          event.preventDefault();
          els.fileDrop.classList.remove('dragover');
        });
      });
      els.fileDrop.addEventListener('drop', (event) => {
        const file = event.dataTransfer.files[0];
        if (!file || !els.fileInput) return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        els.fileInput.files = transfer.files;
        handleFile(file);
      });
    }

    if (els.fileInput) {
      els.fileInput.addEventListener('change', () => handleFile(els.fileInput.files[0]));
    }

    if (els.description) {
      els.description.addEventListener('input', updateDescriptionCount);
    }

    const categoryName = document.getElementById('categoryName');
    const categorySlug = document.getElementById('categorySlug');
    if (categoryName && categorySlug) {
      categoryName.addEventListener('input', (event) => {
        if (!categorySlug.dataset.userEdited) categorySlug.value = slugify(event.target.value);
      });
      categorySlug.addEventListener('input', (event) => {
        event.target.dataset.userEdited = 'true';
      });
    }

    if (els.newSubCategoryName && els.newSubCategorySlug) {
      els.newSubCategoryName.addEventListener('input', () => {
        if (!els.newSubCategorySlug.dataset.userEdited) {
          els.newSubCategorySlug.value = slugify(els.newSubCategoryName.value);
        }
      });
      els.newSubCategorySlug.addEventListener('input', (event) => {
        event.target.dataset.userEdited = 'true';
      });
    }

    document.getElementById('refreshOverviewBtn')?.addEventListener('click', refreshOverview);
    document.getElementById('refreshResourcesBtn')?.addEventListener('click', async () => {
      try { await fetchResources(); } catch (error) { setMessage(els.message, error.message, 'error'); }
    });
    document.getElementById('refreshLogsBtn')?.addEventListener('click', async () => {
      try { await fetchLogs(); } catch (error) { setMessage(els.message, error.message, 'error'); }
    });

    // Wire Kill Switch & Export
    if (els.killAllBtn) els.killAllBtn.addEventListener('click', openDangerModal);
    if (els.killPreviewBtn) els.killPreviewBtn.addEventListener('click', previewKill);
    if (els.exportCsvBtn) els.exportCsvBtn.addEventListener('click', exportCsv);
    if (els.dangerCancelBtn) els.dangerCancelBtn.addEventListener('click', closeDangerModal);
    if (els.dangerBackdrop) els.dangerBackdrop.addEventListener('click', closeDangerModal);
    if (els.dangerConfirmBtn) els.dangerConfirmBtn.addEventListener('click', confirmKill);

    // Escape key closes modal
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && els.dangerModal && els.dangerModal.getAttribute('aria-hidden') === 'false') {
        closeDangerModal();
      }
    });

    if (els.resourceForm) {
      els.resourceForm.addEventListener('reset', () => {
        window.setTimeout(() => {
          resetFileUi();
          updateDescriptionCount();
          toggleSourcePanels();
          setMessage(els.message);
        }, 0);
      });
    }

    toggleSourcePanels();
    updateDescriptionCount();

    // Initial Load
    (async () => {
      try {
        await Promise.all([fetchCategories(), fetchResources()]);
        if (getStoredApiKey()) {
          await fetchLogs();
        } else {
          if (els.topResourcesList) els.topResourcesList.innerHTML = '<div class="loading-state">Enter the Admin API Key to load activity.</div>';
          if (els.logsTableBody) els.logsTableBody.innerHTML = '<tr class="empty-row"><td colspan="5">Admin authentication is required to load download logs.</td></tr>';
        }
      } catch (error) {
        setMessage(els.message, error.message, 'error');
      }
    })();
  });

  // Refresh logs automatically when API key updates
  if (els.apiKey) {
    els.apiKey.addEventListener('change', async () => {
      if (!getStoredApiKey()) return;
      try {
        await fetchLogs();
      } catch (error) {
        setMessage(els.message, error.message, 'error');
      }
    });
  }

})();

// Navigation Javascript Script (Appended for Navigation Bar Functionality)
(function() {
    'use strict';
    const toggle = document.getElementById('menuToggle');
    const drawer = document.getElementById('navLinks');
    const overlay = document.getElementById('navOverlay');
    const nav = document.getElementById('mainNav');

    function openMenu() {
        drawer.classList.add('mobile-drawer-open');
        overlay.classList.add('active');
        toggle.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
    }

    function closeMenu() {
        drawer.classList.remove('mobile-drawer-open');
        overlay.classList.remove('active');
        toggle.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';

        document.querySelectorAll('.nav-dropdown-toggle.open').forEach(function(btn) {
            btn.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        });
        document.querySelectorAll('.nav-dropdown-menu.open').forEach(function(menu) {
            menu.classList.remove('open');
        });
    }

    function isOpen() {
        return drawer.classList.contains('mobile-drawer-open');
    }

    if (toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            isOpen() ? closeMenu() : openMenu();
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMenu);
    }

    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 992 && isOpen()) {
                closeMenu();
            }
            initDesktopDropdowns();
        }, 250);
    });

    window.addEventListener('scroll', function() {
        if (nav) {
            nav.classList.toggle('scrolled', window.scrollY > 20);
        }
    });

    document.querySelectorAll('.nav-dropdown-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            if(window.innerWidth <= 992) {
                e.preventDefault();
                e.stopPropagation();
                const menu = this.nextElementSibling;
                if (!menu) return;

                const isOpen = this.classList.contains('open');

                document.querySelectorAll('.nav-dropdown-toggle.open').forEach(function(toggle) {
                    toggle.classList.remove('open');
                    toggle.setAttribute('aria-expanded', 'false');
                });
                document.querySelectorAll('.nav-dropdown-menu.open').forEach(function(dropdown) {
                    dropdown.classList.remove('open');
                });

                if (!isOpen) {
                    this.classList.add('open');
                    this.setAttribute('aria-expanded', 'true');
                    menu.classList.add('open');
                }
            }
        });
    });

    document.addEventListener('click', function(event) {
        const isDropdown = event.target.closest('.nav-dropdown');
        if (!isDropdown && window.innerWidth <= 992) {
            document.querySelectorAll('.nav-dropdown-toggle.open').forEach(function(btn) {
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            });
            document.querySelectorAll('.nav-dropdown-menu.open').forEach(function(menu) {
                menu.classList.remove('open');
            });
        }
    });

    function initDesktopDropdowns() {
        document.querySelectorAll('.nav-dropdown').forEach(function(dropdown) {
            if (dropdown._desktopListenersAttached) return;
            
            const toggleBtn = dropdown.querySelector('.nav-dropdown-toggle');
            const menu = dropdown.querySelector('.nav-dropdown-menu');

            if (toggleBtn && menu && window.innerWidth > 992) {
                const handleMouseEnter = function() {
                    toggleBtn.classList.add('open');
                    toggleBtn.setAttribute('aria-expanded', 'true');
                    menu.classList.add('open');
                };

                const handleMouseLeave = function() {
                    toggleBtn.classList.remove('open');
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    menu.classList.remove('open');
                };

                dropdown.addEventListener('mouseenter', handleMouseEnter);
                dropdown.addEventListener('mouseleave', handleMouseLeave);
                
                dropdown._desktopListenersAttached = true;
                dropdown._cleanupDesktop = function() {
                    dropdown.removeEventListener('mouseenter', handleMouseEnter);
                    dropdown.removeEventListener('mouseleave', handleMouseLeave);
                    dropdown._desktopListenersAttached = false;
                };
            } else if (dropdown._cleanupDesktop) {
                dropdown._cleanupDesktop();
            }
        });
    }

    initDesktopDropdowns();

    const searchToggle = document.getElementById('searchToggle');
    const searchOverlay = document.getElementById('searchModalOverlay');
    const searchClose = document.getElementById('searchModalClose');
    const searchInput = document.getElementById('searchModalInput');

    function openSearch() {
        if (!searchOverlay) return;
        if (isOpen()) closeMenu();
        searchOverlay.classList.add('active');
        if (searchToggle) searchToggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        setTimeout(function() {
            if (searchInput) searchInput.focus();
        }, 60);
    }

    function closeSearch() {
        if (!searchOverlay) return;
        searchOverlay.classList.remove('active');
        if (searchToggle) searchToggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    function isSearchOpen() {
        return !!(searchOverlay && searchOverlay.classList.contains('active'));
    }

    if (searchToggle) {
        searchToggle.addEventListener('click', function(e) {
            e.preventDefault();
            openSearch();
        });
    }

    if (searchClose) {
        searchClose.addEventListener('click', function(e) {
            e.preventDefault();
            closeSearch();
        });
    }

    if (searchOverlay) {
        searchOverlay.addEventListener('mousedown', function(e) {
            if (e.target === searchOverlay) closeSearch();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isSearchOpen()) closeSearch();
    });
})();