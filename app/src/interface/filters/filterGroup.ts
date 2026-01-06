export type IFilter = {
  id: string
  name: string
  filter_type: "company" | "contact"
  is_searchable: boolean
  allows_exclusion: boolean
  supports_value_lookup: boolean
  input_type: "text" | "select" | "multi_select" | "toggle" | "range" | "hierarchical" | "checkbox_list"
  type?: string // Underlying data type from registry (e.g. 'date', 'text', 'keyword')
  preloaded_values?: Array<{ id: string; name: string; count: number | null }>
  hint?: string
}

export type IFilterGroup = {
  group_id: string
  group_name: string
  group_description: string
  filters: IFilter[]
}
