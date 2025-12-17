export interface IFilterValueResponseMetadata {
  total_count: number
  returned_count: number
  page: number
  per_page: number
  total_pages: number
}

export type IFilterValue = {
  id: string
  name: string
}

export interface IFilterResponse<T> {
  data: T[]
  metadata: IFilterValueResponseMetadata
}

export interface IFilterSearchState {
  results: Record<string, IFilterValue[]>
  metadata: Record<string, IFilterValueResponseMetadata | undefined>
  isLoading: Record<string, boolean>
  error: Record<string, string | null>
}

export interface IFilterSearchParams {
  filter: string
  page: string
  count: string
  q?: string
}

export type SortDirection = "asc" | "desc"

export interface SortConfig {
  field: string
  direction: SortDirection
}

export interface PaginationParams {
  page?: number
  count?: number
  sort?: string
}
