// admin/js/admin.js
//
// Vanilla JS replacement for the React KnowledgeBaseEditor.js component.
// No build step — this file is served as-is by the browser.

// Adjust this if the resources-portal folder is deployed somewhere other
// than the site root, e.g. '/resources-portal/api'.
const API_BASE = '/api';

const apiKeyInput = document.getElementById('apiKey');
const messageEl = document.getElementById('message');
const form = document.getElementById('resourceForm');
const submitBtn = document.getElementById('submitBtn');
const subCategorySelect = document.getElementById('subCategory');
const resourceTableBody = document.getElementById('resourceTableBody');
const resourceCountEl = document.getElementById('resourceCount');

// Persist the key only for this browser tab, never in the HTML/JS source.
apiKeyInput.value = sessionStorage.getItem('mb_admin_api_key') || '';
apiKeyInput.addEventListener('input', () => {
  sessionStorage.setItem('mb_admin_api_key', apiKeyInput.value);
});

document.addEventListener('DOMContentLoaded', () => {
  fetchCategories();
  fetchResources();
});

async function fetchCategories() {
  try {
    const res = await fetch(`${API_BASE}/categories.php`);
    const json = await res.json();
    if (json.success) {
      renderCategoryOptions(json.data);
    }
  } catch (err) {
    console.error('Failed to load categories', err);
  }
}

function renderCategoryOptions(categories) {
  subCategorySelect.innerHTML = '<option value="">Select Sub-Category</option>';
  categories.forEach((cat) => {
    const optgroup = document.createElement('optgroup');
    optgroup.label = cat.name;
    (cat.subcategories || []).forEach((sub) => {
      const option = document.createElement('option');
      option.value = sub.id;
      option.textContent = sub.name;
      optgroup.appendChild(option);
    });
    subCategorySelect.appendChild(optgroup);
  });
}

async function fetchResources() {
  try {
    const res = await fetch(`${API_BASE}/resources.php`);
    const json = await res.json();
    if (json.success) {
      renderResourceTable(json.data);
    }
  } catch (err) {
    console.error('Failed to load resources', err);
  }
}

function renderResourceTable(resources) {
  resourceCountEl.textContent = resources.length;
  resourceTableBody.innerHTML = '';

  resources.forEach((res) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${res.id}</td>
      <td class="title-cell">${escapeHtml(res.title)}</td>
      <td>${escapeHtml(res.category_name)} &gt; ${escapeHtml(res.sub_category_name)}</td>
      <td>${res.download_count}</td>
      <td>${res.is_featured ? 'Yes' : 'No'}</td>
      <td>
        <a href="${res.file_url}" target="_blank" rel="noreferrer">View</a>
        <button class="delete-btn" data-id="${res.id}">Delete</button>
      </td>
    `;
    resourceTableBody.appendChild(tr);
  });

  resourceTableBody.querySelectorAll('.delete-btn').forEach((btn) => {
    btn.addEventListener('click', () => handleDelete(btn.dataset.id));
  });
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  setMessage('', null);
  submitBtn.disabled = true;
  submitBtn.textContent = 'Saving...';

  const payload = {
    title: document.getElementById('title').value,
    description: document.getElementById('description').value,
    file_url: document.getElementById('fileUrl').value,
    sub_category_id: subCategorySelect.value,
    is_featured: document.getElementById('isFeatured').checked,
  };

  try {
    const res = await fetch(`${API_BASE}/admin/resources.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Api-Key': apiKeyInput.value,
      },
      body: JSON.stringify(payload),
    });

    const json = await res.json();
    if (json.success) {
      setMessage('Resource successfully created!', 'success');
      form.reset();
      fetchResources();
    } else {
      setMessage('Error: ' + json.error, 'error');
    }
  } catch (err) {
    setMessage('Failed to connect to backend server.', 'error');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Add Resource';
  }
});

async function handleDelete(id) {
  if (!confirm('Are you sure you want to delete this resource?')) return;

  try {
    const res = await fetch(`${API_BASE}/admin/resources.php?id=${id}`, {
      method: 'DELETE',
      headers: { 'X-Api-Key': apiKeyInput.value },
    });
    const json = await res.json();
    if (!json.success) {
      alert('Error: ' + json.error);
    }
  } catch (err) {
    alert('Failed to connect to backend server.');
  }
  fetchResources();
}

function setMessage(text, type) {
  messageEl.textContent = text;
  messageEl.className = 'message' + (type ? ' ' + type : '');
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str ?? '';
  return div.innerHTML;
}
