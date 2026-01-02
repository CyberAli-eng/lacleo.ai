import { PaginationParams } from "@/interface/filters/filterValueSearch"

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value)
}

export function encodeURIComponentStrict(str: string): string {
  return encodeURIComponent(str).replace(/[!'()*\-._~]/g, (c) => "%" + c.charCodeAt(0).toString(16).toUpperCase())
}

export function objectToVoyagerStringFormat(obj: Record<string, unknown>): string {
  const convert = (val: unknown): string => {
    if (Array.isArray(val)) {
      return `List(${val.map(convert).join(",")})`
    }
    if (isPlainObject(val)) {
      return objectToVoyagerStringFormat(val)
    }
    return encodeURIComponentStrict(String(val))
  }

  return `(${Object.entries(obj)
    .filter(([_, v]) => v !== undefined && v !== null)
    .map(([k, v]) => `${k}:${convert(v)}`)
    .join(",")})`
}

export function buildSearchUrl(params: PaginationParams = {}, queryParams: Record<string, unknown> = {}): string {
  const validatedParams = { ...params }

  // Validate and adjust pagination
  if (validatedParams.page != null) {
    validatedParams.page = Math.max(1, Math.min(100, parseInt(String(validatedParams.page), 10)))
  }

  if (validatedParams.count != null) {
    validatedParams.count = Math.max(10, Math.min(100, parseInt(String(validatedParams.count), 10)))
  }

  const queryParts = [
    ...Object.entries(validatedParams).map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`),
    ...(Object.keys(queryParams).length ? [`query=${objectToVoyagerStringFormat(queryParams)}`] : [])
  ]

  return queryParts.length ? `?${queryParts.join("&")}` : ""
}
