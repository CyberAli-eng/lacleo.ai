import React, { useState } from "react"
import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import {
  addSelectedItem,
  removeSelectedItem,
  selectSelectedItems,
  selectCompanyFilters,
  setFilterMode,
  setFilterFields
} from "@/features/filters/slice/filterSlice"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import Checkbox from "@/components/ui/checkbox"

import { Plus } from "lucide-react"

export const CompanyKeywordFilter = () => {
  const dispatch = useAppDispatch()
  const sectionId = "company_keywords" // We will need to map this sectionId in filterSlice manually if not present
  const filterKey = "company_keywords"

  const [inputValue, setInputValue] = useState("")
  const [actionType, setActionType] = useState<"include" | "exclude">("include")

  // Redux
  const selectedItems = useAppSelector(selectSelectedItems)[sectionId] || []
  const activeFilters = useAppSelector(selectCompanyFilters)
  const activeFilter = activeFilters[filterKey]

  // Defaults
  const mode = activeFilter?.mode || "all"
  const fields = activeFilter?.fields || ["name", "keywords", "description"]

  const handleAdd = () => {
    if (!inputValue.trim()) return
    const val = inputValue.trim()
    const id = `${sectionId}_${val}_${actionType}`

    dispatch(
      addSelectedItem({
        sectionId,
        item: { id, name: val, type: actionType },
        isCompanyFilter: true
      })
    )
    setInputValue("")
  }

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") handleAdd()
  }

  const toggleField = (field: string) => {
    const newFields = fields.includes(field) ? fields.filter((f) => f !== field) : [...fields, field]

    // Prevent empty fields
    if (newFields.length === 0) return

    dispatch(setFilterFields({ bucket: "company", key: filterKey, fields: newFields }))
  }

  const setMode = (m: "all" | "any") => {
    dispatch(setFilterMode({ bucket: "company", key: filterKey, mode: m }))
  }

  return (
    <div className="flex flex-col gap-4 p-2">
      {/* Input Area */}
      <div className="flex gap-2">
        <Input
          placeholder="Enter keywords (e.g. SaaS, AI)..."
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          onKeyDown={handleKeyDown}
        />
        <Button variant="outline" size="icon" onClick={handleAdd}>
          <Plus className="size-4" />
        </Button>
      </div>

      {/* Controls Row */}
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-1 rounded-md border p-1">
          <Button
            variant={actionType === "include" ? "secondary" : "ghost"}
            size="sm"
            className="h-6 text-xs"
            onClick={() => setActionType("include")}
          >
            Include
          </Button>
          <Button
            variant={actionType === "exclude" ? "secondary" : "ghost"}
            size="sm"
            className="h-6 text-xs"
            onClick={() => setActionType("exclude")}
          >
            Exclude
          </Button>
        </div>

        {/* Mode Toggle (only relevant for Includes) */}
        <div className="flex items-center gap-1 rounded-md border p-1">
          <Button
            variant={mode === "all" ? "secondary" : "ghost"}
            size="sm"
            className="h-6 text-xs"
            onClick={() => setMode("all")}
            disabled={actionType === "exclude"}
          >
            All
          </Button>
          <Button
            variant={mode === "any" ? "secondary" : "ghost"}
            size="sm"
            className="h-6 text-xs"
            onClick={() => setMode("any")}
            disabled={actionType === "exclude"}
          >
            Any
          </Button>
        </div>
      </div>

      {/* Fields Selection */}
      <div className="flex flex-col gap-2 rounded-md bg-gray-50 p-2 dark:bg-gray-900">
        <span className="text-xs font-medium text-gray-500">Search in:</span>
        <div className="flex gap-4">
          {["name", "keywords", "description"].map((f) => (
            <div key={f} className="flex items-center gap-1.5">
              <Checkbox checked={fields.includes(f)} onChange={() => toggleField(f)} />
              <span className="text-xs capitalize text-gray-700 dark:text-gray-300">{f}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Selected Keywords Tags */}
      <div className="flex flex-wrap gap-2">
        {selectedItems.map((item) => (
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
              onClick={() => dispatch(removeSelectedItem({ sectionId, itemId: item.id, isCompanyFilter: true }))}
              className="ml-1 hover:text-black dark:hover:text-white"
            >
              ×
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}
