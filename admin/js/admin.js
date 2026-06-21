/**
 * Fundación Bon Sens - Panel de Administración
 * Módulo completo de gestión: login, dashboard, mensajes, noticias, suscriptores, donaciones.
 */

const API = {
  base: '/api',
  async request(method, path, data = null, auth = true) {
    const opts = {
      method,
      headers: { 'Content-Type': 'application/json' },
    };
    if (auth && getToken()) {
      opts.headers['Authorization'] = `Bearer ${getToken()}`;
    }
    if (data) opts.body = JSON.stringify(data);

    const res = await fetch(`${this.base}${path}`, opts);
    const json = await res.json();
    if (!res.ok && !json.success) throw new Error(json.error || 'Error de conexión');
    return json;
  },
  get(path) { return this.request('GET', path); },
  post(path, data) { return this.request('POST', path, data); },
  put(path, data) { return this.request('PUT', path, data); },
  patch(path) { return this.request('PATCH', path); },
  delete(path) { return this.request('DELETE', path); },
};

// ─── Token management ────────────────────────────────────────────

function getToken() {
  return localStorage.getItem('admin_token');
}

function setToken(token) {
  localStorage.setItem('admin_token', token);
}

function removeToken() {
  localStorage.removeItem('admin_token');
}

// ─── Login ────────────────────────────────────────────────────────

document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const user = document.getElementById('login-user').value;
  const pass = document.getElementById('login-pass').value;
  const errorEl = document.getElementById('login-error');
  errorEl.classList.add('hidden');

  try {
    const res = await API.post('/admin/login', { username: user, password: pass });
    if (res.success && res.token) {
      setToken(res.token);
      showPanel();
      loadDashboard();
    }
  } catch (err) {
    errorEl.textContent = err.message || 'Credenciales inválidas';
    errorEl.classList.remove('hidden');
  }
});

document.getElementById('logout-btn').addEventListener('click', () => {
  removeToken();
  document.getElementById('admin-panel').classList.add('hidden');
  document.getElementById('login-screen').classList.remove('hidden');
  document.getElementById('login-pass').value = '';
});

// ─── Navigation ───────────────────────────────────────────────────

document.querySelectorAll('.sidebar-link').forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
    link.classList.add('active');
    document.querySelectorAll('.view').forEach(v => v.classList.add('hidden'));
    const view = link.dataset.view;
    document.getElementById(`view-${view}`).classList.remove('hidden');
    loadView(view);
  });
});

// ─── Show/hide panel ──────────────────────────────────────────────

async function showPanel() {
  document.getElementById('login-screen').classList.add('hidden');
  document.getElementById('admin-panel').classList.remove('hidden');
}

// ─── View loader ─────────────────────────────────────────────────

function loadView(view) {
  switch (view) {
    case 'dashboard': loadDashboard(); break;
    case 'messages': loadMessages(); break;
    case 'news': loadNewsList(); break;
    case 'subscribers': loadSubscribers(); break;
    case 'donations': loadDonations(); break;
  }
}

// ─── APP INIT ─────────────────────────────────────────────────────

(async function init() {
  const token = getToken();
  if (!token) return;

  try {
    await API.get('/admin/me');
    showPanel();
    loadDashboard();
  } catch {
    removeToken();
  }
})();

// ─── DASHBOARD ───────────────────────────────────────────────────

async function loadDashboard() {
  try {
    const res = await API.get('/admin/dashboard');
    if (!res.success || !res.stats) return;
    const s = res.stats;

    document.getElementById('stat-messages').textContent = s.total_messages || 0;
    document.getElementById('stat-unread').textContent = s.unread_messages || 0;
    document.getElementById('stat-subscribers').textContent = s.total_subscribers || 0;
    document.getElementById('stat-news').textContent = s.published_news || 0;

    // Badge no leídos
    const badge = document.getElementById('msg-badge');
    if (s.unread_messages > 0) {
      badge.textContent = s.unread_messages;
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
    }

    // Últimos mensajes
    const container = document.getElementById('recent-messages');
    if (s.recent_messages && s.recent_messages.length > 0) {
      container.innerHTML = s.recent_messages.map(m => `
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg ${!m.is_read ? 'border-l-4 border-red-500' : ''}">
          <div>
            <p class="font-medium text-sm">${escapeHtml(m.name)}</p>
            <p class="text-xs text-gray-500">${escapeHtml(m.email)}</p>
          </div>
          <span class="text-xs text-gray-400">${timeAgo(m.created_at)}</span>
        </div>
      `).join('');
    } else {
      container.innerHTML = '<p class="text-gray-400 text-sm">No hay mensajes aún.</p>';
    }
  } catch (err) {
    console.error('Dashboard error:', err);
  }
}

