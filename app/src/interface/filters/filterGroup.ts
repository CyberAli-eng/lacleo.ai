export type IFilter = {
  id: string
  name: string
  filter_type: "company" | "contact"
  is_searchable: boolean
  allows_exclusion: boolean
  supports_value_lookup: boolean
  input_type: "text" | "select" | "multi_select" | "toggle" | "range" | "hierarchical"
}

export type IFilterGroup = {
  group_id: string
  group_name: string
  group_description: string
  filters: IFilter[]
}
