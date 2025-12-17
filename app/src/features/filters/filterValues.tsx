import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import { IFilterValue, IFilterValueResponseMetadata } from "@/interface/filters/filterValueSearch"
import { SelectedFilter } from "@/interface/filters/slice"
import { Check, Loader2, Minus, Plus } from "lucide-react"

interface SearchResultsProps {
  results?: IFilterValue[]
  metadata?: IFilterValueResponseMetadata
  isLoading: boolean
  error: string | null
  selectedItems: SelectedFilter[]
  onInclude: (item: IFilterValue) => void
  onExclude: (item: IFilterValue) => void
  onRemove: (item: IFilterValue) => void
  canExclude: boolean
}

const FilterSearchValueResults = ({
  results = [],
  metadata,
  isLoading,
  error,
  selectedItems,
  onInclude,
  onExclude,
  canExclude,
  onRemove
}: SearchResultsProps) => {
  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <Loader2 className="size-6 animate-spin text-gray-400 dark:text-gray-600" />
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex items-center justify-center py-8">
        <p className="text-sm text-gray-500 dark:text-gray-400">{error}</p>
      </div>
    )
  }

  if (results.length === 0) {
    return (
      <div className="flex items-center justify-center py-8">
        <p className="text-sm text-gray-500 dark:text-gray-400">No results found</p>
      </div>
    )
  }

  return (
    <TooltipProvider>
      <div className="mt-2 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
        <div className="max-h-64 overflow-y-auto">
          {results.map((item) => {
            const existingSelection = selectedItems.find((selected) => selected.id === item.id)
            const isIncluded = existingSelection?.type === "include"
            const isExcluded = existingSelection?.type === "exclude"

            return (
              <div
                key={item.id}
                className="group flex items-center justify-between bg-white px-3 py-1 transition-colors hover:bg-gray-50 dark:bg-gray-800/50 dark:hover:bg-gray-800/50"
              >
                <div className="min-w-0 flex-1 pr-4">
                  <p className="text-sm text-gray-950 dark:text-gray-100" title={item.name}>
                    {item.name}
                  </p>
                </div>

                <div className="flex items-center gap-2">
                  {canExclude ? (
                    <>
                      <Tooltip>
                        <TooltipTrigger asChild>
                          <button
                            onClick={() => (isIncluded ? onRemove(item) : onInclude(item))}
                            className={`inline-flex size-8 items-center justify-center rounded-full transition-colors
                              ${
                                isIncluded
                                  ? "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                                  : "text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                              }`}
                          >
                            {isIncluded ? <Check className="size-4" /> : <Plus className="size-4" />}
                          </button>
                        </TooltipTrigger>
                        <TooltipContent>
                          <p>{isIncluded ? "Included" : "Include"}</p>
                        </TooltipContent>
                      </Tooltip>

                      <Tooltip>
                        <TooltipTrigger asChild>
                          <button
                            onClick={() => (isExcluded ? onRemove(item) : onExclude(item))}
                            className={`inline-flex size-8 items-center justify-center rounded-full transition-colors
                              ${
                                isExcluded
                                  ? "bg-red-50 text-red-700 dark:bg-red-500/20 dark:text-red-300"
                                  : "text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                              }`}
                          >
                            {isExcluded ? <Check className="size-4" /> : <Minus className="size-4" />}
                          </button>
                        </TooltipTrigger>
                        <TooltipContent>
                          <p>{isExcluded ? "Excluded" : "Exclude"}</p>
                        </TooltipContent>
                      </Tooltip>
                    </>
                  ) : (
                    <button
                      onClick={() => (isIncluded ? onRemove(item) : onInclude(item))}
                      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors
                      ${
                        existingSelection
                          ? "bg-blue-50 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300"
                          : "text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                      }`}
                    >
                      {existingSelection ? <Check className="size-3.5" /> : <Plus className="size-3.5" />}
                      {existingSelection ? "Selected" : "Select"}
                    </button>
                  )}
                </div>
              </div>
            )
          })}
        </div>

        {!!metadata && metadata.total_pages > 1 && (
          <div className="border-t border-gray-200 bg-gray-50 px-4 py-2 dark:border-gray-800 dark:bg-gray-800/50">
            <p className="text-center text-xs text-gray-500 dark:text-gray-400">
              Showing {metadata.returned_count} of {metadata.total_count} results
            </p>
          </div>
        )}
      </div>
    </TooltipProvider>
  )
}

export default FilterSearchValueResults