// ─── MESSAGES ────────────────────────────────────────────────────

let msgPage = 1;

async function loadMessages() {
  try {
    const res = await API.get(`/contact?page=${msgPage}&limit=20`);
    const container = document.getElementById('messages-list');
    const pagination = document.getElementById('msg-pagination');

    if (!res.messages || res.messages.length === 0) {
      container.innerHTML = '<div class="bg-white rounded-xl p-8 text-center text-gray-400">No hay mensajes de contacto.</div>';
      pagination.innerHTML = '';
      return;
    }

    container.innerHTML = res.messages.map(m => `
      <div class="bg-white rounded-xl p-5 shadow-sm ${!m.is_read ? 'border-l-4 border-red-500' : ''}">
        <div class="flex items-start justify-between mb-3">
          <div>
            <h4 class="font-semibold">${escapeHtml(m.name)}</h4>
            <p class="text-sm text-gray-500">${escapeHtml(m.email)}${m.phone ? ' · ' + escapeHtml(m.phone) : ''}</p>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400">${timeAgo(m.created_at)}</span>
            ${!m.is_read ? `<button onclick="markRead(${m.id})" class="text-blue-600 hover:text-blue-800 text-xs font-medium">Leído</button>` : ''}
            <button onclick="deleteMsg(${m.id})" class="text-red-500 hover:text-red-700 text-xs"><i class="fa-solid fa-trash-can"></i></button>
          </div>
        </div>
        ${m.subject ? `<p class="text-xs font-medium text-gray-500 mb-1">Asunto: ${escapeHtml(m.subject)}</p>` : ''}
        <p class="text-gray-700 text-sm">${escapeHtml(m.message)}</p>
      </div>
    `).join('');

    // Pagination
    if (res.pages > 1) {
      pagination.innerHTML = Array.from({ length: res.pages }, (_, i) =>
        `<button onclick="msgPage=${i + 1};loadMessages()" class="px-3 py-1 rounded-lg ${i + 1 === msgPage ? 'bg-[#CC0000] text-white' : 'bg-white text-gray-600 border'} text-sm">${i + 1}</button>`
      ).join('');
    } else {
      pagination.innerHTML = '';
    }

    // Update badge
    if (res.unread > 0) {
      document.getElementById('msg-badge').textContent = res.unread;
      document.getElementById('msg-badge').classList.remove('hidden');
    } else {
      document.getElementById('msg-badge').classList.add('hidden');
    }
  } catch (err) {
    document.getElementById('messages-list').innerHTML = `<div class="bg-red-50 rounded-xl p-4 text-red-600 text-sm">Error: ${err.message}</div>`;
  }
}

async function markRead(id) {
  try {
    await API.patch(`/contact/${id}/read`);
    loadMessages();
  } catch (err) { alert(err.message); }
}

async function deleteMsg(id) {
  if (!confirm('¿Eliminar este mensaje?')) return;
  try {
    await API.delete(`/contact/${id}`);
    loadMessages();
  } catch (err) { alert(err.message); }
}

document.getElementById('refresh-msg-btn')?.addEventListener('click', loadMessages);

// ─── NEWS ────────────────────────────────────────────────────────

let newsPage = 1;

