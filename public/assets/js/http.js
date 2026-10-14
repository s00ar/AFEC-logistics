export class ApiError extends Error {
  constructor(message, status = 0, code = 'network') {
    super(message);
    this.status = status;
    this.code = code;
    this.uncertain = status === 0 || status >= 500 || code === 'invalid_response' || code === 'idempotency_conflict';
  }
}

export async function request(route, { method = 'GET', query = {}, body, key, timeout = 20000 } = {}) {
  const url = new URL(document.querySelector('meta[name="api-url"]').content, window.location.href);
  url.searchParams.set('route', route);
  for (const [name, value] of Object.entries(query)) url.searchParams.set(name, String(value));
  const headers = {};
  if (method !== 'GET') {
    headers['Content-Type'] = 'application/json';
    headers['X-CSRF-Token'] = document.querySelector('meta[name="csrf-token"]').content;
  }
  if (key) headers['Idempotency-Key'] = key;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeout);
  try {
    const response = await fetch(url, {
      method, credentials: 'same-origin', headers,
      body: method === 'GET' ? undefined : JSON.stringify(body ?? {}), signal: controller.signal,
    });
    let result;
    try { result = await response.json(); }
    catch { throw new ApiError('No se pudo confirmar la respuesta del servidor.', response.status, 'invalid_response'); }
    if (!response.ok) throw new ApiError(result.error?.message || 'No se pudo completar la operación.', response.status, result.error?.code);
    if (!Object.hasOwn(result, 'data')) throw new ApiError('Respuesta incompleta del servidor.', response.status, 'invalid_response');
    if (result.data?.csrf_token) document.querySelector('meta[name="csrf-token"]').content = result.data.csrf_token;
    return result;
  } catch (failure) {
    if (failure instanceof ApiError) throw failure;
    throw new ApiError('No se pudo confirmar la operación por un problema de conexión.');
  } finally { clearTimeout(timer); }
}
