import DownloadIcon from "../../../static/media/icons/download-icon.svg?react"
import { Button } from "../button"
import Checkbox from "../checkbox"
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "../dialog"
import { Input } from "../input"
import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import { setCreditUsageOpen } from "@/features/settings/slice/settingSlice"
import { useBillingUsageQuery, useExportCreateMutation } from "@/features/searchTable/slice/apiSlice"
import { useMemo, useState } from "react"

type ExportLeadsProps = {
  open: boolean
  onClose: () => void
  selectedCount: number
  totalAvailable: number
  selectedIds: string[]
  type: "contacts" | "companies"
}

const ExportLeads = ({
  open,
  onClose,
  selectedCount,
  totalAvailable,
  selectedIds = [],
  type = "contacts"
}: ExportLeadsProps) => {
   console.log('Selected IDs:', selectedIds);
   console.log("selectedtype", type)
  const [exportMode, setExportMode] = useState<"selected" | "custom">("selected")
  const [customCount, setCustomCount] = useState("")
  const [emailSelected, setEmailSelected] = useState(true)
  const [phoneSelected, setPhoneSelected] = useState(true)
  const [isExporting, setIsExporting] = useState(false)
  const { data: billingData } = useBillingUsageQuery()
  const availableCredits = billingData?.balance ?? 0
  const [createExport] = useExportCreateMutation()
  const dispatch = useAppDispatch()
  
  const selectedData: string[] = []
  if (emailSelected) selectedData.push("Emails")
  if (phoneSelected) selectedData.push("Phone Numbers")

  const parsedCustomCount = useMemo(() => {
    const value = Number(customCount)
    if (Number.isNaN(value) || value < 0) return 0
    return Math.min(value, totalAvailable)
  }, [customCount, totalAvailable])

  const exportCount = exportMode === "selected" ? selectedCount : parsedCustomCount
  
  const handleExport = async () => {
    if (!selectedIds.length) {
      onClose()
      return
    }

    try {
      setIsExporting(true)
      // Backend enforces a hard cap of 50k rows; for companies we always send a
      // limit to avoid hitting the "Too many records" guard when many contacts
      // are linked to selected companies.
      const backendLimit =
        exportMode === "custom"
          ? parsedCustomCount
          : type === "companies"
            ? 50000
            : undefined

      const res = await createExport({
        type,
        ids: selectedIds,
        // When user chooses custom export mode, honor that as a backend `limit`.
        // For companies in selected mode, pass a safe cap of 50k to stay under
        // the MAX_CONTACTS guard in middleware.
        limit: backendLimit,
        fields: {
          email: emailSelected,
          phone: phoneSelected
        }
      }).unwrap()

      if (typeof res === "string") {
        // Backend returned raw CSV text; download via Blob
        const blob = new Blob([res], { type: "text/csv;charset=utf-8;" })
        const url = URL.createObjectURL(blob)
        const link = document.createElement("a")
        link.href = url
        link.download = "export.csv"
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        URL.revokeObjectURL(url)
      } else if (res && typeof res === "object" && "url" in res && (res as any).url) {
        // Backend returned JSON metadata with a URL
        const url = (res as { url: string }).url
        const link = document.createElement("a")
        link.href = url
        link.download = "export.csv"
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
      } else {
        // Minimal debug log if response shape is unexpected
        console.log("Export completed with unexpected response shape", res)
      }

      onClose()
    } catch (err) {
      const anyErr = err as { status?: number; data?: unknown; error?: string }
      const status = anyErr?.status
      const data = anyErr?.data as { error?: string; message?: string; [key: string]: unknown } | undefined
      const code = data?.error || anyErr?.error

      console.error("Export failed", {
        status,
        code,
        responseData: data,
        fullError: err,
        context: {
          type,
          selectedIds,
          exportMode,
          parsedCustomCount,
          selectedCount,
          totalAvailable
        }
      })

      if (status === 402 || code === "INSUFFICIENT_CREDITS") {
        dispatch(setCreditUsageOpen(true))
      }
    } finally {
      setIsExporting(false)
    }
  }

  const [error, setError] = useState('')

  return (
    <Dialog open={open} onOpenChange={(isOpen) => !isOpen && onClose()}>
      <DialogContent className="max-w-[400px] max-h-[90vh] overflow-auto rounded-xl border p-0">
        <DialogHeader className="flex flex-row items-start justify-between border-b border-border p-5">
          <div className="flex flex-row items-center gap-3.5">
            <span className="flex items-center justify-center rounded-full border p-[10px]">
              <DownloadIcon className="size-5 text-gray-600" />
            </span>
            <div className="flex flex-col items-start">
              <DialogTitle className="text-sm font-medium text-gray-950">
                Export {type === "contacts" ? "Contacts" : "Companies"}
              </DialogTitle>
              <DialogDescription className="text-xs font-normal text-gray-600">
                Exporting {selectedCount} {selectedCount === 1 ? "item" : "items"}
              </DialogDescription>
            </div>
          </div>
        </DialogHeader>

        <div className="flex flex-col gap-5 p-5">
          {/* Export selected leads */}
          <label className="flex cursor-pointer items-center gap-3">
            <div className="relative">
              <input
                type="radio"
                name="exportMode"
                value="selected"
                checked={exportMode === "selected"}
                onChange={(e) => setExportMode(e.target.value as "selected" | "custom")}
                className="sr-only"
              />
              <div
                className={`size-4 rounded-full border-2 transition-all ${
                  exportMode === "selected" ? "border-blue-600 bg-blue-600" : "border-gray-300 bg-white"
                }`}
              >
                {exportMode === "selected" && <div className="absolute inset-0 m-[3px] rounded-full bg-white" />}
              </div>
            </div>
            <span className="text-sm font-medium text-gray-950">
              Export Selected {type} ({selectedCount} {selectedCount === 1 ? type.slice(0, -1) : type})
            </span>
          </label>

          {/* Add custom value */}
          <div className="flex flex-col gap-2">
            <label className="flex cursor-pointer items-center gap-3">
              <div className="relative">
                <input
                  type="radio"
                  name="exportMode"
                  value="custom"
                  checked={exportMode === "custom"}
                  onChange={(e) => setExportMode(e.target.value as "selected" | "custom")}
                  className="sr-only"
                />
                <div
                  className={`size-4 rounded-full border-2 transition-all ${
                    exportMode === "custom" ? "border-blue-600 bg-blue-600" : "border-gray-300 bg-white"
                  }`}
                >
                  {exportMode === "custom" && <div className="absolute inset-0 m-[3px] rounded-full bg-white" />}
                </div>
              </div>
              <span className="text-sm font-medium text-gray-950">Add custom value</span>
            </label>

           <div className="flex flex-col gap-1">
  <div className="flex flex-row items-center justify-between gap-2">
    <Input
      type="number"
      placeholder="Enter Number"
      value={customCount}
      onChange={(e) => {
        const value = e.target.value
        setCustomCount(value)
        // Clear error when user starts typing
        if (parseInt(value) <= totalAvailable) {
          setError('')
        }
      }}
      onBlur={(e) => {
        const value = parseInt(e.target.value)
        if (value > totalAvailable) {
          setError(`Maximum ${totalAvailable} items available`)
        } else {
          setError('')
        }
      }}
      disabled={exportMode !== "custom"}
      className={`w-full rounded-lg p-[10px] ${
        exportMode !== "custom" ? "bg-gray-50 opacity-60" : ""
      } ${parseInt(customCount) > totalAvailable ? "border-red-500" : ""}`}
    />
    <span className="text-xs font-medium text-gray-600">Out of {totalAvailable}</span>
  </div>
  {parseInt(customCount) > totalAvailable && (
    <span className="text-xs text-red-500">Entered number is greater than {totalAvailable}</span>
  )}
</div>
          </div>

          <div>
            <span className="text-sm font-medium text-gray-950">Data to export</span>

            <div className="rounded-lg border border-border">
              <div className="flex flex-row gap-[10px] p-3.5">
                <Checkbox 
                  checked={emailSelected} 
                  onChange={(checked: boolean) => setEmailSelected(checked)} 
                />
                <span className="text-sm font-medium text-gray-950">Email Addresses</span>
              </div>
              <div className="border-b"></div>
              <div className="flex flex-row gap-[10px] p-3.5">
                <Checkbox 
                  checked={phoneSelected} 
                  onChange={(checked: boolean) => setPhoneSelected(checked)} 
                />
                <span className="text-sm font-medium text-gray-950">Phone Numbers</span>
              </div>
            </div>
          </div>

          <div className="flex flex-col gap-3 rounded-2xl border p-4">
            <div className="py-1">
              <span className="text-sm font-medium text-gray-950">Credits Cost</span>
            </div>

            <div className="flex flex-col items-center justify-center rounded-xl border">
              <div className="py-1.5">
                <span className="text-xl font-medium text-gray-950">
                  {type === 'contacts' && phoneSelected ? exportCount * 4 : 0} Credits
                </span>
              </div>
              <div className="border-b w-full"></div>
              <div className="flex w-full justify-center rounded-b-xl bg-[#F7F7F7] py-1.5">
                <span className="text-sm font-medium text-gray-600">
                  Available = <span className="text-gray-950">
                    {availableCredits}
                  </span>
                </span>
              </div>
            </div>

            <div className="space-y-3">
              <div className="flex flex-row items-center justify-between gap-2">
                <span className="text-xs font-medium text-gray-600">Total {type}</span>
                <span className="text-xs font-medium text-gray-950">{exportCount}</span>
              </div>

              {selectedData.length > 0 && (
                <div className="flex flex-row items-center justify-between gap-2">
                  <span className="text-xs font-medium text-gray-600">Data</span>
                  <span className="text-xs font-medium text-gray-950">{selectedData.join(" + ")}</span>
                </div>
              )}
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
            variant="outline" 
            className="w-full rounded-lg bg-[#335CFF] p-2 text-sm font-medium text-white hover:bg-[#335CFF] hover:text-white"
            onClick={handleExport}
            disabled={isExporting || (!emailSelected && !phoneSelected) || (exportMode === "custom" && (!customCount || Number(customCount) < 1))}
          >
            {isExporting ? 'Exporting...' : 'Export'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export default ExportLeads
