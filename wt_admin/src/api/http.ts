import axios, { type AxiosInstance, type AxiosRequestConfig } from 'axios'
import { apiBase } from './config'
import { camelizeKeys, mapPage, snakeizeParams } from './mapping'
import { clearToken, getToken, notifyUnauthorized } from './token'
import type { Paginated } from './types'

// Shared axios instance. Attaches the bearer token, and on 401 wipes the token and
// notifies the auth layer (which routes to the login screen).
const instance: AxiosInstance = axios.create({
  baseURL: apiBase,
  headers: { Accept: 'application/json' },
})

instance.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers = config.headers ?? {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

instance.interceptors.response.use(
  (r) => r,
  (error) => {
    if (error?.response?.status === 401) {
      clearToken()
      notifyUnauthorized()
    }
    return Promise.reject(normalizeError(error))
  },
)

export interface ApiError extends Error {
  status?: number
  code?: string
}

// Turn an axios error into a plain message. The admin API uses RFC 7807
// (application/problem+json with a machine `code`) except input validation, which
// uses Laravel's 422 { message, errors } shape.
function normalizeError(error: unknown): ApiError {
  const err = new Error() as ApiError
  if (axios.isAxiosError(error)) {
    err.status = error.response?.status
    const data = error.response?.data as
      | { title?: string; detail?: string; code?: string; message?: string; errors?: Record<string, string[]> }
      | undefined
    if (data?.errors) {
      // Laravel 422: surface the first field error.
      const first = Object.values(data.errors)[0]?.[0]
      err.message = first ?? data.message ?? 'Ошибка валидации'
    } else {
      err.code = data?.code
      err.message = data?.detail ?? data?.title ?? data?.message ?? error.message ?? 'Ошибка сети'
    }
  } else {
    err.message = 'Неизвестная ошибка'
  }
  return err
}

// Single-object GET: camelize the response body.
export async function httpGet<T>(path: string, params?: object): Promise<T> {
  const res = await instance.get(path, { params: cleanParams(snakeizeParams(params)) })
  return camelizeKeys<T>(res.data)
}

// Paginated GET: map the { data, meta } envelope and derive totalPages.
export async function httpGetPage<T>(path: string, params?: object): Promise<Paginated<T>> {
  const res = await instance.get(path, { params: cleanParams(snakeizeParams(params)) })
  return mapPage<T>(res.data)
}

export async function httpPost<T>(path: string, body?: unknown, config?: AxiosRequestConfig): Promise<T> {
  const res = await instance.post(path, body, config)
  return camelizeKeys<T>(res.data)
}

export async function httpPut<T>(path: string, body?: unknown, config?: AxiosRequestConfig): Promise<T> {
  const res = await instance.put(path, body, config)
  return camelizeKeys<T>(res.data)
}

// Drop undefined/empty params so they never appear as `?x=` in the URL.
function cleanParams(params?: Record<string, unknown>): Record<string, unknown> | undefined {
  if (!params) return undefined
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') out[k] = v
  }
  return out
}
