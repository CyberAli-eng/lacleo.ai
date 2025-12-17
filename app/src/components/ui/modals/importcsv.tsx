import { useState, type ChangeEvent } from "react"
import { Button } from "../button"
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "../dialog"
import { Input } from "../input"
import Checkbox from "../checkbox"
import { Upload } from "lucide-react"

const ImportCSV = () => {
  const [activeTab, setActiveTab] = useState<"include" | "exclude">("include")
  const [isHeader, setIsHeader] = useState<boolean>(true)
  const [csvFileName, setCsvFileName] = useState<string | null>(null)
  const [inputMode, setInputMode] = useState<"text" | "csv">("text")

  const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    setCsvFileName(file ? file.name : null)
  }
  return (
    <Dialog open={true}>
      <DialogContent className="max-w-[420px] rounded-xl border p-0">
        <DialogHeader className="flex flex-row items-start justify-between border-b border-border p-5">
          <DialogTitle className="flex flex-row items-center gap-3.5">
            <div className="flex flex-col items-start">
              <span className="text-sm font-medium text-gray-950">Bulk import companies</span>
              <span className="text-xs font-normal text-gray-600">Upload a CSV of company names or enter them directly into the text field.</span>
            </div>
          </DialogTitle>
          <DialogDescription className="sr-only">Upload or enter company names for bulk import.</DialogDescription>
        </DialogHeader>

        <div className="flex flex-col gap-5 p-5">
          <div className="flex w-full flex-row items-center justify-between gap-1 rounded-lg bg-[#F7F7F7] p-1">
            <Button
              variant="outline"
              className={`h-7 w-full p-1 ${
                activeTab === "include"
                  ? "bg-white text-gray-950 hover:bg-white hover:text-gray-950"
                  : "border-none bg-transparent text-[#A3A3A3] shadow-none hover:bg-transparent hover:text-[#A3A3A3]"
              }`}
              onClick={() => setActiveTab("include")}
              aria-pressed={activeTab === "include"}
            >
              Include
            </Button>
            <Button
              variant="outline"
              className={`h-7 w-full p-1 ${
                activeTab === "exclude"
                  ? "bg-white text-gray-950 hover:bg-white hover:text-gray-950"
                  : "border-none bg-transparent text-[#A3A3A3] shadow-none hover:bg-transparent hover:text-[#A3A3A3]"
              }`}
              onClick={() => setActiveTab("exclude")}
              aria-pressed={activeTab === "exclude"}
            >
              Exclude
            </Button>
          </div>

          <div className="flex flex-col gap-2">
            <div className="flex flex-row items-center gap-2">
              <input
                type="radio"
                name="import-mode"
                aria-label="Enter company name"
                checked={inputMode === "text"}
                onChange={() => setInputMode("text")}
                className="size-4 accent-[#335CFF]"
              />
              <div className="flex flex-col">
                <span className="text-xs font-normal text-gray-950">Enter company name</span>
                <span className="text-xs font-normal text-gray-600">Enter or copy & paste company names or URLs</span>
              </div>
            </div>
            <Input
              className=" p-[10px] text-sm"
              placeholder="Enter one company per row"
              disabled={inputMode !== "text"}
              aria-disabled={inputMode !== "text"}
            />
          </div>

          <div className="flex flex-col gap-2">
            <div className="flex flex-row items-center gap-2">
              <input
                type="radio"
                name="import-mode"
                aria-label="Upload a CSV file"
                checked={inputMode === "csv"}
                onChange={() => setInputMode("csv")}
                className="size-4 accent-[#335CFF]"
              />
              <span className="text-xs font-normal text-gray-950">Upload a CSV file</span>
            </div>
            <div
              className={`flex w-full items-center justify-between rounded-[10px] border border-border bg-white px-3 py-2 shadow-sm ${
                inputMode !== "csv" ? "pointer-events-none opacity-50" : ""
              }`}
            >
              <label
                htmlFor="csv-upload"
                className={`flex select-none items-center gap-2 text-sm text-[#A3A3A3] ${
                  inputMode === "csv" ? "cursor-pointer" : "cursor-not-allowed"
                }`}
              >
                <Upload className="size-5 text-[#A3A3A3]" />
                <span className={`${csvFileName ? "text-xs text-gray-900" : "text-xs text-gray-400"}`}>{csvFileName ?? "Upload CSV"}</span>
              </label>

              <div className="flex items-center gap-2">
                <Checkbox checked={isHeader} onChange={setIsHeader} disabled={inputMode !== "csv"} />
                <span className="text-xs text-gray-600">First row is header</span>
              </div>

              <input
                id="csv-upload"
                type="file"
                accept=".csv,text/csv"
                className="hidden"
                onChange={handleFileChange}
                disabled={inputMode !== "csv"}
              />
            </div>
          </div>
        </div>

        <DialogFooter className="border-t p-5">
          <DialogClose asChild>
            <Button variant="outline" className="w-full rounded-lg p-2 text-sm font-medium text-[#5C5C5C]">
              Cancel
            </Button>
          </DialogClose>
          <Button
            // onClick={handleSave}
            variant="outline"
            className="w-full rounded-lg bg-[#335CFF] p-2 text-sm font-medium text-white hover:bg-[#335CFF] hover:text-white"
          >
            Upload
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export default ImportCSV
