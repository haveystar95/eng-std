// Runtime configuration for local `vite dev` / `vite preview`.
// In Docker this file is regenerated from the API_BASE env var at container start
// (see docker-entrypoint.sh). Leaving apiBase empty + useMocks true makes the SPA use
// its built-in mock adapter so it runs standalone. To hit the live backend in dev,
// run `VITE_API_BASE=http://localhost:8001/admin/api` OR set useMocks:false here (the
// vite proxy forwards /admin/api → :8001).
window.__WT_ADMIN__ = {
  apiBase: '',
  useMocks: true,
}
