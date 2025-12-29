import React, { useMemo, useState, useRef, ChangeEvent } from "react"
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"
import Checkbox from "@/components/ui/checkbox"
import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import { addSelectedItem, removeSelectedItem, selectSearchContext } from "@/features/filters/slice/filterSlice"
import { useLazyCompaniesExistenceQuery } from "@/features/filters/slice/apiSlice"
import { Upload, X, Check, AlertCircle } from "lucide-react"
import Papa from "papaparse" // Add this package for CSV parsing
import { Badge } from "@/components/ui/badge"

type Props = {
  open: boolean
  onOpenChange: (o: boolean) => void
  onApplyFilters?: () => void // Optional callback after applying filters
}

const normalizeDomain = (d: string) => {
  if (!d) return ""
  return d
    .trim()
    .toLowerCase()
    .replace(/^https?:\/\//, "")
    .replace(/^www\./, "")
    .split("/")[0] // Remove any paths
}

const normalizeName = (s: string) => s.trim()

const BulkCompanyInputDialog: React.FC<Props> = ({ open, onOpenChange, onApplyFilters }) => {
  const dispatch = useAppDispatch()
  const searchContext = useAppSelector(selectSearchContext)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const [tab, setTab] = useState<"names" | "domains" | "csv">("names")
  const [text, setText] = useState("")
  const [csvFile, setCsvFile] = useState<File | null>(null)
  const [firstRowHeader, setFirstRowHeader] = useState(true)
  const [csvColumn, setCsvColumn] = useState<"auto" | "name" | "domain">("auto")
  const [checkExistence] = useLazyCompaniesExistenceQuery()
  const [preview, setPreview] = useState<{
    total: number
    matched: number
    skipped: number
    sampleItems?: string[]
  } | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)
  const [parsedData, setParsedData] = useState<string[]>([])
  const [error, setError] = useState<string | null>(null)

  const sectionIdName = "company_name"
  const sectionIdDomain = "company_domain"

  const tokens = useMemo(() => {
    if (tab === "csv") {
      return parsedData
    }

    const raw = text
      .split(/\n|,|;|\|/)
      .map((x) => x.trim())
      .filter((x) => x.length > 0)
    return Array.from(new Set(raw))
  }, [text, tab, parsedData])

  const addTokens = (vals: string[], type: "name" | "domain") => {
    const limit = 5000
    const slice = vals.slice(0, limit)

    for (const v of slice) {
      if (type === "domain") {
        const nd = normalizeDomain(v)
        if (nd) {
          dispatch(
            addSelectedItem({
              sectionId: sectionIdDomain,
              item: {
                id: `${sectionIdDomain}_${nd}`,
                name: nd,
                type: "include"
              }
            })
          )
        }
      } else {
        const nn = normalizeName(v)
        if (nn) {
          dispatch(
            addSelectedItem({
              sectionId: sectionIdName,
              item: {
                id: `${sectionIdName}_${nn}`,
                name: nn,
                type: "include"
              }
            })
          )
        }
      }
    }

    // Optional callback to trigger filter application
    if (onApplyFilters) {
      onApplyFilters()
    }
  }

  const parseCSV = async (file: File): Promise<string[]> => {
    return new Promise((resolve, reject) => {
      Papa.parse(file, {
        header: firstRowHeader,
        skipEmptyLines: true,
        complete: (results) => {
          if (results.errors.length > 0) {
            reject(new Error(results.errors[0].message))
            return
          }

          let columnValues: string[] = []

          if (firstRowHeader && results.data.length > 0) {
            const data = results.data as Record<string, string>[]
            const headers = Object.keys(data[0] || {})

            let targetColumn = ""

            if (csvColumn === "auto") {
              // Auto-detect column
              const headerStr = headers.join("|").toLowerCase()
              if (headerStr.includes("domain") || headerStr.includes("website")) {
                targetColumn = headers.find((h) => /domain|website/i.test(h || "")) || headers[0]
              } else {
                targetColumn = headers.find((h) => /company|name|organization/i.test(h || "")) || headers[0]
              }
            } else if (csvColumn === "domain") {
              targetColumn = headers.find((h) => /domain|website/i.test(h || "")) || headers[0]
            } else {
              targetColumn = headers.find((h) => /company|name|organization/i.test(h || "")) || headers[0]
            }

            columnValues = data.map((row) => row[targetColumn] || "").filter((val) => val.trim().length > 0)
          } else {
            // No headers, use first column
            const data = results.data as string[][]
            columnValues = data.map((row) => row[0] || "").filter((val) => val.trim().length > 0)
          }

          resolve(Array.from(new Set(columnValues)))
        },
        error: (error) => reject(error)
      })
    })
  }

  const handleFileSelect = async (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return

    setError(null)
    setCsvFile(file)

    try {
      const data = await parseCSV(file)
      setParsedData(data)

      if (data.length === 0) {
        setError("No valid data found in CSV")
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to parse CSV")
      setParsedData([])
    }
  }

  const handlePreviewMatches = async () => {
    try {
      setPreviewLoading(true)
      setError(null)

      if (tab === "names") {
        const vals = tokens.map((t) => normalizeName(t)).filter((x) => x)
        const res = await checkExistence({ names: vals }).unwrap()
        const found = res.data?.found_names || []
        setPreview({
          total: vals.length,
          matched: found.length,
          skipped: Math.max(vals.length - found.length, 0),
          sampleItems: found.slice(0, 5)
        })
      } else if (tab === "domains") {
        const vals = tokens.map((t) => normalizeDomain(t)).filter((x) => x)
        const res = await checkExistence({ domains: vals }).unwrap()
        const found = res.data?.found_domains || []
        setPreview({
          total: vals.length,
          matched: found.length,
          skipped: Math.max(vals.length - found.length, 0),
          sampleItems: found.slice(0, 5)
        })
      } else if (tab === "csv" && tokens.length > 0) {
        // Determine if we should check domains or names
        const isDomainColumn = csvColumn === "domain" || (csvColumn === "auto" && tokens.some((t) => t.includes(".") && !t.includes(" ")))

        if (isDomainColumn) {
          const vals = tokens.map((t) => normalizeDomain(t)).filter((x) => x)
          const res = await checkExistence({ domains: vals }).unwrap()
          const found = res.data?.found_domains || []
          setPreview({
            total: vals.length,
            matched: found.length,
            skipped: Math.max(vals.length - found.length, 0),
            sampleItems: found.slice(0, 5)
          })
        } else {
          const vals = tokens.map((t) => normalizeName(t)).filter((x) => x)
          const res = await checkExistence({ names: vals }).unwrap()
          const found = res.data?.found_names || []
          setPreview({
            total: vals.length,
            matched: found.length,
            skipped: Math.max(vals.length - found.length, 0),
            sampleItems: found.slice(0, 5)
          })
        }
      }
    } catch (err) {
      setError("Failed to check existence. Please try again.")
      setPreview(null)
    } finally {
      setPreviewLoading(false)
    }
  }

  const handleSave = async () => {
    try {
      if (tab === "names") {
        const vals = tokens.map((t) => normalizeName(t)).filter((x) => x)
        const res = await checkExistence({ names: vals }).unwrap()
        const found = res.data?.found_names || vals // Use all if API fails
        addTokens(found, "name")
      } else if (tab === "domains") {
        const vals = tokens.map((t) => normalizeDomain(t)).filter((x) => x)
        const res = await checkExistence({ domains: vals }).unwrap()
        const found = res.data?.found_domains || vals // Use all if API fails
        addTokens(found, "domain")
      } else if (tab === "csv" && tokens.length > 0) {
        const isDomainColumn = csvColumn === "domain" || (csvColumn === "auto" && tokens.some((t) => t.includes(".") && !t.includes(" ")))

        if (isDomainColumn) {
          const vals = tokens.map((t) => normalizeDomain(t)).filter((x) => x)
          const res = await checkExistence({ domains: vals }).unwrap()
          const found = res.data?.found_domains || vals
          addTokens(found, "domain")
        } else {
          const vals = tokens.map((t) => normalizeName(t)).filter((x) => x)
          const res = await checkExistence({ names: vals }).unwrap()
          const found = res.data?.found_names || vals
          addTokens(found, "name")
        }
      }
    } catch (err) {
      // Fallback: add all tokens without checking existence
      if (tab === "names" || (tab === "csv" && csvColumn !== "domain")) {
        addTokens(
          tokens.map((t) => normalizeName(t)).filter((x) => x),
          "name"
        )
      } else {
        addTokens(
          tokens.map((t) => normalizeDomain(t)).filter((x) => x),
          "domain"
        )
      }
    } finally {
      onOpenChange(false)
      setText("")
      setCsvFile(null)
      setParsedData([])
      setPreview(null)
    }
  }

  const handleClear = () => {
    setText("")
    setCsvFile(null)
    setParsedData([])
    setPreview(null)
    setError(null)
    if (fileInputRef.current) {
      fileInputRef.current.value = ""
    }
  }

  const isApplyDisabled = () => {
    if (tab === "csv") {
      return parsedData.length === 0
    }
    return tokens.length === 0
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[520px] rounded-xl border p-0">
        <DialogHeader className="flex flex-row items-start justify-between border-b p-5">
          <div>
            <DialogTitle className="text-sm font-medium">Bulk Company Input</DialogTitle>
            <DialogDescription className="text-xs">Import company names or domains from CSV or paste directly</DialogDescription>
          </div>
        </DialogHeader>
        <div className="p-5">
          <div className="mb-4">
            <div className="flex items-center justify-between gap-2">
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  className={`px-3 text-xs ${tab === "names" ? "border-blue-200 bg-blue-50" : ""}`}
                  onClick={() => {
                    setTab("names")
                    setPreview(null)
                  }}
                >
                  Names
                </Button>
                <Button
                  variant="outline"
                  className={`px-3 text-xs ${tab === "domains" ? "border-blue-200 bg-blue-50" : ""}`}
                  onClick={() => {
                    setTab("domains")
                    setPreview(null)
                  }}
                >
                  Domains
                </Button>
                <Button
                  variant="outline"
                  className={`px-3 text-xs ${tab === "csv" ? "border-blue-200 bg-blue-50" : ""}`}
                  onClick={() => {
                    setTab("csv")
                    setPreview(null)
                  }}
                >
                  CSV Import
                </Button>
              </div>
              <Button variant="outline" className="px-3 text-xs" onClick={handlePreviewMatches} disabled={previewLoading || isApplyDisabled()}>
                {previewLoading ? <>Checking...</> : <>Preview Matches</>}
              </Button>
            </div>
          </div>

          {tab !== "csv" ? (
            <div className="space-y-2">
              <textarea
                className="h-32 w-full rounded-md border p-3 text-sm"
                placeholder={
                  tab === "names" ? "Paste company names (one per line or comma-separated)" : "Paste domains (one per line or comma-separated)"
                }
                value={text}
                onChange={(e) => setText(e.target.value)}
              />
              <div className="flex items-center justify-between">
                <span className="text-xs text-gray-500">{tokens.length} unique items</span>
                {!!text && (
                  <Button variant="ghost" size="sm" className="h-6 text-xs" onClick={() => setText("")}>
                    Clear
                  </Button>
                )}
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              <div className="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center">
                <input ref={fileInputRef} type="file" accept=".csv,.txt,.xlsx,.xls" className="hidden" onChange={handleFileSelect} />
                {!csvFile ? (
                  <div className="cursor-pointer" onClick={() => fileInputRef.current?.click()}>
                    <Upload className="mx-auto size-10 text-gray-400" />
                    <p className="mt-2 text-sm text-gray-600">Click to upload CSV file</p>
                    <p className="mt-1 text-xs text-gray-500">Supports .csv, .xlsx, .xls</p>
                  </div>
                ) : (
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <Check className="size-5 text-green-500" />
                        <span className="text-sm font-medium">{csvFile.name}</span>
                        <Badge variant="secondary" className="text-xs">
                          {parsedData.length} items
                        </Badge>
                      </div>
                      <Button variant="ghost" size="sm" onClick={handleClear}>
                        <X className="size-4" />
                      </Button>
                    </div>
                    <div className="text-left">
                      <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                          <Checkbox checked={firstRowHeader} onChange={setFirstRowHeader} />
                          <span className="text-xs text-gray-600">First row is header</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className="text-xs text-gray-600">Column type:</span>
                          <select
                            className="rounded border px-2 py-1 text-xs"
                            value={csvColumn}
                            onChange={(e) => setCsvColumn(e.target.value as "auto" | "name" | "domain")}
                          >
                            <option value="auto">Auto-detect</option>
                            <option value="name">Company Names</option>
                            <option value="domain">Domains</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                )}
              </div>

              {parsedData.length > 0 && (
                <div className="max-h-40 overflow-y-auto rounded-md border p-3">
                  <p className="mb-2 text-xs font-medium">Preview ({parsedData.length} items):</p>
                  <div className="space-y-1">
                    {parsedData.slice(0, 10).map((item, idx) => (
                      <div key={idx} className="truncate text-xs text-gray-600">
                        {item}
                      </div>
                    ))}
                    {parsedData.length > 10 && <div className="text-xs text-gray-500">... and {parsedData.length - 10} more</div>}
                  </div>
                </div>
              )}
            </div>
          )}

          {!!error && (
            <div className="mt-3 flex items-center gap-2 text-xs text-red-600">
              <AlertCircle className="size-4" />
              <span>{error}</span>
            </div>
          )}

          {!!preview && (
            <div className="mt-4 rounded-md border border-blue-200 bg-blue-50 p-3">
              <div className="mb-2 grid grid-cols-3 gap-4">
                <div className="text-center">
                  <div className="text-lg font-semibold text-gray-900">{preview.total}</div>
                  <div className="text-xs text-gray-600">Total</div>
                </div>
                <div className="text-center">
                  <div className="text-lg font-semibold text-green-600">{preview.matched}</div>
                  <div className="text-xs text-gray-600">Matched</div>
                </div>
                <div className="text-center">
                  <div className="text-lg font-semibold text-amber-600">{preview.skipped}</div>
                  <div className="text-xs text-gray-600">Skipped</div>
                </div>
              </div>

              {preview.matched > 0 && !!preview.sampleItems && (
                <div className="mt-2">
                  <p className="mb-1 text-xs font-medium">Sample matches:</p>
                  <div className="truncate text-xs text-gray-600">
                    {preview.sampleItems.join(", ")}
                    {preview.matched > 5 && "..."}
                  </div>
                </div>
              )}

              {preview.skipped > 0 && (
                <p className="mt-2 text-xs text-amber-600">Note: {preview.skipped} items will be skipped as they don&apos;t exist in the database</p>
              )}
            </div>
          )}
        </div>
        <DialogFooter className="flex gap-2 border-t p-5">
          <DialogClose asChild>
            <Button variant="outline" className="flex-1">
              Cancel
            </Button>
          </DialogClose>
          <Button className="flex-1 bg-[#335CFF] text-white hover:bg-[#2a4fd8]" onClick={handleSave} disabled={isApplyDisabled()}>
            Apply {tokens.length > 0 ? `(${tokens.length})` : ""} Filters
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

export default BulkCompanyInputDialog
