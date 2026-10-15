import { request } from './http.js';
import './starfield.js';
const homeUrl = document.querySelector('meta[name="home-url"]').content;

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
    await request('login', { method: 'POST', body: { username: form.elements.username.value, password: form.elements.password.value, remember_me: form.elements.remember_me.checked } });
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
  try { await request('logout', { method: 'POST' }); window.location.assign(homeUrl); }
  catch (failure) {
    const message = document.querySelector('#global-message');
    message.textContent = failure.message || 'No se pudo cerrar la sesión.';
    message.hidden = false;
    button.disabled = false;
  }
});
