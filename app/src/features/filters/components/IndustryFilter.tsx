import React, { useState } from "react"
import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import {
  addSelectedItem,
  removeSelectedItem,
  selectSelectedItems,
  selectCompanyFilters,
  setFilterPresence,
  setFilterMode,
  setFilterFields
} from "@/features/filters/slice/filterSlice"
import { useFilterSearch } from "@/app/hooks/filterValuesSearchHook"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import Checkbox from "@/components/ui/checkbox"
import { Search, Loader2, Plus } from "lucide-react"
import { IFilter } from "@/interface/filters/filterGroup"

export const IndustryFilter = () => {
  const dispatch = useAppDispatch()

  // --- Industry Logic ---
  const industrySectionId = "company_industries"
  const industryFilterKey = "industries"

  const [industrySearchTerm, setIndustrySearchTerm] = useState("")
  const [industryMode, setIndustryMode] = useState<"include" | "exclude">("include")

  const selectedIndustries = useAppSelector(selectSelectedItems)[industrySectionId] || []
  const activeCompanyFilters = useAppSelector(selectCompanyFilters)
  const presence = activeCompanyFilters[industryFilterKey]?.presence || "any"

  const { searchState, handleSearch } = useFilterSearch()
  const industryResults = searchState.results["industry"]
  const isIndustryLoading = searchState.isLoading["industry"]

  const handleIndustrySearch = (e: React.ChangeEvent<HTMLInputElement>) => {
    const val = e.target.value
    setIndustrySearchTerm(val)
    if (val.length > 1) {
      const filter: IFilter = {
        id: "industry",
        name: "Industry",
        filter_type: "company",
        is_searchable: true,
        allows_exclusion: true,
        supports_value_lookup: true,
        input_type: "multi_select"
      }
      handleSearch(filter, val)
    }
  }

  const toggleIndustry = (item: { id: string; name: string }) => {
    const itemId = `${industrySectionId}_${item.name}`
    const existing = selectedIndustries.find((i) => i.id === itemId)

    if (existing) {
      dispatch(removeSelectedItem({ sectionId: industrySectionId, itemId: existing.id, isCompanyFilter: true }))
    } else {
      dispatch(
        addSelectedItem({
          sectionId: industrySectionId,
          item: { id: itemId, name: item.name, type: industryMode },
          isCompanyFilter: true
        })
      )
    }
  }

  const handlePresenceChange = (val: "any" | "known" | "unknown") => {
    dispatch(setFilterPresence({ bucket: "company", key: industryFilterKey, presence: val }))
  }

  // --- Keyword Logic ---
  const keywordSectionId = "company_keywords"
  const keywordFilterKey = "company_keywords"

  const [keywordInput, setKeywordInput] = useState("")
  const [keywordActionType, setKeywordActionType] = useState<"include" | "exclude">("include")

  const selectedKeywords = useAppSelector(selectSelectedItems)[keywordSectionId] || []
  const activeKeywordFilter = activeCompanyFilters[keywordFilterKey]
  const keywordMode = activeKeywordFilter?.mode || "all"
  const keywordFields = activeKeywordFilter?.fields || ["name", "keywords", "description"]

  const handleAddKeyword = () => {
    if (!keywordInput.trim()) return
    const val = keywordInput.trim()
    const id = `${keywordSectionId}_${val}_${keywordActionType}`

    dispatch(
      addSelectedItem({
        sectionId: keywordSectionId,
        item: { id, name: val, type: keywordActionType },
        isCompanyFilter: true
      })
    )
    setKeywordInput("")
  }

  const handleKeywordKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") handleAddKeyword()
  }

  const toggleKeywordField = (field: string) => {
    const newFields = keywordFields.includes(field) ? keywordFields.filter((f) => f !== field) : [...keywordFields, field]
    if (newFields.length === 0) return
    dispatch(setFilterFields({ bucket: "company", key: keywordFilterKey, fields: newFields }))
  }

  const setKeywordMode = (m: "all" | "any") => {
    dispatch(setFilterMode({ bucket: "company", key: keywordFilterKey, mode: m }))
  }

  return (
    <div className="flex flex-col gap-6 p-2">
      {/* === INDUSTRY SECTION === */}
      <div className="flex flex-col gap-3">
        <h3 className="text-xs font-semibold text-gray-900 dark:text-gray-100">Industry</h3>

        {/* Presence */}
        <div className="flex gap-4">
          {["any", "known", "unknown"].map((p) => (
            <label key={p} className="flex cursor-pointer items-center space-x-2">
              <input
                type="radio"
                name="industry_presence"
                value={p}
                checked={presence === p}
                onChange={() => handlePresenceChange(p as "any" | "known" | "unknown")}
                className="text-blue-600 focus:ring-blue-500"
              />
              <span className="text-sm capitalize">{p}</span>
            </label>
          ))}
        </div>

        {presence !== "unknown" && (
          <>
            {/* Modes (Include/Exclude) */}
            <div className="flex items-center gap-2">
              <Button
                variant={industryMode === "include" ? "secondary" : "ghost"}
                size="sm"
                onClick={() => setIndustryMode("include")}
                className="h-6 text-xs"
              >
                Include
              </Button>
              <Button
                variant={industryMode === "exclude" ? "secondary" : "ghost"}
                size="sm"
                onClick={() => setIndustryMode("exclude")}
                className="h-6 text-xs"
              >
                Exclude
              </Button>
            </div>

            {/* Search Input */}
            <div className="relative ">
              <Search className="absolute left-2  top-2.5 size-4 text-gray-400" />
              <Input placeholder="Search industries..." value={industrySearchTerm} onChange={handleIndustrySearch} className="h-9 pl-8 text-sm" />
              {!!isIndustryLoading && <Loader2 className="absolute right-2 top-2.5 size-4 animate-spin text-gray-400" />}
            </div>

            {/* Suggestions */}
            {!!industrySearchTerm && !!industryResults && industryResults.length > 0 && (
              <div className="max-h-40 overflow-y-auto rounded-md border bg-white p-2 shadow-sm dark:bg-gray-900">
                {industryResults.map((res) => {
                  const isSelected = selectedIndustries.some((i) => i.name === res.name)
                  return (
                    <div
                      key={res.id}
                      className="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-gray-800"
                      onClick={() => toggleIndustry(res)}
                    >
                      <Checkbox checked={isSelected} onChange={() => {}} />
                      <span className="text-sm">{res.name}</span>
                    </div>
                  )
                })}
              </div>
            )}

            {/* Industry Tags */}
            <div className="flex flex-wrap gap-2">
              {selectedIndustries.map((item) => (
                <div
                  key={item.id}
                  className={`flex items-center gap-1 rounded-full px-2 py-1 text-xs ${
                    item.type === "include"
                      ? "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                      : "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300"
                  }`}
                >
                  <span>{item.name}</span>
                  <button
                    onClick={() => dispatch(removeSelectedItem({ sectionId: industrySectionId, itemId: item.id, isCompanyFilter: true }))}
                    className="ml-1 hover:text-black dark:hover:text-white"
                  >
                    ×
                  </button>
                </div>
              ))}
            </div>
          </>
        )}
      </div>

      <div className="h-px bg-gray-200 dark:bg-gray-800" />

      {/* === KEYWORDS SECTION === */}
      <div className="flex flex-col gap-3">
        <h3 className="text-xs font-semibold text-gray-900 dark:text-gray-100">Company Keywords</h3>

        {/* Input */}
        <div className="flex gap-2">
          <Input
            placeholder="e.g. SaaS, AI, B2B..."
            value={keywordInput}
            onChange={(e) => setKeywordInput(e.target.value)}
            onKeyDown={handleKeywordKeyDown}
            className="h-9 text-sm"
          />
          <Button variant="outline" size="icon" onClick={handleAddKeyword} className="size-9">
            <Plus className="size-4" />
          </Button>
        </div>

        {/* Keyword Controls */}
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-1 rounded-md border p-1">
            <Button
              variant={keywordActionType === "include" ? "secondary" : "ghost"}
              size="sm"
              className="h-6 text-xs"
              onClick={() => setKeywordActionType("include")}
            >
              Include
            </Button>
            <Button
              variant={keywordActionType === "exclude" ? "secondary" : "ghost"}
              size="sm"
              className="h-6 text-xs"
              onClick={() => setKeywordActionType("exclude")}
            >
              Exclude
            </Button>
          </div>

          <div className="flex items-center gap-1 rounded-md border p-1">
            <Button
              variant={keywordMode === "all" ? "secondary" : "ghost"}
              size="sm"
              className="h-6 text-xs"
              onClick={() => setKeywordMode("all")}
              disabled={keywordActionType === "exclude"}
            >
              All
            </Button>
            <Button
              variant={keywordMode === "any" ? "secondary" : "ghost"}
              size="sm"
              className="h-6 text-xs"
              onClick={() => setKeywordMode("any")}
              disabled={keywordActionType === "exclude"}
            >
              Any
            </Button>
          </div>
        </div>

        {/* Fields */}
        <div className="flex flex-col gap-2 rounded-md bg-gray-50 p-2 dark:bg-gray-900">
          <span className="text-xs font-medium text-gray-500">Search in:</span>
          <div className="flex gap-4">
            {["name", "keywords", "description"].map((f) => (
              <div key={f} className="flex items-center gap-1.5">
                <Checkbox checked={keywordFields.includes(f)} onChange={() => toggleKeywordField(f)} />
                <span className="text-xs capitalize text-gray-700 dark:text-gray-300">{f}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Keyword Tags */}
        <div className="flex flex-wrap gap-2">
          {selectedKeywords.map((item) => (
            <div
              key={item.id}
              className={`flex items-center gap-1 rounded-full px-2 py-1 text-xs ${
                item.type === "include"
                  ? "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                  : "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300"
              }`}
            >
              <span>{item.name}</span>
              <button
                onClick={() => dispatch(removeSelectedItem({ sectionId: keywordSectionId, itemId: item.id, isCompanyFilter: true }))}
                className="ml-1 hover:text-black dark:hover:text-white"
              >
                ×
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
