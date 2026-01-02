import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import { selectActiveFilters, resetFilters } from "@/features/filters/slice/filterSlice" // activeFilters is the single truth now
import { executeSearch, setDsl, setEntity } from "@/features/searchExecution/slice/searchExecutionSlice"
import { buildFilterDSL } from "@/features/filters/adapter/querySerializer"

export const ActiveFilterChips = ({ entity }: { entity: "contact" | "company" }) => {
  const dispatch = useAppDispatch()
  const activeFilters = useAppSelector(selectActiveFilters)
  const filters = activeFilters[entity] || {}

  const chipEntries = Object.entries(filters).filter(([_, f]) => {
    return (
      (f.include?.length || 0) > 0 || (f.exclude?.length || 0) > 0 || (f.range && f.range.min !== undefined) || (f.presence && f.presence !== "any")
    )
  })

  // If no filters are active, do not render anything
  if (chipEntries.length === 0) return null

  const handleClearAll = () => {
    // 1. Reset filters in store
    dispatch(resetFilters())

    // 2. IMMEDIATE EXECUTION (One user action = One API call)
    // We must manually triggering search here because this is a User Interaction Component
    // and SearchLayout is not allowed to coordinate this.
    // We build an empty DSL for the entity to execute a "Clear" search.
    const emptyDsl = buildFilterDSL({ contact: {}, company: {} })

    // Ensure we are setting the dsl for the correct entity logic if needed,
    // but typically executeSearch uses the searchExecution slice state.
    // We update the DSL in searchExecution slice first.
    dispatch(setDsl(emptyDsl as unknown as Record<string, unknown>))
    dispatch(executeSearch())
  }

  return (
    <div className="mb-4 flex flex-wrap gap-2 px-6">
      {chipEntries.map(([key, filter]) => (
        <div key={key} className="flex flex-wrap gap-2">
          {filter.include?.map((val) => (
            <span
              key={val}
              className="inline-flex items-center gap-1 rounded bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10"
            >
              {val}
            </span>
          ))}
          {filter.exclude?.map((val) => (
            <span
              key={val}
              className="inline-flex items-center gap-1 rounded bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-700/10"
            >
              Not: {val}
            </span>
          ))}
          {!!filter.range && (
            <span className="inline-flex items-center gap-1 rounded bg-orange-50 px-2 py-1 text-xs font-medium text-orange-700 ring-1 ring-inset ring-orange-700/10">
              {key}: {filter.range.min}-{filter.range.max}
            </span>
          )}
          {!!filter.presence && filter.presence !== "any" && (
            <span className="inline-flex items-center gap-1 rounded bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10">
              {key}: {filter.presence}
            </span>
          )}
        </div>
      ))}
      <button onClick={handleClearAll} className="text-xs text-gray-500 underline hover:text-gray-700">
        Clear all
      </button>
    </div>
  )
}
