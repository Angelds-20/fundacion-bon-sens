/*
  Fundación Bon Sens - Funcionalidades compartidas
  - Menú móvil
  - Header sticky
  - Contadores animados con Intersection Observer
  - Smooth scroll para anclas internas
  - Formulario de contacto via API
  - Newsletter via API
  - Botón de donación centralizado (Donando / API)
  - Sistema de notificaciones Toast
*/

// URL del portal de pagos (ej: Donando) si está integrado en producción
const DONANDO_PAYMENT_URL = "";

document.addEventListener('DOMContentLoaded', () => {
  initMobileMenu();
  initStickyHeader();
  initSmoothScroll();
  initCounters();
  initDonationButtons();
  initContactForm();
  initNewsletterForm();
  initPublicNews();
  initScrollReveal();
});

// Menú móvil

function initMobileMenu() {
  const toggleButton = document.getElementById('mobile-menu-button');
  const mobileMenu = document.getElementById('mobile-menu');

  if (!toggleButton || !mobileMenu) return;

  toggleButton.addEventListener('click', () => {
    const isHidden = mobileMenu.classList.contains('hidden');
    mobileMenu.classList.toggle('hidden');
    toggleButton.setAttribute('aria-expanded', String(!isHidden));
  });

  mobileMenu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      mobileMenu.classList.add('hidden');
      toggleButton.setAttribute('aria-expanded', 'false');
    });
  });
}

// Header sticky

function initStickyHeader() {
  const header = document.querySelector('.site-header');
  if (!header) return;

  const onScroll = () => {
    header.classList.toggle('is-sticky', window.scrollY > 10);
  };
  onScroll();
  window.addEventListener('scroll', onScroll);
}

// Smooth scroll

function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener('click', (event) => {
      const targetId = link.getAttribute('href');
      if (!targetId || targetId === '#') return;

      const targetElement = document.querySelector(targetId);
      if (targetElement) {
        event.preventDefault();
        targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
}

// Contadores animados

function initCounters() {
  const counters = document.querySelectorAll('[data-counter]');
  if (!counters.length) return;

  const animateCounter = (counterElement) => {
    const target = Number(counterElement.getAttribute('data-target'));
    const duration = 1600;
    const startTime = performance.now();

    const update = (currentTime) => {
      const progress = Math.min((currentTime - startTime) / duration, 1);
      counterElement.textContent = `+${Math.floor(progress * target)}`;
      if (progress < 1) {
        requestAnimationFrame(update);
      } else {
        counterElement.textContent = `+${target}`;
      }
    };
    requestAnimationFrame(update);
  };

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.1 }
  );

  counters.forEach((counter) => observer.observe(counter));
}

// Donación - Botón centralizado

function initDonationButtons() {
  document.querySelectorAll('[data-donate]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();

      if (DONANDO_PAYMENT_URL) {
        window.open(DONANDO_PAYMENT_URL, '_blank', 'noopener noreferrer');
      } else {
        // Modal de donación simple
        showDonationModal();
      }
    });
  });
}

function showDonationModal() {
  const modal = document.createElement('div');
  modal.style.cssText = `
    position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999;
    display:flex; align-items:center; justify-content:center; padding:1rem;
  `;
  modal.innerHTML = `
    <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full animate-in" style="animation:modalIn 0.2s ease">
      <h3 class="text-xl font-bold mb-3">Haz tu donación</h3>
      <form id="donation-form" class="space-y-3">
        <div>
          <label class="block text-sm font-medium mb-1">Nombre (opcional)</label>
          <input type="text" id="donor-name" class="form-input w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-[#CC0000] focus:ring-2 focus:ring-red-100" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Email (opcional)</label>
          <input type="email" id="donor-email" class="form-input w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-[#CC0000] focus:ring-2 focus:ring-red-100" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Monto ($CLP)</label>
          <div class="flex gap-2 mb-2">
            ${[2000, 5000, 10000, 20000].map(a =>
              `<button type="button" data-amount="${a}" class="amount-opt flex-1 border border-gray-300 rounded-lg py-2 text-sm font-medium hover:border-[#CC0000] hover:text-[#CC0000] transition-all">$${(a).toLocaleString('es-CL')}</button>`
            ).join('')}
          </div>
          <input type="number" id="donor-amount" min="100" step="100" class="form-input w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-[#CC0000] focus:ring-2 focus:ring-red-100" placeholder="Monto personalizado" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Mensaje (opcional)</label>
          <textarea id="donor-message" rows="2" class="form-input w-full border border-gray-300 rounded-lg px-3 py-2 outline-none focus:border-[#CC0000] focus:ring-2 focus:ring-red-100"></textarea>
        </div>
        <p id="donation-error" class="text-red-600 text-sm hidden"></p>
        <button type="submit" class="w-full bg-[#CC0000] text-white font-bold py-3 rounded-lg hover:brightness-90 transition-all">
          Donar ahora
        </button>
        <button type="button" id="close-donation-modal" class="w-full text-gray-500 text-sm py-2 hover:text-gray-700">Cancelar</button>
      </form>
    </div>
  `;
  document.body.appendChild(modal);

  // Montos predefinidos
  modal.querySelectorAll('.amount-opt').forEach(btn => {
    btn.addEventListener('click', () => {
      modal.querySelectorAll('.amount-opt').forEach(b => b.classList.remove('bg-[#CC0000]', 'text-white', 'border-[#CC0000]'));
      btn.classList.add('bg-[#CC0000]', 'text-white', 'border-[#CC0000]');
      document.getElementById('donor-amount').value = btn.dataset.amount;
    });
  });

  modal.querySelector('#close-donation-modal').addEventListener('click', () => modal.remove());
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

  // Submit
  modal.querySelector('#donation-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const errorEl = document.getElementById('donation-error');
    errorEl.classList.add('hidden');
    const amount = parseFloat(document.getElementById('donor-amount').value);

    if (!amount || amount < 100) {
      errorEl.textContent = 'Ingresa un monto válido (mínimo $100)';
      errorEl.classList.remove('hidden');
      return;
    }

    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Procesando...';

    try {
      const res = await fetch('/api/donations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          donor_name: document.getElementById('donor-name').value,
          donor_email: document.getElementById('donor-email').value,
          amount,
          message: document.getElementById('donor-message').value,
        }),
      });
      const data = await res.json();
      if (data.success && data.data && data.data.payment_url) {
        window.location.href = data.data.payment_url;
      } else {
        throw new Error(data.error || 'Error al registrar la donación.');
      }
    } catch (err) {
      errorEl.textContent = err.message || 'Error al procesar la donación. Intenta de nuevo.';
      errorEl.classList.remove('hidden');
      btn.disabled = false;
      btn.textContent = 'Donar ahora';
    }
  });
}

