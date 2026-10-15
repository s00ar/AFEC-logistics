import { request } from './http.js';

function connect(form, submit) {
  let busy = false;
  let key = null;
  form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (busy || !form.reportValidity()) return;
    const error = form.querySelector('[data-form-error]');
    const success = form.querySelector('[data-form-success]');
    const button = form.querySelector('[type="submit"]');
    error.hidden = true; success.hidden = true;
    if (form.id === 'own-password-form' && form.elements.new_password.value !== form.elements.confirmation.value) {
      error.textContent = 'Las contraseñas nuevas no coinciden.'; error.hidden = false; return;
    }
    busy = true; button.disabled = true;
    key ??= crypto.randomUUID();
    try {
      const message = await submit(form, key);
      // Credentials live only in this form/request, never in browser recovery storage.
      form.reset(); key = null;
      success.textContent = message; success.hidden = false;
    } catch (failure) {
      if (!failure.uncertain) key = null;
      error.textContent = failure.message + (failure.uncertain && form.id === 'own-password-form' ? ' Si la respuesta se perdió, comprobá el acceso con la contraseña nueva antes de repetir el cambio.' : '');
      error.hidden = false;
    } finally { busy = false; button.disabled = false; }
  });
}

connect(document.querySelector('#password-help-form'), async form => {
  const result = await request('password/help', {method:'POST', body:{username:form.elements.username.value}});
  return result.data.message;
});
connect(document.querySelector('#own-password-form'), async (form, key) => {
  await request('account/password', {method:'POST', key, body:{current_password:form.elements.current_password.value, new_password:form.elements.new_password.value, confirmation:form.elements.confirmation.value}});
  return 'Tu contraseña se actualizó. Las otras sesiones de tu cuenta ya no tienen acceso.';
});
for (const form of document.querySelectorAll('.close-password-help-form')) {
  connect(form, async (entry, key) => {
    await request('admin/password-help/close', {method:'POST', key, query:{id:entry.dataset.userId}, body:{request_id:entry.dataset.requestId, reason:entry.elements.reason.value}});
    window.location.reload();
    return 'Solicitud atendida.';
  });
}
