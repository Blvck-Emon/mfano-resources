/**
 * frontend/js/resources.js
 * Combined Frontend Controller & Tabbed Viewer Script for Mfano Bora Africa
 * 
 * Features:
 * - Mobile navigation menu toggle.
 * - Intersection Observer card animations.
 * - Nested resource fetching & hierarchical rendering (categories -> sub_categories -> resources)[cite: 17].
 * - Live search/filtering across resource cards.
 * - Right-hand in-page tabbed document viewer for PDF streaming (?action=view)[cite: 17].
 * - Direct download trigger with server-side audit logging (?action=download)[cite: 17].
 */

document.addEventListener('DOMContentLoaded', function () {
  const API_BASE = window.location.origin + '/Backend+AdminPanel/api';
  
  // DOM Elements
  const catsContainer = document.getElementById('categoriesContainer');
  const tabsBar = document.getElementById('tabsBar');
  const tabsHeader = document.getElementById('tabsHeader');
  const iframeWrap = document.getElementById('iframeWrap');
  const viewerToolbar = document.getElementById('viewerToolbar');
  const openInBrowserBtn = document.getElementById('openInBrowserBtn');
  const closeTabBtn = document.getElementById('closeTabBtn');
  const viewerEmpty = document.getElementById('viewerEmpty');
  const tabsContainer = document.getElementById('tabsContainer');
  const closeAllTabsBtn = document.getElementById('closeAllTabs');
  const searchInput = document.getElementById('resourceSearch');
  const mobileMenuButton = document.getElementById('mobileMenuButton');
  const mainNavigation = document.querySelector('.main-navigation');

  // State: open tabs array [{ id, title, iframeEl, tabBtn }]
  const openTabs = [];

  /* =========================================
     1. MOBILE NAVIGATION CONTROLLER
  ========================================= */
  if (mobileMenuButton && mainNavigation) {
    mobileMenuButton.addEventListener('click', function () {
      mainNavigation.classList.toggle('show');
    });
  }

  const navigationLinks = document.querySelectorAll('.main-navigation a');
  navigationLinks.forEach(function (link) {
    link.addEventListener('click', function () {
      if (mainNavigation) {
        mainNavigation.classList.remove('show');
      }
    });
  });

  /* =========================================
     2. INTERSECTION OBSERVER ANIMATIONS
  ========================================= */
  const observerOptions = { threshold: 0.1 };
  const observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
      }
    });
  }, observerOptions);

  function observeCards() {
    const animatableCards = document.querySelectorAll('.category, .subcat, .resource-card');
    animatableCards.forEach(function (card) {
      observer.observe(card);
    });
  }

  /* =========================================
     3. FETCH & HIERARCHICAL RENDERING
  ========================================= */
  function fetchAndRender() {
    if (!catsContainer) return;

    fetch(API_BASE + '/resources.php')
      .then(r => r.ok ? r.json() : Promise.reject('Network response failed'))
      .then(json => {
        if (!json.success) throw new Error(json.message || 'API error');
        const data = json.data || [];
        renderCategories(data);
      })
      .catch(err => {
        console.error('Failed to load resources', err);
        catsContainer.innerHTML = '<p class="no-resources">Failed to load resources from server.</p>';
      });
  }

  function renderCategories(categories) {
    catsContainer.innerHTML = '';
    if (!categories || categories.length === 0) {
      catsContainer.innerHTML = '<div class="no-resources">No categories found.</div>';
      return;
    }

    categories.forEach(cat => {
      const cdiv = document.createElement('div');
      cdiv.className = 'category';
      
      const ctitle = document.createElement('h4');
      ctitle.textContent = cat.name;
      cdiv.appendChild(ctitle);

      if (!cat.sub_categories || cat.sub_categories.length === 0) {
        const p = document.createElement('div');
        p.className = 'no-resources';
        p.textContent = 'No sub-categories.';
        cdiv.appendChild(p);
      } else {
        cat.sub_categories.forEach(sub => {
          const sdiv = document.createElement('div');
          sdiv.className = 'subcat';
          
          const sTitle = document.createElement('strong');
          sTitle.textContent = sub.name;
          sdiv.appendChild(sTitle);

          if (!sub.resources || sub.resources.length === 0) {
            const p = document.createElement('div');
            p.className = 'no-resources';
            p.textContent = 'No resources in this sub-category.';
            sdiv.appendChild(p);
          } else {
            sub.resources.forEach(r => {
              const rc = document.createElement('div');
              rc.className = 'resource-card';
              rc.setAttribute('data-id', r.id);

              const h = document.createElement('div');
              h.style.fontWeight = '600';
              h.textContent = r.title || 'Untitled';
              rc.appendChild(h);

              if (r.description) {
                const desc = document.createElement('div');
                desc.style.fontSize = '13px';
                desc.style.color = '#555';
                desc.textContent = r.description;
                rc.appendChild(desc);
              }

              const controls = document.createElement('div');
              controls.className = 'resource-controls';

              const viewBtn = document.createElement('button');
              viewBtn.className = 'btn btn-primary';
              viewBtn.textContent = 'View 👁️';
              viewBtn.addEventListener('click', () => openResourceInRightTab(r));

              const downloadBtn = document.createElement('button');
              downloadBtn.className = 'btn';
              downloadBtn.textContent = 'Download ⬇';
              downloadBtn.addEventListener('click', () => {
                const url = API_BASE + '/download.php?resource_id=' + encodeURIComponent(r.id) + '&action=download';
                window.open(url, '_blank', 'noopener');
              });

              controls.appendChild(viewBtn);
              controls.appendChild(downloadBtn);
              rc.appendChild(controls);

              sdiv.appendChild(rc);
            });
          }

          cdiv.appendChild(sdiv);
        });
      }

      catsContainer.appendChild(cdiv);
    });

    observeCards();
  }

  /* =========================================
     4. LIVE SEARCH & FILTERING
  ========================================= */
  if (searchInput) {
    searchInput.addEventListener('input', function () {
      const term = this.value.toLowerCase().trim();
      const resourceCards = document.querySelectorAll('.resource-card');
      
      resourceCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        if (text.includes(term)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });
  }

  /* =========================================
     5. RIGHT-PANE TABBED VIEWER FUNCTIONS
  ========================================= */
  function openResourceInRightTab(resource) {
    const existing = openTabs.find(t => t.id === resource.id);
    if (existing) {
      activateTab(existing);
      return;
    }

    if (viewerEmpty) viewerEmpty.style.display = 'none';
    if (tabsContainer) tabsContainer.style.display = 'flex';
    if (viewerToolbar) viewerToolbar.style.display = 'flex';
    if (tabsBar) tabsBar.style.display = 'flex';

    const tabBtn = document.createElement('div');
    tabBtn.className = 'tab';
    tabBtn.textContent = resource.title || 'Untitled';
    if (tabsHeader) tabsHeader.appendChild(tabBtn);

    const iframe = document.createElement('iframe');
    iframe.className = 'viewer-iframe';
    iframe.setAttribute('sandbox', 'allow-forms allow-scripts allow-same-origin allow-popups');
    iframe.src = API_BASE + '/download.php?resource_id=' + encodeURIComponent(resource.id) + '&action=view';
    iframe.style.width = '100%';
    iframe.style.height = '100%';
    iframe.dataset.resourceId = resource.id;

    if (iframeWrap) iframeWrap.appendChild(iframe);

    const tab = { id: resource.id, title: resource.title || '', iframeEl: iframe, tabBtn: tabBtn };

    tabBtn.addEventListener('click', () => activateTab(tab));
    tabBtn.addEventListener('auxclick', (ev) => {
      if (ev.button === 1) closeTab(tab); // Middle-click to close
    });

    if (openInBrowserBtn) {
      openInBrowserBtn.onclick = () => {
        const activeTab = openTabs.find(t => t.tabBtn.classList.contains('active'));
        if (!activeTab) return;
        window.open(activeTab.iframeEl.src, '_blank', 'noopener');
      };
    }

    if (closeTabBtn) {
      closeTabBtn.onclick = () => {
        const activeTab = openTabs.find(t => t.tabBtn.classList.contains('active'));
        if (activeTab) closeTab(activeTab);
      };
    }

    if (closeAllTabsBtn) {
      closeAllTabsBtn.onclick = closeAllTabs;
    }

    openTabs.push(tab);
    activateTab(tab);
  }

  function activateTab(tab) {
    openTabs.forEach(t => {
      t.tabBtn.classList.remove('active');
      t.iframeEl.style.display = 'none';
    });
    tab.tabBtn.classList.add('active');
    tab.iframeEl.style.display = 'block';
    
    const titleEl = document.getElementById('tabTitle');
    if (titleEl) titleEl.textContent = tab.title || '';
  }

  function closeTab(tab) {
    const idx = openTabs.findIndex(t => t.id === tab.id);
    if (idx === -1) return;

    try { tab.tabBtn.remove(); } catch (e) {}
    try { tab.iframeEl.remove(); } catch (e) {}
    openTabs.splice(idx, 1);

    if (openTabs.length > 0) {
      activateTab(openTabs[openTabs.length - 1]);
    } else {
      if (tabsContainer) tabsContainer.style.display = 'none';
      if (viewerEmpty) viewerEmpty.style.display = 'block';
      if (viewerToolbar) viewerToolbar.style.display = 'none';
      const titleEl = document.getElementById('tabTitle');
      if (titleEl) titleEl.textContent = '';
    }
  }

  function closeAllTabs() {
    openTabs.slice().forEach(t => closeTab(t));
  }

  // Initialize application
  fetchAndRender();
});