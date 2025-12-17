import { AI } from "@/app/constants/apiConstants"
import { aiBaseQuery, apiSlice } from "@/app/redux/apiSlice"
import { CompanyAttributes, ContactAttributes } from "@/interface/searchTable/search"
import { FetchBaseQueryError } from "@reduxjs/toolkit/query"

type ExtractedFilter = {
  id: string
  name: string
  type: string
  input_type: "text" | "select" | "multi_select" | "boolean" | "hierarchical"
  is_searchable: boolean
  allows_exclusion: boolean
  supports_value_lookup: boolean
  filter_type: "company" | "contact"
  value: string | number | null
}

type ExtractedFilterGroup = {
  group_id: number
  group_name: string
  group_description: string
  filters: ExtractedFilter[]
}

type ExtractFiltersApiResponse = {
  success: boolean
  data: ExtractedFilterGroup[]
  error_message: string | null
}

export const aiSearchApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    extractFilters: builder.mutation<ExtractedFilterGroup[], { query: string; variables?: Record<string, unknown> }>({
      queryFn: async (body, queryApi, extraOptions) => {
        const result = await aiBaseQuery(
          {
            url: AI.EXTRACT_FILTERS,
            method: "POST",
            body: { query: body.query, variables: body.variables ?? {} }
          },
          queryApi,
          extraOptions
        )

        if ("error" in result && result.error) {
          return { error: result.error }
        }

        const payloadUnknown: unknown = result.data

        // Backend canonical format: { filters: [{ field, operator, value }], model }
        if (payloadUnknown && typeof payloadUnknown === "object" && Array.isArray((payloadUnknown as { filters?: unknown[] }).filters)) {
          const payload = payloadUnknown as {
            filters: Array<{ field: string; operator?: string; value?: string | number | null }>
          }
          const DISPLAY_NAME_MAP: Record<string, string> = {
            company: "Company Name",
            "company.domain": "Company Domain",
            title: "Job Title",
            departments: "Department",
            "location.country": "Company Location",
            technologies: "Technologies"
          }

          const FILTER_TYPE_MAP: Record<string, "company" | "contact"> = {
            company: "company",
            "company.domain": "company",
            "location.country": "company",
            title: "contact",
            departments: "contact",
            technologies: "company"
          }

          const group: ExtractedFilterGroup = {
            group_id: 0,
            group_name: "AI Filters",
            group_description: "Derived from AI prompt",
            filters: payload.filters.map((f) => {
              const field: string = String(f.field ?? "")
              return {
                id: field || String(DISPLAY_NAME_MAP[field] || field),
                name: DISPLAY_NAME_MAP[field] || field,
                type: "string",
                input_type: "multi_select",
                is_searchable: true,
                allows_exclusion: true,
                supports_value_lookup: false,
                filter_type: FILTER_TYPE_MAP[field] || "company",
                value: f.value !== undefined ? f.value : null
              }
            })
          }

          return { data: [group] }
        }

        // Legacy format: { success, data }
        const legacy = payloadUnknown as ExtractFiltersApiResponse
        if (legacy && Array.isArray(legacy.data)) {
          return { data: legacy.data }
        }

        return {
          error: {
            status: 400,
            data: "Failed to extract filters"
          } as FetchBaseQueryError
        }
      }
    })
  })
})

export const { useExtractFiltersMutation } = aiSearchApiSlice
