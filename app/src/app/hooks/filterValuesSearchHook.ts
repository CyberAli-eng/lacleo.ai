import { debounce } from "lodash"
import { useCallback, useRef, useState, useMemo } from "react"
import { useLazySearchFilterValuesQuery, useLazyCompaniesSuggestQuery } from "@/features/filters/slice/apiSlice"
import { IFilter } from "@/interface/filters/filterGroup"
import { IFilterSearchState } from "@/interface/filters/filterValueSearch"
import { useAppSelector } from "./reduxHooks"
import { selectActiveFilters } from "@/features/filters/slice/filterSlice"
import { buildFilterDSL } from "@/features/filters/adapter/querySerializer"

export const useFilterSearch = () => {
  const [searchState, setSearchState] = useState<IFilterSearchState>({
    results: {},
    metadata: {},
    isLoading: {},
    error: {}
  })

  const activeFilters = useAppSelector(selectActiveFilters)

  const [triggerSearch] = useLazySearchFilterValuesQuery()
  const [triggerCompaniesSuggest] = useLazyCompaniesSuggestQuery()

  // Use a ref to keep track of the latest search implementation so debounce can call it
  const searchImplementationRef = useRef<(filter: IFilter, query: string, page: string) => Promise<void>>()

  // Actual search logic
  searchImplementationRef.current = async (filter: IFilter, query: string, page: string) => {
    // Handle search implementation

    // Guard: Min length (unless it's a page change or explicit empty clear? No, user said min length < 2 return)
    if (filter.is_searchable && query.length < 2) {
      // Clear results if query is too short (matches user request)
      setSearchState((prev) => ({
        ...prev,
        results: { ...prev.results, [filter.id]: [] },
        metadata: { ...prev.metadata, [filter.id]: undefined },
        error: { ...prev.error, [filter.id]: null }
      }))
      return
    }

    const filterId = filter.id
    setSearchState((prev) => ({
      ...prev,
      isLoading: { ...prev.isLoading, [filterId]: true },
      error: { ...prev.error, [filterId]: null }
    }))

    try {
      let response: import("@/interface/filters/filterValueSearch").IFilterResponse<import("@/interface/filters/filterValueSearch").IFilterValue>
      const isCompanyDomain = filter.id === "company_domain"

      if (isCompanyDomain && query) {
        const suggest = await triggerCompaniesSuggest({ q: query }).unwrap()
        const mapped = (suggest.data || []).map((s) => ({ id: String(s.name ?? s.domain ?? ""), name: String(s.name ?? s.domain ?? "") }))
        response = {
          data: mapped,
          metadata: {
            total_count: mapped.length,
            returned_count: mapped.length,
            page: Number(page),
            per_page: 10,
            total_pages: 1
          }
        }
      } else {
        // Build DSL from current active filters
        const dsl = buildFilterDSL(activeFilters)

        response = await triggerSearch({
          filter: filterId,
          page,
          count: "10",
          ...(query && { q: query }),
          filter_dsl: JSON.stringify(dsl) // Stringify for GET request compatibility
        }).unwrap()
      }

      setSearchState((prev) => ({
        ...prev,
        results: {
          ...prev.results,
          [filterId]: response.data
        },
        metadata: {
          ...prev.metadata,
          [filterId]: response.metadata
        },
        isLoading: { ...prev.isLoading, [filterId]: false }
      }))
    } catch (error) {
      setSearchState((prev) => ({
        ...prev,
        isLoading: { ...prev.isLoading, [filterId]: false },
        results: { ...prev.results, [filterId]: [] },
        error: { ...prev.error, [filterId]: null } // Explicitly no error
      }))
    }
  }

  // Debounced wrapper
  const debouncedSearch = useMemo(
    () =>
      debounce((filter: IFilter, query: string, page: string) => {
        if (searchImplementationRef.current) {
          searchImplementationRef.current(filter, query, page)
        }
      }, 300),
    []
  )

  const handleSearch = useCallback(
    (filter: IFilter, query: string = "", page: string = "1") => {
      // Call debounced function
      // Note: This returns undefined immediately, so caller cannot await it.
      // This is acceptable for "type-ahead" search.
      debouncedSearch(filter, query, page)
      return Promise.resolve()
    },
    [debouncedSearch]
  )

  const clearSearchResults = useCallback((filterId: string) => {
    setSearchState((prev) => ({
      ...prev,
      results: { ...prev.results, [filterId]: [] },
      metadata: { ...prev.metadata, [filterId]: undefined },
      error: { ...prev.error, [filterId]: null }
    }))
  }, [])

  return {
    searchState,
    handleSearch,
    clearSearchResults
  }
}
