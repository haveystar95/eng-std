/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE?: string
  readonly VITE_USE_MOCKS?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}

interface Window {
  __WT_ADMIN__?: {
    apiBase?: string
    useMocks?: boolean
  }
}