async function loadNewsList() {
  try {
    const res = await API.get(`/news?page=${newsPage}&limit=12&all=1`);
    const container = document.getElementById('news-list');
    const pagination = document.getElementById('news-pagination');

    if (!res.news || res.news.length === 0) {
      container.innerHTML = '<div class="col-span-full bg-white rounded-xl p-8 text-center text-gray-400">No hay noticias. ¡Crea la primera!</div>';
      pagination.innerHTML = '';
      return;
    }

    container.innerHTML = res.news.map(n => `
      <div class="bg-white rounded-xl shadow-sm overflow-hidden card-hover">
        ${n.image_url ? `<img src="${n.image_url}" alt="" class="w-full h-36 object-cover" />` : '<div class="w-full h-36 bg-gray-100 flex items-center justify-center text-gray-300"><i class="fa-solid fa-image text-3xl"></i></div>'}
        <div class="p-4">
          <span class="text-xs font-medium text-[#CC0000] uppercase">${n.category || 'general'}</span>
          <h3 class="font-semibold text-gray-800 mt-1 mb-1">${escapeHtml(n.title)}</h3>
          <p class="text-xs text-gray-400 mb-3">${timeAgo(n.published_at)} ${!n.is_published ? '· <span class="text-yellow-600">Borrador</span>' : ''}</p>
          <p class="text-sm text-gray-600 mb-3 line-clamp-2">${escapeHtml(n.excerpt || '')}</p>
          <div class="flex gap-2">
            <button onclick="editNews(${n.id})" class="text-blue-600 hover:text-blue-800 text-xs font-medium"><i class="fa-solid fa-pen"></i> Editar</button>
            <button onclick="deleteNews(${n.id})" class="text-red-500 hover:text-red-700 text-xs font-medium"><i class="fa-solid fa-trash-can"></i> Eliminar</button>
          </div>
        </div>
      </div>
    `).join('');

    if (res.pages > 1) {
      pagination.innerHTML = Array.from({ length: res.pages }, (_, i) =>
        `<button onclick="newsPage=${i + 1};loadNewsList()" class="px-3 py-1 rounded-lg ${i + 1 === newsPage ? 'bg-[#CC0000] text-white' : 'bg-white text-gray-600 border'} text-sm">${i + 1}</button>`
      ).join('');
    } else {
      pagination.innerHTML = '';
    }
  } catch (err) {
    document.getElementById('news-list').innerHTML = `<div class="col-span-full text-red-600">Error: ${err.message}</div>`;
  }
}

// News modal
const newsModal = document.getElementById('news-modal');
document.getElementById('new-news-btn').addEventListener('click', () => {
  document.getElementById('modal-title').textContent = 'Nueva Noticia';
  document.getElementById('news-form').reset();
  document.getElementById('news-id').value = '';
  document.getElementById('news-error').classList.add('hidden');
  newsModal.classList.remove('hidden');
});

document.getElementById('close-modal').addEventListener('click', () => newsModal.classList.add('hidden'));
document.getElementById('cancel-modal').addEventListener('click', () => newsModal.classList.add('hidden'));
newsModal.addEventListener('click', (e) => { if (e.target === newsModal) newsModal.classList.add('hidden'); });

document.getElementById('news-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errorEl = document.getElementById('news-error');
  errorEl.classList.add('hidden');
  const id = document.getElementById('news-id').value;
  const data = {
    title: document.getElementById('news-title').value,
    slug: document.getElementById('news-slug').value,
    excerpt: document.getElementById('news-excerpt').value,
    content: document.getElementById('news-content').value,
    image_url: document.getElementById('news-image').value,
    category: document.getElementById('news-category').value,
  };

  try {
    if (id) {
      await API.put(`/news/${id}`, data);
    } else {
      await API.post('/news', data);
    }
    newsModal.classList.add('hidden');
    loadNewsList();
  } catch (err) {
    errorEl.textContent = err.message;
    errorEl.classList.remove('hidden');
  }
});

async function editNews(id) {
  try {
    const res = await API.get(`/news/${id}`);
    if (!res.success) return;
    const n = res.data;
    document.getElementById('modal-title').textContent = 'Editar Noticia';
    document.getElementById('news-id').value = n.id;
    document.getElementById('news-title').value = n.title;
    document.getElementById('news-slug').value = n.slug;
    document.getElementById('news-excerpt').value = n.excerpt || '';
    document.getElementById('news-content').value = n.content || '';
    document.getElementById('news-image').value = n.image_url || '';
    document.getElementById('news-category').value = n.category || 'general';
    document.getElementById('news-error').classList.add('hidden');
    newsModal.classList.remove('hidden');
  } catch (err) { alert(err.message); }
}

async function deleteNews(id) {
  if (!confirm('¿Eliminar esta noticia definitivamente?')) return;
  try {
    await API.delete(`/news/${id}`);
    loadNewsList();
  } catch (err) { alert(err.message); }
}

// Auto-generar slug desde título
document.getElementById('news-title').addEventListener('input', function() {
  const slugField = document.getElementById('news-slug');
  if (!slugField.dataset.manual) {
    slugField.value = this.value
      .toLowerCase()
      .replace(/[^a-z0-9áéíóúüñ ]/g, '')
      .replace(/[á]/g, 'a').replace(/[é]/g, 'e').replace(/[í]/g, 'i').replace(/[ó]/g, 'o').replace(/[ú]/g, 'u')
      .replace(/[ñ]/g, 'n').replace(/[ü]/g, 'u')
      .trim().replace(/\s+/g, '-')
      .slice(0, 80);
  }
});
document.getElementById('news-slug').addEventListener('input', function() {
  this.dataset.manual = this.value ? 'true' : '';
});

