import React from "react"
import { BaseResponseItem } from "./search"

type DataTableColumnWithTypedRender<T, K extends keyof T> = {
  title: string
  field: K
  width?: string
  sortable?: boolean
  render?: (value: T[K], row: T) => React.ReactNode
}

export type DataTableColumn<T> = {
  [K in keyof T]: DataTableColumnWithTypedRender<T, K>
}[keyof T]

interface PaginationState {
  page: number
  count: number
  total: number
  lastPage: number
}

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[]
  data: BaseResponseItem<T>[]
  loading: boolean
  fetching: boolean
  sortableFields?: Array<string>
  onSort?: (value: string[]) => void
  sortSelected?: string[]
  searchPlaceholder?: string
  onSearch?: (value: string) => void
  onSearchExecute?: () => void
  searchValue?: string
  onOpenEditColumns?: () => void
  entityType?: "contact" | "company"
  showCheckbox?: boolean
  selectedItems?: string[]
  onItemSelect?: (itemId: string) => void
  onSelectAll?: (checked: boolean) => void
  onRowClick?: (row: T) => void
  pagination?: {
    currentPage: number
    lastPage: number
    onPageChange: (page: number) => void
  }
}
