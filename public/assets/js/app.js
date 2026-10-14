'use strict';

const apiUrl = document.querySelector('meta[name="api-url"]').content;
const homeUrl = document.querySelector('meta[name="home-url"]').content;
let csrfToken = document.querySelector('meta[name="csrf-token"]').content;

async function send(route, payload = {}) {
  const response = await fetch(`${apiUrl}?route=${encodeURIComponent(route)}`, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
    body: JSON.stringify(payload),
  });
  let result;
  try { result = await response.json(); }
  catch { throw new Error('No se pudo leer la respuesta. Intentá de nuevo.'); }
  if (!response.ok) throw new Error(result.error?.message || 'No se pudo completar la operación.');
  if (result.data?.csrf_token) csrfToken = result.data.csrf_token;
  return result.data;
}

document.querySelector('#login-form')?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = event.currentTarget;
  if (!form.reportValidity()) return;
  const button = document.querySelector('#login-submit');
  if (button.disabled) return;
  const error = document.querySelector('#login-error');
  error.hidden = true;
  button.disabled = true;
  button.textContent = 'Ingresando…';
  try {
    await send('login', { username: form.elements.username.value, password: form.elements.password.value });
    window.location.assign(homeUrl);
  } catch (failure) {
    error.textContent = failure.message || 'No hay conexión. Intentá de nuevo.';
    error.hidden = false;
    button.disabled = false;
    button.textContent = 'Iniciar sesión →';
  }
});

document.querySelector('#toggle-password')?.addEventListener('click', (event) => {
  const password = document.querySelector('#password');
  const show = password.type === 'password';
  password.type = show ? 'text' : 'password';
  event.currentTarget.textContent = show ? 'Ocultar' : 'Mostrar';
  event.currentTarget.setAttribute('aria-pressed', String(show));
});

document.querySelector('#logout')?.addEventListener('click', async (event) => {
  const button = event.currentTarget;
  button.disabled = true;
  try { await send('logout'); window.location.assign(homeUrl); }
  catch (failure) {
    const message = document.querySelector('#global-message');
    message.textContent = failure.message || 'No se pudo cerrar la sesión.';
    message.hidden = false;
    button.disabled = false;
  }
});