// ─── SUBSCRIBERS ─────────────────────────────────────────────────

let subPage = 1;

async function loadSubscribers() {
  try {
    const res = await API.get(`/subscribe?page=${subPage}&limit=50`);
    const tbody = document.getElementById('subscribers-tbody');
    const pagination = document.getElementById('sub-pagination');

    if (!res.subscribers || res.subscribers.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="p-4 text-center text-gray-400">No hay suscriptores.</td></tr>';
      pagination.innerHTML = '';
      return;
    }

    tbody.innerHTML = res.subscribers.map(s => `
      <tr>
        <td class="p-3">${escapeHtml(s.email)}</td>
        <td class="p-3 text-gray-500">${escapeHtml(s.name || '—')}</td>
        <td class="p-3 text-gray-400 text-xs">${timeAgo(s.created_at)}</td>
        <td class="p-3 text-center">
          <button onclick="deleteSub(${s.id})" class="text-red-500 hover:text-red-700 text-xs"><i class="fa-solid fa-trash-can"></i></button>
        </td>
      </tr>
    `).join('');

    if (res.pages > 1) {
      pagination.innerHTML = Array.from({ length: res.pages }, (_, i) =>
        `<button onclick="subPage=${i + 1};loadSubscribers()" class="px-3 py-1 rounded-lg ${i + 1 === subPage ? 'bg-[#CC0000] text-white' : 'bg-white text-gray-600 border'} text-sm">${i + 1}</button>`
      ).join('');
    } else {
      pagination.innerHTML = '';
    }
  } catch (err) {
    document.getElementById('subscribers-tbody').innerHTML = `<tr><td colspan="4" class="p-4 text-center text-red-600">Error: ${err.message}</td></tr>`;
  }
}

async function deleteSub(id) {
  if (!confirm('¿Eliminar este suscriptor?')) return;
  try {
    await API.delete(`/subscribe/${id}`);
    loadSubscribers();
  } catch (err) { alert(err.message); }
}

// ─── DONATIONS ───────────────────────────────────────────────────

async function loadDonations() {
  try {
    const res = await API.get('/donations');
    const statsContainer = document.getElementById('donations-stats');
    const tbody = document.getElementById('donations-tbody');

    if (res.stats) {
      statsContainer.innerHTML = `
        <div class="bg-white rounded-xl p-5 shadow-sm card-hover">
          <p class="text-2xl font-bold text-gray-800">${res.stats.count || 0}</p>
          <p class="text-xs text-gray-500">Donaciones completadas</p>
        </div>
        <div class="bg-white rounded-xl p-5 shadow-sm card-hover">
          <p class="text-2xl font-bold text-green-600">$${(res.stats.total_amount || 0).toLocaleString('es-CL')}</p>
          <p class="text-xs text-gray-500">Total recaudado</p>
        </div>
      `;
    }

    if (!res.donations || res.donations.length === 0) {
      tbody.innerHTML = '<tr><td colspan="5" class="p-4 text-center text-gray-400">No hay donaciones registradas.</td></tr>';
      return;
    }

    tbody.innerHTML = res.donations.map(d => `
      <tr>
        <td class="p-3">${escapeHtml(d.donor_name || 'Anónimo')}</td>
        <td class="p-3 font-medium">$${Number(d.amount).toLocaleString('es-CL')}</td>
        <td class="p-3 text-gray-500">${d.payment_method}</td>
        <td class="p-3"><span class="text-xs px-2 py-1 rounded-full ${d.status === 'completed' ? 'bg-green-100 text-green-700' : d.status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'}">${d.status}</span></td>
        <td class="p-3 text-xs text-gray-400">${timeAgo(d.created_at)}</td>
      </tr>
    `).join('');
  } catch (err) {
    document.getElementById('donations-tbody').innerHTML = `<tr><td colspan="5" class="p-4 text-center text-red-600">Error: ${err.message}</td></tr>`;
  }
}

// ─── Utilities ───────────────────────────────────────────────────

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr + 'Z');
  const now = new Date();
  const diff = Math.floor((now - date) / 1000);

  if (diff < 60) return 'Ahora';
  if (diff < 3600) return `${Math.floor(diff / 60)}m`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
  if (diff < 604800) return `${Math.floor(diff / 86400)}d`;
  return date.toLocaleDateString('es-CL');
}
