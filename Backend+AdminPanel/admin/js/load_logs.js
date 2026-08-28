// Backend+AdminPanel/admin/js/load_logs.js
document.addEventListener('DOMContentLoaded', function () {
  const API_BASE = (function () {
    const p = window.location.pathname || '/';
    const adminIndex = p.indexOf('/admin/');
    if (adminIndex !== -1) return p.slice(0, adminIndex) + '/api';
    return '/api';
  })();

  const adminKey = (function () {
    try { return localStorage.getItem('mfano_admin_api_key') || ''; } catch (e) { return ''; }
  })();

  function apiFetch(path, opts = {}) {
    const url = `${API_BASE}${path.startsWith('/') ? path : '/' + path}`;
    const headers = Object.assign({}, opts.headers || {});
    if (adminKey) headers['X-Api-Key'] = adminKey;
    return fetch(url, Object.assign({ credentials: 'same-origin', headers }, opts));
  }

  function loadLogs() {
    const tbody = document.getElementById('logsTableBody') || document.querySelector('#logsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="6">Loading...</td></tr>';

    apiFetch('/admin/logs.php?limit=500')
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(json => {
        if (!json.success) throw new Error('API error');
        const rows = json.data || [];
        if (!rows.length) {
          tbody.innerHTML = '<tr><td colspan="6">No logs found.</td></tr>';
          return;
        }
        tbody.innerHTML = '';
        rows.forEach(row => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${row.id}</td>
            <td>${row.downloaded_at || ''}</td>
            <td>${row.action || ''}</td>
            <td>${row.resource_id || ''}</td>
            <td>${escapeHtml(row.resource_title || '')}</td>
            <td>${escapeHtml(row.ip_address || '')}</td>
            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(row.user_agent || '')}</td>
            <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(row.referrer || '')}</td>
          `;
          tbody.appendChild(tr);
        });
      })
      .catch(err => {
        tbody.innerHTML = '<tr><td colspan="6">Failed to load logs.</td></tr>';
        console.error(err);
      });
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  // add a refresh button if one exists, otherwise poll once
  const refreshBtn = document.getElementById('refreshLogsBtn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', loadLogs);
  }

  loadLogs();
});