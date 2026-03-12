// pnomina - JavaScript utilitarios

// Formatear número como COP
function formatCOP(valor) {
  return '$ ' + new Intl.NumberFormat('es-CO').format(valor) + ' COP';
}

// Confirmar antes de acción peligrosa
function confirmar(msg) {
  return confirm(msg || '¿Estás seguro de realizar esta acción?');
}

// Toast notification
function showToast(msg, tipo = 'success') {
  const existing = document.getElementById('toast-pnomina');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.id = 'toast-pnomina';
  toast.style.cssText = `
    position: fixed; bottom: 24px; right: 24px;
    background: ${tipo === 'success' ? 'var(--accent-green)' : tipo === 'error' ? 'var(--accent-red)' : 'var(--accent-blue)'};
    color: #fff; padding: 12px 20px; border-radius: 8px;
    font-size: 13px; font-weight: 600; z-index: 9999;
    box-shadow: 0 8px 24px rgba(0,0,0,0.4);
    animation: slideInToast 0.3s ease;
  `;
  toast.textContent = msg;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3500);
}

// Cerrar modal al click fuera
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('active');
  }
});

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
  }
});

// Animación CSS para toasts
const style = document.createElement('style');
style.textContent = `
  @keyframes slideInToast {
    from { transform: translateX(100px); opacity: 0; }
    to   { transform: translateX(0); opacity: 1; }
  }
`;
document.head.appendChild(style);
