import { ChevronDown, Save, Search } from "lucide-react"
import { useState } from "react"
import { Button } from "../button"
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "../dialog"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "../dropdown-menu"
import { Input } from "../input"

type SaveFilterProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
}

const SaveFilter = ({ open, onOpenChange }: SaveFilterProps) => {
  const [searchMode, setSearchMode] = useState("new")
  const [selectedFilter, setSelectedFilter] = useState("")
  const [searchQuery, setSearchQuery] = useState("")
  const [newSearchName, setNewSearchName] = useState("")
  const [isDropdownOpen, setIsDropdownOpen] = useState(false)

  const savedSearches = [
    "SaaS Marketing VPs",
    "Enterprise IT Directors",
    "SaaS Marketing Directors",
    "US companies Revenue >200M",
    "US companies Revenue >200M"
  ]

  const filteredSearches = savedSearches.filter((search) => search.toLowerCase().includes(searchQuery.toLowerCase()))

  const handleSave = () => {
    if (searchMode === "new") {
      console.log("Saving new search:", newSearchName)
    } else {
      console.log("Modifying existing search:", selectedFilter)
    }
  }

  const isSaveDisabled = searchMode === "new" ? newSearchName.trim() === "" : selectedFilter.trim() === ""

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[400px] border p-0">
        <DialogHeader className="border-b border-border p-5">
          <DialogTitle className="flex flex-row items-center gap-3.5">
            <span className="flex items-center justify-center rounded-full border p-[10px]">
              <Save className="size-5" />
            </span>
            <div className="flex flex-col items-start">
              <span className="text-sm font-medium text-gray-950">Save Filters</span>
              <span className="text-xs font-normal text-gray-600">Save your search setup for quick access later</span>
            </div>
          </DialogTitle>
          <DialogDescription className="sr-only">Form to save current filter configuration.</DialogDescription>
        </DialogHeader>

        <div className="p-5">
          {/* Save as new search */}
          <div className="flex flex-col gap-3">
            <label className="flex cursor-pointer items-center gap-3">
              <div className="relative">
                <input
                  type="radio"
                  name="saveMode"
                  value="new"
                  checked={searchMode === "new"}
                  onChange={(e) => setSearchMode(e.target.value)}
                  className="sr-only"
                />
                <div
                  className={`size-4 rounded-full border-2 transition-all ${
                    searchMode === "new" ? "border-blue-600 bg-blue-600" : "border-gray-300 bg-white"
                  }`}
                >
                  {searchMode === "new" && <div className="absolute inset-0 m-[3px] rounded-full bg-white" />}
                </div>
              </div>
              <span className="text-sm font-medium text-gray-950">Save as new search</span>
            </label>
            <Input
              placeholder="Add a label to this filter search"
              value={newSearchName}
              onChange={(e) => setNewSearchName(e.target.value)}
              disabled={searchMode !== "new"}
              className={searchMode !== "new" ? "bg-gray-50 opacity-60" : ""}
            />
          </div>

          {/* Modify existing search */}
          <div className="mt-5 flex flex-col gap-3">
            <label className="flex cursor-pointer items-center gap-3">
              <div className="relative">
                <input
                  type="radio"
                  name="saveMode"
                  value="modify"
                  checked={searchMode === "modify"}
                  onChange={(e) => setSearchMode(e.target.value)}
                  className="sr-only"
                />
                <div
                  className={`size-4 rounded-full border-2 transition-all ${
                    searchMode === "modify" ? "border-blue-600 bg-blue-600" : "border-gray-300 bg-white"
                  }`}
                >
                  {searchMode === "modify" && <div className="absolute inset-0 m-[3px] rounded-full bg-white" />}
                </div>
              </div>
              <span className="text-sm font-medium text-gray-950">Modify existing search</span>
            </label>

            <DropdownMenu
              open={!!isDropdownOpen && searchMode === "modify"}
              onOpenChange={(open) => searchMode === "modify" && setIsDropdownOpen(open)}
            >
              <DropdownMenuTrigger asChild>
                <Button
                  variant="outline"
                  role="combobox"
                  aria-expanded={isDropdownOpen}
                  disabled={searchMode !== "modify"}
                  className={`w-full justify-between font-normal ${searchMode !== "modify" ? "bg-gray-50 opacity-60" : ""} ${
                    !selectedFilter ? "text-muted-foreground" : ""
                  }`}
                >
                  {selectedFilter || "Select the saved filter to overwrite"}
                  <ChevronDown className={`ml-2 size-4 shrink-0 transition-transform ${isDropdownOpen ? "rotate-180" : ""}`} />
                </Button>
              </DropdownMenuTrigger>

              <DropdownMenuContent className="w-[--radix-dropdown-menu-trigger-width] p-0">
                {/* Search Input */}
                <div className="border-b p-2">
                  <div className="relative">
                    <Search className="absolute left-2 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      placeholder="Search..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="h-8 border-none pl-8 text-sm font-normal text-gray-600 shadow-none"
                      onClick={(e) => e.stopPropagation()}
                    />
                  </div>
                </div>

                {/* Search Results */}
                <div className="max-h-48 overflow-y-auto p-2">
                  {filteredSearches.length > 0 ? (
                    filteredSearches.map((search, index) => (
                      <DropdownMenuItem
                        key={index}
                        onClick={() => {
                          setSelectedFilter(search)
                          setSearchQuery("")
                          setIsDropdownOpen(false)
                        }}
                        className="cursor-pointer text-sm font-normal text-gray-950"
                      >
                        {search}
                      </DropdownMenuItem>
                    ))
                  ) : (
                    <div className="px-2 py-1.5 text-sm text-muted-foreground">No results found</div>
                  )}
                </div>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        <DialogFooter className="border-t p-5">
          <DialogClose asChild>
            <Button variant="outline" className="w-full rounded-lg p-2 text-sm font-medium text-[#5C5C5C]">
              Cancel
            </Button>
          </DialogClose>
          <Button
            variant="outline"
            onClick={handleSave}
            disabled={isSaveDisabled}
            className="w-full rounded-lg p-2 text-sm font-medium text-[#5C5C5C]"
          >
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export default SaveFilter
