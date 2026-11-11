// Theme toggle (persisted in localStorage)
const themeToggle = document.getElementById('themeToggle');
if (themeToggle) {
  themeToggle.addEventListener('click', () => {
    document.documentElement.classList.toggle('dark');
    localStorage.setItem('smp-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
  });
}

// Mobile sidebar toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarState = document.getElementById('sidebarState');
if (sidebarToggle && sidebarState) {
  sidebarToggle.addEventListener('click', () => {
    sidebarState.checked = !sidebarState.checked;
  });
}

// Auto-dismiss flash toasts after 5s
document.querySelectorAll('[data-toast]').forEach((el) => {
  setTimeout(() => {
    el.style.transition = 'opacity .4s ease';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 400);
  }, 5000);
});

// Confirm before any element with data-confirm is submitted/clicked
document.querySelectorAll('[data-confirm]').forEach((el) => {
  el.addEventListener('click', (e) => {
    if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) {
      e.preventDefault();
    }
  });
});

// Password show/hide toggle — works on any input wrapped in a
// `.password-field` container next to a `[data-toggle-password]` button.
document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
  btn.addEventListener('click', () => {
    const field = btn.closest('.password-field');
    const input = field ? field.querySelector('input') : null;
    if (!input) return;
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    const eye = btn.querySelector('[data-icon-eye]');
    const eyeOff = btn.querySelector('[data-icon-eye-off]');
    if (eye && eyeOff) {
      eye.classList.toggle('hidden', !showing);
      eyeOff.classList.toggle('hidden', showing);
    }
  });
});