// Formulario de contacto

function initContactForm() {
  // Buscar formulario de contacto donde esté (index.html, contacto.html)
  const forms = document.querySelectorAll('form');
  let form = null;
  for (const f of forms) {
    if (f.querySelector('#nombre') && f.querySelector('#email') && f.querySelector('#mensaje')) {
      form = f;
      break;
    }
  }
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Enviando...';

    const data = {
      name: form.querySelector('#nombre')?.value || '',
      email: form.querySelector('#email')?.value || '',
      phone: form.querySelector('#telefono')?.value || '',
      subject: form.querySelector('#asunto')?.value || '',
      message: form.querySelector('#mensaje')?.value || '',
    };

    try {
      const res = await fetch('/api/contact', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
      });

      const result = await res.json();

      if (result.success) {
        showToast('Mensaje enviado correctamente. Te contactaremos pronto.', 'success');
        form.reset();
      } else {
        showToast(result.error || 'Error al enviar el mensaje.', 'error');
      }
    } catch {
      showToast('Error de conexión. Intenta de nuevo.', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Enviar mensaje';
    }
  });
}

// Newsletter

function initNewsletterForm() {
  // Buscar formularios de suscripción por data属性 o clase
  const forms = document.querySelectorAll('[data-newsletter]');
  forms.forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const input = form.querySelector('input[type="email"]');
      if (!input) return;

      const btn = form.querySelector('button');
      const originalText = btn?.textContent || 'Suscribirse';

      if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

      try {
        const res = await fetch('/api/subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: input.value }),
        });
        const result = await res.json();

        if (result.success) {
          showToast('¡Gracias por suscribirte!', 'success');
          input.value = '';
        } else {
          showToast(result.error || 'Error al suscribir.', 'error');
        }
      } catch {
        showToast('Error de conexión.', 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = originalText; }
      }
    });
  });
}

// Sistema de Toast

