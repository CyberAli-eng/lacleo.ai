import React, { useEffect, useMemo, useRef, useState } from "react"
import { Building } from "lucide-react"
import GroupIcon from "../../../static/media/icons/group-icon.svg?react"
import { Button } from "../button"
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "../dialog"
import Checkbox from "../checkbox"
import DragIcon from "../../../static/media/icons/drag-icon.svg?react"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "../select"
import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import { closeColumnsModal, ColumnsTab, setActiveColumnsTab, setColumnsForTab } from "@/features/searchTable/slice/columnsSlice"
import type { ColumnOption } from "@/features/searchTable/slice/columnsSlice"

const EditColumn = () => {
  const dispatch = useAppDispatch()
  const open = useAppSelector((s) => s.columns.open)
  const activeTab = useAppSelector((s) => s.columns.activeTab)
  const allConfigs = useAppSelector((s) => s.columns.configs)
  const [items, setItems] = useState<ColumnOption[]>(allConfigs[activeTab])
  const dragIndexRef = useRef<number | null>(null)

  useEffect(() => {
    setItems(allConfigs[activeTab])
  }, [allConfigs, activeTab])

  const ADD_TITLE_MAP: Record<string, string> = { phone_number: "Number", email: "Email" }

  const handleToggleVisible = (index: number) => {
    setItems((prev) => prev.map((c, i) => (i === index ? { ...c, visible: !c.visible } : c)))
  }

  const handleAddColumn = (field: string) => {
    setItems((prev) => {
      const idx = prev.findIndex((c) => c.field === field)
      if (idx === -1) {
        const title = ADD_TITLE_MAP[field] || field
        return [...prev, { field, title, visible: true }]
      }
      const updated = [...prev]
      updated[idx] = { ...updated[idx], visible: true }
      return updated
    })
  }

  const handleDragStart = (index: number) => (e: React.DragEvent<HTMLDivElement>) => {
    dragIndexRef.current = index
    e.dataTransfer.effectAllowed = "move"
  }

  const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    e.dataTransfer.dropEffect = "move"
  }

  const handleDrop = (index: number) => (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    const from = dragIndexRef.current
    if (from === null || from === index) return
    setItems((prev) => {
      const updated = [...prev]
      const [moved] = updated.splice(from, 1)
      updated.splice(index, 0, moved)
      return updated
    })
    dragIndexRef.current = null
  }

  const handleSave = () => {
    dispatch(setColumnsForTab({ tab: activeTab, columns: items }))
    dispatch(closeColumnsModal())
  }

  return (
    <Dialog open={open} onOpenChange={(o) => !o && dispatch(closeColumnsModal())}>
      <DialogContent className="max-w-[420px] rounded-xl border p-0">
        <DialogHeader className="flex flex-row items-start justify-between border-b border-border p-5">
          <DialogTitle className="flex flex-row items-center gap-3.5">
            <div className="flex flex-col items-start">
              <span className="text-sm font-medium text-gray-950">Modify Columns</span>
              <span className="text-xs font-normal text-gray-600">Select and reorder columns for this table</span>
            </div>
          </DialogTitle>
          <DialogDescription className="sr-only">Select and reorder columns for this table</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-4 p-5">
          <div className="flex w-full flex-row items-center justify-between gap-1 rounded-lg bg-[#F7F7F7] p-1">
            <Button
              variant="outline"
              className={`h-7 w-full p-1 ${
                activeTab === "contact"
                  ? "bg-white text-gray-950 hover:bg-white hover:text-gray-950"
                  : "border-none bg-transparent text-[#A3A3A3] shadow-none hover:bg-transparent hover:text-[#A3A3A3]"
              }`}
              onClick={() => dispatch(setActiveColumnsTab("contact" as ColumnsTab))}
              aria-pressed={activeTab === "contact"}
            >
              <GroupIcon aria-hidden="true" />
              Contact
            </Button>
            <Button
              variant="outline"
              className={`h-7 w-full p-1 ${
                activeTab === "company"
                  ? "bg-white text-gray-950 hover:bg-white hover:text-gray-950"
                  : "border-none bg-transparent text-[#A3A3A3] shadow-none hover:bg-transparent hover:text-[#A3A3A3]"
              }`}
              onClick={() => dispatch(setActiveColumnsTab("company" as ColumnsTab))}
              aria-pressed={activeTab === "company"}
            >
              <Building className="size-5" />
              Company
            </Button>
          </div>

          <div className="flex max-h-72 flex-col gap-2 overflow-auto pr-1">
            {items.map((col, index) => (
              <div
                key={col.field}
                className="flex w-full cursor-grab select-none flex-row items-center justify-between rounded-lg border border-border p-2 active:cursor-grabbing"
                draggable
                onDragStart={handleDragStart(index)}
                onDragOver={handleDragOver}
                onDrop={handleDrop(index)}
                onDragEnd={() => {
                  dragIndexRef.current = null
                }}
              >
                <div className="flex flex-row items-center gap-2">
                  <Checkbox checked={col.visible} onChange={(_) => handleToggleVisible(index)} className="size-5" />
                  <span id={`col-${col.field}-label`} className="text-sm font-medium text-gray-700">
                    {col.title}
                  </span>
                </div>
                <button type="button" className="text-muted-foreground">
                  <DragIcon aria-hidden="true" />
                </button>
              </div>
            ))}
          </div>

          <div className="flex flex-col gap-2">
            <span className="text-sm font-medium text-gray-950">Add Columns</span>
            <Select onValueChange={handleAddColumn}>
              <SelectTrigger className="h-9 p-[10px] text-sm font-medium text-gray-600">
                <SelectValue placeholder="Select column to add" />
              </SelectTrigger>
              <SelectContent className="text-sm font-medium text-gray-950">
                <SelectItem value="phone_number">{ADD_TITLE_MAP["phone_number"]}</SelectItem>
                <SelectItem value="email">{ADD_TITLE_MAP["email"]}</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <DialogFooter className="border-t p-5">
          <DialogClose asChild>
            <Button variant="outline" className="w-full rounded-lg p-2 text-sm font-medium text-[#5C5C5C]">
              Cancel
            </Button>
          </DialogClose>
          <Button
            onClick={handleSave}
            variant="outline"
            className="w-full rounded-lg bg-[#335CFF] p-2 text-sm font-medium text-white hover:bg-[#335CFF] hover:text-white"
          >
            Save Changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export default EditColumn
