# ── Stage 1: build the SPA ──────────────────────────────────────────────
FROM node:20-alpine AS build
WORKDIR /app

# Install deps against the lockfile first (better layer caching).
COPY package.json package-lock.json ./
RUN npm ci

# Build. VITE_API_BASE can be baked at build time, but the runtime config.js
# (written by the entrypoint) takes precedence, so this is usually left empty.
COPY . .
ARG VITE_API_BASE=
ENV VITE_API_BASE=${VITE_API_BASE}
RUN npm run build

# ── Stage 2: serve static files with nginx + reverse-proxy the admin API ──
FROM nginx:1.27-alpine AS serve
# nginx's entrypoint runs envsubst over *.template, substituting ${BACKEND_ORIGIN}.
COPY nginx.conf /etc/nginx/templates/default.conf.template
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker-entrypoint.sh /docker-entrypoint.d/99-wt-admin-config.sh
RUN chmod +x /docker-entrypoint.d/99-wt-admin-config.sh

# BACKEND_ORIGIN — where nginx proxies /admin/api (host.docker.internal for a plain
#   `docker run`; compose overrides it to the internal service http://wt_app:8000).
# API_BASE empty ⇒ the SPA calls /admin/api same-origin (proxied here) — no CORS.
# USE_MOCKS false ⇒ live by default; set true to run standalone on demo data.
ENV BACKEND_ORIGIN=http://host.docker.internal:8001
ENV API_BASE=
ENV USE_MOCKS=false
EXPOSE 80
