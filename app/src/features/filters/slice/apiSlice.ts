import { FILTER, transformErrorResponse } from "@/app/constants/apiConstants"
import { apiSlice } from "@/app/redux/apiSlice"
import { IFilterGroup } from "@/interface/filters/filterGroup"
import { IFilterResponse, IFilterSearchParams, IFilterValue } from "@/interface/filters/filterValueSearch"

const filtersApiSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getFilters: builder.query<IFilterGroup[], void>({
      query: () => ({ url: FILTER.GET_ALL_FILTERS }),
      transformResponse: (response: { data: IFilterGroup[] }) => response.data,
      transformErrorResponse
    }),
    searchFilterValues: builder.query<IFilterResponse<IFilterValue>, IFilterSearchParams>({
      query: (params) => ({
        url: FILTER.GET_FILTER_VALUES,
        params
      })
    }),
    companiesSuggest: builder.query<
      { data: Array<{ id: string | null; name: string | null; domain: string | null; employee_count: number | null }> },
      { q: string }
    >({
      query: ({ q }) => ({ url: "/filters/companies/suggest", params: { q } })
    }),
    companiesExistence: builder.query<{ data: { found_names: string[]; found_domains: string[] } }, { names?: string[]; domains?: string[] }>({
      query: ({ names = [], domains = [] }) => ({ url: "/filters/companies/existence", params: { names, domains } })
    }),
    checkCompaniesExistence: builder.mutation<{ data: { found_names: string[]; found_domains: string[] } }, { names?: string[]; domains?: string[] }>(
      {
        query: ({ names = [], domains = [] }) => ({ url: "/filters/companies/existence", method: "POST", body: { names, domains } })
      }
    ),
    bulkApplyFilters: builder.mutation<
      { data: { applied: string[]; skipped: string[]; type: "name" | "domain"; searchContext: "contacts" | "companies" } },
      { type: "name" | "domain"; values: string[]; searchContext: "contacts" | "companies" }
    >({
      query: ({ type, values, searchContext }) => ({ url: "/filters/bulk-apply", method: "POST", body: { type, values, searchContext } })
    })
  })
})

export const {
  useGetFiltersQuery,
  useLazySearchFilterValuesQuery,
  useLazyCompaniesSuggestQuery,
  useLazyCompaniesExistenceQuery,
  useCheckCompaniesExistenceMutation,
  useBulkApplyFiltersMutation
} = filtersApiSlice
