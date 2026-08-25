// admin/js/admin.js
// Redesigned vanilla JS frontend for the existing Mfano Bora Resources API.
// No build step is required.

const API_BASE = '/api';
const STORAGE_KEY = 'mb_admin_api_key';

const els = {
  apiKey: document.getElementById('apiKey'),
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
  exportCsvBtn: document.getElementById('exportCsvBtn'),
};

const state = {
  categories: [],
  resources: [],
};

// ---------------------------------------------------------------------------
// Session API key
// ---------------------------------------------------------------------------
els.apiKey.value = sessionStorage.getItem(STORAGE_KEY) || '';
els.apiKey.addEventListener('input', () => {
  sessionStorage.setItem(STORAGE_KEY, els.apiKey.value.trim());
});

function adminHeaders(extra = {}) {
  return {
    ...extra,
    'X-Api-Key': els.apiKey.value.trim(),
  };
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

function setMessage(element, text = '', type = '') {
  element.textContent = text;
  element.className = 'message' + (text ? ` visible ${type}` : '');
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
// Categories / taxonomy
// ---------------------------------------------------------------------------
async function fetchCategories() {
  const json = await fetchJson(`${API_BASE}/categories.php`);
  state.categories = Array.isArray(json.data) ? json.data : [];
  renderCategoryOptions();
}

function renderCategoryOptions() {
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

  els.statCategories.textContent = number(state.categories.length);
}

// ---------------------------------------------------------------------------
// Resources
// ---------------------------------------------------------------------------
async function fetchResources() {
  const json = await fetchJson(`${API_BASE}/resources.php`);
  state.resources = Array.isArray(json.data) ? json.data : [];
  renderResourceTable();
  els.statResources.textContent = number(state.resources.length);
}

function renderResourceTable() {
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

  const title = els.title.value.trim();
  const description = els.description.value.trim();
  const subCategoryId = els.subCategory.value;
  const isFeatured = document.getElementById('isFeatured').checked;
  const source = document.querySelector('input[name="source"]:checked')?.value || 'file';

  if (!els.apiKey.value.trim()) {
    setMessage(els.message, 'Enter the Admin API Key before saving.', 'error');
    els.apiKey.focus();
    return;
  }

  const formData = new FormData();
  formData.append('title', title);
  formData.append('description', description);
  formData.append('sub_category_id', subCategoryId);
  formData.append('is_featured', String(isFeatured));

  if (source === 'file') {
    const file = els.fileInput.files[0];
    if (!file) {
      setMessage(els.message, 'Choose a PDF file to upload.', 'error');
      return;
    }
    formData.append('file', file);
  } else {
    const fileUrl = els.fileUrl.value.trim();
    if (!fileUrl) {
      setMessage(els.message, 'Enter the hosted PDF URL.', 'error');
      els.fileUrl.focus();
      return;
    }
    // The backend determines that this is an external resource when no
    // multipart file is attached and file_url is present.
    formData.append('file_url', fileUrl);
  }

  setButtonBusy(els.submitBtn, 'Saving…');

  try {
    const json = await fetchJson(`${API_BASE}/admin/resources.php`, {
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

  const approved = window.confirm(`Delete “${resource.title}”? This cannot be undone.`);
  if (!approved) return;

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
// Categories / sub-categories creation
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
// Download logs / dashboard stats
// ---------------------------------------------------------------------------
async function fetchLogs() {
  const json = await fetchJson(`${API_BASE}/admin/logs.php?limit=100`, {
    headers: adminHeaders(),
  });

  const logs = Array.isArray(json.logs) ? json.logs : [];
  els.statDownloads.textContent = number(json.total);
  els.statDownloads24h.textContent = number(json.last_24h);
  els.logCount.textContent = number(logs.length);
  renderLogs(logs);
  renderTopResources(Array.isArray(json.top_resources) ? json.top_resources : []);
}

function renderLogs(logs) {
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
// Source toggle / upload interactions
// ---------------------------------------------------------------------------
function toggleSourcePanels() {
  const source = document.querySelector('input[name="source"]:checked')?.value || 'file';
  els.fileSourceField.hidden = source !== 'file';
  els.urlSourceField.hidden = source !== 'url';

  if (source === 'file') {
    els.fileUrl.value = '';
  } else {
    els.fileInput.value = '';
    resetFileUi();
  }
}

function resetFileUi() {
  els.filePicked.textContent = 'No file selected';
}

function handleFile(file) {
  if (!file) return;
  const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
  if (!isPdf) {
    setMessage(els.message, 'Only PDF files can be uploaded.', 'error');
    els.fileInput.value = '';
    resetFileUi();
    return;
  }

  const maxBytes = 25 * 1024 * 1024;
  if (file.size > maxBytes) {
    setMessage(els.message, 'The selected PDF is larger than the backend 25MB limit.', 'error');
    els.fileInput.value = '';
    resetFileUi();
    return;
  }

  setMessage(els.message);
  els.filePicked.textContent = `${file.name} · ${formatFileSize(file.size)}`;
}

function formatFileSize(bytes) {
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function setButtonBusy(button, text, busy = true) {
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
  els.descriptionCount.textContent = `${els.description.value.length} / 1000`;
}

// ---------------------------------------------------------------------------
// Danger Zone / Export
// ---------------------------------------------------------------------------
async function performKillAll() {
  // Defensive confirmation: require a two-step acknowledgement
  // Step 1: Are you *really* sure? (prevent accidental prompt appearance)
  if (!confirm('This action will PERMANENTLY DELETE ALL SAMPLE DATA and local uploaded files. Click OK to continue or Cancel to abort.')) {
    setMessage(els.message, 'Kill cancelled by user.', 'info');
    return;
  }

  // Step 2: type the exact confirmation phrase
  const confirmation = prompt('Type the exact confirmation phrase to permanently delete all sample data:\n\nYES_I_CONFIRM_DELETE_ALL');

  // Debug trace: helps to detect accidental invocation path (remove in production)
  try { console.debug('[admin] performKillAll invoked; confirmation input:', confirmation); } catch(e) {}

  if (confirmation !== 'YES_I_CONFIRM_DELETE_ALL') {
    setMessage(els.message, 'Confirmation mismatch — aborting.', 'error');
    return;
  }

  if (!els.apiKey.value.trim()) {
    setMessage(els.message, 'Admin API key required in this tab (paste it into the Admin API Key field).', 'error');
    els.apiKey.focus();
    return;
  }

  try {
    await fetchJson(`${API_BASE}/admin/kill_switch.php`, {
      method: 'POST',
      headers: adminHeaders({ 'Content-Type': 'application/json' }),
      body: JSON.stringify({ confirm: 'YES_I_CONFIRM_DELETE_ALL' })
    });

    setMessage(els.message, 'All sample data deleted. You may want to reload the page.', 'success');
  } catch (error) {
    setMessage(els.message, error.message || 'Kill switch failed', 'error');
  }
}

async function exportCsv() {
  if (!els.apiKey.value.trim()) {
    setMessage(els.message, 'Admin API key required in this tab (paste it into the Admin API Key field).', 'error');
    els.apiKey.focus();
    return;
  }

  try {
    const res = await fetch(`${API_BASE}/admin/export_csv.php`, {
      method: 'GET',
      headers: adminHeaders()
    });

    if (!res.ok) {
      let errorText = 'Export failed';
      try {
        const body = await res.json();
        errorText = body.error || errorText;
      } catch (e) {}
      setMessage(els.message, errorText, 'error');
      return;
    }

    // Download CSV blob
    const blob = await res.blob();
    const contentDisposition = res.headers.get('content-disposition') || '';
    let filename = 'resources_export.csv';
    const m = /filename="([^"]+)"/i.exec(contentDisposition);
    if (m) filename = m[1];

    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);

    setMessage(els.message, 'Exported CSV downloaded. A copy was saved to database/exports/ on the server (if writable).', 'success');
  } catch (error) {
    setMessage(els.message, 'Unexpected error: ' + (error.message || error), 'error');
  }
}

// ---------------------------------------------------------------------------
// Event wiring
// ---------------------------------------------------------------------------
els.resourceForm.addEventListener('submit', addResource);
els.categoryForm.addEventListener('submit', addCategory);
els.subCategoryForm.addEventListener('submit', addSubCategory);
els.resourceTableBody.addEventListener('click', handleResourceTableClick);

document.querySelectorAll('input[name="source"]').forEach((input) => {
  input.addEventListener('change', toggleSourcePanels);
});

els.fileDrop.addEventListener('click', () => els.fileInput.click());
els.fileDrop.addEventListener('keydown', (event) => {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    els.fileInput.click();
  }
});
els.fileInput.addEventListener('change', () => handleFile(els.fileInput.files[0]));

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
  if (!file) return;
  const transfer = new DataTransfer();
  transfer.items.add(file);
  els.fileInput.files = transfer.files;
  handleFile(file);
});

els.description.addEventListener('input', updateDescriptionCount);
document.getElementById('categoryName').addEventListener('input', (event) => {
  const slugField = document.getElementById('categorySlug');
  if (!slugField.dataset.userEdited) slugField.value = slugify(event.target.value);
});
document.getElementById('categorySlug').addEventListener('input', (event) => {
  event.target.dataset.userEdited = 'true';
});
els.newSubCategoryName.addEventListener('input', () => {
  if (!els.newSubCategorySlug.dataset.userEdited) {
    els.newSubCategorySlug.value = slugify(els.newSubCategoryName.value);
  }
});
els.newSubCategorySlug.addEventListener('input', (event) => {
  event.target.dataset.userEdited = 'true';
});

document.getElementById('refreshOverviewBtn').addEventListener('click', refreshOverview);
document.getElementById('refreshResourcesBtn').addEventListener('click', async () => {
  try { await fetchResources(); } catch (error) { setMessage(els.message, error.message, 'error'); }
});
document.getElementById('refreshLogsBtn').addEventListener('click', async () => {
  try { await fetchLogs(); } catch (error) { setMessage(els.message, error.message, 'error'); }
});

if (els.killAllBtn) els.killAllBtn.addEventListener('click', performKillAll);
if (els.exportCsvBtn) els.exportCsvBtn.addEventListener('click', exportCsv);

els.resourceForm.addEventListener('reset', () => {
  window.setTimeout(() => {
    resetFileUi();
    updateDescriptionCount();
    toggleSourcePanels();
    setMessage(els.message);
  }, 0);
});

toggleSourcePanels();
updateDescriptionCount();

// Initial load: public category/resource endpoints are available immediately;
// logs are protected and will show an auth message if no API key is entered.
window.addEventListener('DOMContentLoaded', async () => {
  try {
    await Promise.all([fetchCategories(), fetchResources()]);
    if (els.apiKey.value.trim()) {
      await fetchLogs();
    } else {
      els.topResourcesList.innerHTML = '<div class="loading-state">Enter the Admin API Key to load activity.</div>';
      els.logsTableBody.innerHTML = '<tr class="empty-row"><td colspan="5">Admin authentication is required to load download logs.</td></tr>';
    }
  } catch (error) {
    setMessage(els.message, error.message, 'error');
  }
});

// Refresh protected dashboard data automatically when the user enters a key.
els.apiKey.addEventListener('change', async () => {
  if (!els.apiKey.value.trim()) return;
  try {
    await fetchLogs();
  } catch (error) {
    setMessage(els.message, error.message, 'error');
  }
});