function showToast(message, type = 'success') {
  const existing = document.getElementById('app-toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.id = 'app-toast';
  toast.setAttribute('role', 'status');
  toast.setAttribute('aria-live', 'polite');

  const bgColor = type === 'success' ? '#15803d' : type === 'error' ? '#b91c1c' : '#111111';
  const icon = type === 'success' ? 'fa-circle-check' : type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info';

  toast.style.cssText = `
    position:fixed; bottom:5rem; left:50%; transform:translateX(-50%);
    background:${bgColor}; color:#ffffff;
    padding:0.85rem 1.5rem; border-radius:0.75rem;
    box-shadow:0 8px 24px rgba(0,0,0,0.18);
    z-index:99999; font-size:0.9rem; font-weight:500;
    display:flex; align-items:center; gap:0.6rem;
    opacity:0; transition:opacity 0.25s ease;
    max-width:calc(100vw - 2rem);
  `;
  toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${message}`;

  document.body.appendChild(toast);

  requestAnimationFrame(() => { toast.style.opacity = '1'; });

  setTimeout(() => {
    toast.style.opacity = '0';
    setTimeout(() => toast.remove(), 280);
  }, 4000);
}

// Añadir animación modal
const style = document.createElement('style');
style.textContent = `
  @keyframes modalIn { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
`;
document.head.appendChild(style);

// Noticias públicas dinámicas

function initPublicNews() {
  const container = document.getElementById('news-container');
  if (!container) return;

  loadPublicNews();
}

async function loadPublicNews() {
  const container = document.getElementById('news-container');
  try {
    const res = await fetch('/api/news');
    const json = await res.json();
    if (json.success && json.news && json.news.length > 0) {
      container.innerHTML = json.news.map(n => `
        <article class="bg-[#F5F5F5] rounded-2xl overflow-hidden card-hover flex flex-col h-full border border-gray-100 shadow-sm">
          ${n.image_url ? `<img src="${n.image_url}" alt="${escapeHtml(n.title)}" class="w-full h-48 object-cover" />` : '<div class="w-full h-48 bg-gray-200 flex items-center justify-center text-gray-400"><i class="fa-solid fa-image text-3xl"></i></div>'}
          <div class="p-6 flex-1 flex flex-col justify-between">
            <div>
              <span class="text-xs font-semibold text-[#CC0000] uppercase tracking-wider block mb-2">${escapeHtml(n.category || 'general')}</span>
              <h2 class="text-xl font-bold mb-2 text-gray-800 line-clamp-2">${escapeHtml(n.title)}</h2>
              <p class="text-xs text-gray-400 mb-3">${timeAgoDate(n.published_at)}</p>
              <p class="text-gray-600 text-sm mb-4 line-clamp-3">${escapeHtml(n.excerpt || '')}</p>
            </div>
            <a href="/noticias/${n.slug}" class="text-[#CC0000] font-bold text-sm hover:text-red-700 transition-all flex items-center gap-1 mt-auto self-start">
              Leer más <i class="fa-solid fa-arrow-right text-xs"></i>
            </a>
          </div>
        </article>
      `).join('');
    } else {
      container.innerHTML = '<p class="col-span-full text-center text-gray-500 py-8">No hay noticias publicadas en este momento.</p>';
    }
  } catch (err) {
    console.error('Error loading news:', err);
    container.innerHTML = '<p class="col-span-full text-center text-red-500 py-8">Error de conexión al cargar noticias.</p>';
  }
}

async function showNewsDetailModal(slug) {
  // Modal de carga temporal
  const modal = document.createElement('div');
  modal.style.cssText = `
    position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999;
    display:flex; align-items:center; justify-content:center; padding:1rem;
  `;
  modal.innerHTML = `
    <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full text-center animate-in" style="animation:modalIn 0.2s ease">
      <p class="text-gray-500">Cargando noticia...</p>
    </div>
  `;
  document.body.appendChild(modal);

  try {
    const res = await fetch(`/api/news/${slug}`);
    const json = await res.json();
    if (!json.success || !json.data) {
      throw new Error(json.error || 'Error al obtener la noticia');
    }
    const n = json.data;

    modal.innerHTML = `
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden max-w-2xl w-full max-h-[90vh] flex flex-col animate-in" style="animation:modalIn 0.2s ease">
        <div class="relative flex-shrink-0">
          ${n.image_url ? `<img src="${n.image_url}" alt="" class="w-full h-64 object-cover" />` : '<div class="w-full h-40 bg-gray-100 flex items-center justify-center text-gray-300"><i class="fa-solid fa-image text-4xl"></i></div>'}
          <button id="close-news-modal" class="absolute top-4 right-4 bg-black/60 text-white rounded-full w-10 h-10 flex items-center justify-center hover:bg-black/80 transition-all font-bold text-xl">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto flex-1 text-left">
          <span class="text-xs font-semibold text-[#CC0000] uppercase tracking-wider block mb-2">${escapeHtml(n.category || 'general')}</span>
          <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900 mb-2">${escapeHtml(n.title)}</h2>
          <p class="text-xs text-gray-400 mb-6">${timeAgoDate(n.published_at)}</p>
          <div class="prose max-w-none text-gray-700 leading-relaxed">
            ${n.content}
          </div>
        </div>
      </div>
    `;

    modal.querySelector('#close-news-modal').addEventListener('click', () => modal.remove());
  } catch (err) {
    modal.innerHTML = `
      <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-sm w-full text-center">
        <h3 class="text-xl font-bold text-red-600 mb-2">Error</h3>
        <p class="text-gray-600 mb-4">${err.message}</p>
        <button onclick="this.closest('[style]').remove()" class="bg-[#CC0000] text-white px-6 py-2 rounded-lg font-semibold">Cerrar</button>
      </div>
    `;
  }

  modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
}

function timeAgoDate(dateStr) {
  if (!dateStr) return '';
  // Reemplazar espacio con T para compatibilidad ISO en Safari/Firefox
  const date = new Date(dateStr.replace(' ', 'T') + 'Z');
  return date.toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric' });
}

function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

// Revelación al hacer scroll (Scroll Reveal)

function initScrollReveal() {
  const reveals = document.querySelectorAll('.reveal');
  if (!reveals.length) return;

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed');
        }
      });
    },
    { threshold: 0.05, rootMargin: '0px 0px -40px 0px' }
  );

  reveals.forEach((element) => observer.observe(element));
}
