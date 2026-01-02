import useDocumentTitle from "@/app/hooks/documentTitleHook"
import { useAppSelector } from "@/app/hooks/reduxHooks"
import { buildSearchUrl } from "@/app/utils/searchUtils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { CardContent } from "@/components/ui/card"
import ContactInformation from "@/components/ui/contactinformation"
import { Dialog, DialogOverlay, DialogPortal } from "@/components/ui/dialog"
import ExportLeads from "@/components/ui/modals/exportleads"
import EditColumn from "@/components/ui/modals/editcolumn"
import type { ColumnOption } from "@/features/searchTable/slice/columnsSlice"
import { openColumnsModal } from "@/features/searchTable/slice/columnsSlice"
import { useAppDispatch } from "@/app/hooks/reduxHooks"
import { DataTableColumn } from "@/interface/searchTable/components"
import { CompanyAttributes, ContactAttributes, SearchApiResponse } from "@/interface/searchTable/search"
import * as DialogPrimitive from "@radix-ui/react-dialog"
import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocation } from "react-router-dom"
import DownloadIcon from "../../static/media/icons/download-icon.svg?react"
import { setLastResultCount, selectSemanticQuery, selectShowResults, setSearchQueryOnly } from "../aisearch/slice/searchslice"
import { DataTable } from "./baseDataTable"
import { useCompanyLogoQuery } from "./slice/apiSlice"
import { Avatar } from "@/components/ui/avatar"
import { openContactInfoForCompany } from "./slice/contactInfoSlice"
import {
  selectSearchResults,
  selectSearchStatus,
  selectSearchMeta,
  setSort,
  executeSearch,
  setPage,
  setCount
} from "@/features/searchExecution/slice/searchExecutionSlice"
import { useDebounce } from "@/app/hooks/useDebounce"

const CompanyNameCell = ({ row }: { row: CompanyAttributes }) => {
  const normalizedDomain = (row.website || "")
    .replace(/^https?:\/\//, "")
    .replace(/^www\./, "")
    .trim()
  const { data: logoData } = useCompanyLogoQuery({ domain: normalizedDomain }, { skip: normalizedDomain === "" })
  const logoUrl = logoData?.logo_url || null
  return (
    <div className="flex items-center gap-2">
      {logoUrl ? (
        <Avatar className="flex size-6 items-center justify-center rounded-full border">
          <img src={logoUrl} alt={row.company || "Company logo"} className="size-4" />
        </Avatar>
      ) : null}
      <span className="break-words">{row.company || "N/A"}</span>
    </div>
  )
}

export function CompaniesTable() {
  const location = useLocation()
  const navState = (location.state as { fromAi?: boolean } | null) || null
  const fromAi = Boolean(navState?.fromAi)
  useDocumentTitle("Search Companies")
  const dispatch = useAppDispatch()
  const columnsConfigFromStore = useAppSelector((s) => s.columns.configs.company)

  // Table consumes executed results only

  // const globalSearchQuery = useAppSelector(selectSearchQuery)
  // Removed local query state
  const queryValue = useAppSelector((state) => state.searchExecution.searchTerm) || ""

  const handleSearchChange = (val: string) => {
    dispatch(setSearchQueryOnly(val))
    // Also update execution slice? SearchLayout handles the input usually.
    // But table search box might differ.
    // For now, adhere to "Tables must read from store".
    // If table has search input, it should dispatch actions.
  }

  // Removed local sort state
  const sortSelected = useAppSelector((state) => state.searchExecution.sort)
  const setSortSelected = (sort: string[]) => dispatch(setSort(sort))

  // NEW: State for checkbox selection
  const [selectedCompanies, setSelectedCompanies] = useState<string[]>([])
  const [isExportOpen, setIsExportOpen] = useState(false)
  const [infoCompanyContact, setInfoCompanyContact] = useState<ContactAttributes | null>(null)
  const [infoCompanyDetails, setInfoCompanyDetails] = useState<CompanyAttributes | null>(null)

  // NEW: Handle individual company selection
  const handleCompanySelect = useCallback((companyId: string) => {
    setSelectedCompanies((prev) => (prev.includes(companyId) ? prev.filter((id) => id !== companyId) : [...prev, companyId]))
  }, [])

  // NEW: Handle select all companies
  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      const allCompanyIds = companies.map((company) => company.id || "")
      setSelectedCompanies(allCompanyIds.filter((id) => id !== ""))
    } else {
      setSelectedCompanies([])
    }
  }

  const semanticQuery = useAppSelector(selectSemanticQuery)

  const showResults = useAppSelector(selectShowResults)
  const results = useAppSelector(selectSearchResults)
  const status = useAppSelector(selectSearchStatus)
  const searchMeta = useAppSelector(selectSearchMeta)

  // Derive company data and IDs from executed search results
  // Remove useMemo as requested by "Remove memo traps"
  const companies = results as Array<{ id: string; attributes: CompanyAttributes; highlights: Record<string, string[]> | null }>
  const companyData = companies.map((r) => r.attributes)

  const selectedCompanyPhones = useMemo(() => {
    const matching = companies.filter((item) => selectedCompanies.includes(item.id || ""))
    return matching
      .map((item) => {
        const attrs = item.attributes
        return attrs.company_phone || attrs.phone_number || attrs.phone || ""
      })
      .filter((phone) => phone !== "")
  }, [companies, selectedCompanies])

  const selectedCompanyExportIds = useMemo(() => {
    return companies
      .filter((item) => selectedCompanies.includes(item.id || ""))
      .map((item) => item.id || "")
      .filter((id): id is string => typeof id === "string" && id.trim().length > 0)
  }, [companies, selectedCompanies])

  const sortableFields = ["company", "website", "company_linkedin_url"]
  const baseColumns: DataTableColumn<CompanyAttributes>[] = [
    {
      title: "Company Name",
      field: "company",
      width: "w-1/4",
      render: (_value, row) => <CompanyNameCell row={row} />
    },
    {
      title: "Keywords",
      field: "keywords",
      width: "w-1/6",
      render: (value) => {
        const keywords: string[] = Array.isArray(value) ? (value as string[]) : typeof value === "string" ? [value as string] : []
        return (
          <div className="flex flex-wrap gap-1">
            {keywords.slice(0, 3).map((keyword, i) => (
              <span key={i} className="rounded bg-gray-100 px-1 py-0.5 text-xs text-gray-600">
                {keyword}
              </span>
            ))}
            {keywords.length > 3 && <span className="text-xs text-gray-500">+{keywords.length - 3}</span>}
          </div>
        )
      }
    },
    {
      title: "LinkedIn",
      field: "linkedin_url",
      width: "w-1/6",
      render: (value) => {
        const url = value as string
        return url ? (
          <a href={url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">
            View
          </a>
        ) : (
          "N/A"
        )
      }
    },
    {
      title: "Facebook",
      field: "facebook_url",
      width: "w-1/6",
      render: (value) => {
        const url = value as string
        return url ? (
          <a href={url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">
            View
          </a>
        ) : (
          "N/A"
        )
      }
    },
    {
      title: "Twitter",
      field: "twitter_url",
      width: "w-1/6",
      render: (value) => {
        const url = value as string
        return url ? (
          <a href={url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">
            View
          </a>
        ) : (
          "N/A"
        )
      }
    },
    {
      title: "Business Category",
      field: "business_category",
      width: "w-1/6",
      render: (value: unknown) => <div className="text-gray-950">{(value as string) || "N/A"}</div>
    },
    {
      title: "Industry",
      field: "industry",
      width: "w-1/6",
      render: (_value, row) => {
        const bc = row.business_category
        const industry = row.industry
        const bcText = Array.isArray(bc) ? bc.join(", ") : (bc as string | null) || null
        return <div className="text-gray-950">{bcText || industry || "N/A"}</div>
      }
    },
    {
      title: "Description",
      field: "seo_description",
      width: "w-1/4",
      render: (value) => {
        const desc = (value as string) || ""
        return (
          <div className="max-w-xs truncate text-gray-950" title={desc}>
            {desc || "N/A"}
          </div>
        )
      }
    },
    {
      title: "Technologies",
      field: "technologies",
      width: "w-1/6",
      render: (value) => {
        const techs = (value as string[]) || []
        return (
          <div className="flex flex-wrap gap-1">
            {techs.slice(0, 3).map((tech, i) => (
              <span key={i} className="rounded bg-blue-50 px-1 py-0.5 text-xs text-blue-700">
                {tech}
              </span>
            ))}
            {techs.length > 3 && <span className="text-xs text-gray-500">+{techs.length - 3}</span>}
          </div>
        )
      }
    },
    {
      title: "Revenue",
      field: "annual_revenue",
      width: "w-1/6",
      render: (value) => {
        const revenue = value
        if (typeof revenue === "number") {
          return <div className="text-gray-950">${revenue.toLocaleString()}</div>
        }
        return <div className="text-gray-950">{revenue || "N/A"}</div>
      }
    },
    {
      title: "Employees",
      field: "total_employees",
      width: "w-1/6",
      render: (value) => <div className="text-gray-950">{value || "N/A"}</div>
    },
    {
      title: "HQ Location",
      field: "location",
      render: (value) => (
        <div className="flex items-center gap-2">
          {/* <MapPin className="mt-1 size-4 shrink-0" /> */}
          {value?.country || <span className="text-sm text-muted-foreground">Not Available</span>}
        </div>
      )
    },
    {
      title: "Employees",
      field: "company_headcount",
      width: "w-1/6",
      render: (_value, row) => {
        const headcount =
          row.company_headcount ??
          row.number_of_employees ??
          (row as unknown as Record<string, unknown> | null | undefined)?.["employee_count"] ??
          (row as unknown as Record<string, unknown> | null | undefined)?.["headcount"] ??
          null
        const text = headcount !== null && headcount !== undefined ? String(headcount) : "N/A"
        return (
          <div className="flex items-center gap-2">
            <span className="text-sm font-medium text-gray-950">{text}</span>
          </div>
        )
      }
    },
    {
      title: "Actions",
      field: "actions",
      width: "w-[140px]",
      render: (_: unknown, row: CompanyAttributes) => (
        <Button
          size="sm"
          variant="outline"
          className="h-8 gap-2 border-[#335CFF] px-[10px] py-1 text-xs text-[#335CFF] hover:text-[#335CFF]"
          onClick={() => {
            setInfoCompanyContact({
              _id: "",
              company: row.company,
              linkedin_url: row.company_linkedin_url ?? undefined,
              website: row.website
            })
            setInfoCompanyDetails(row)
          }}
        >
          View Company
        </Button>
      )
    }
  ]

  const companyColumnsConfig: ColumnOption[] = columnsConfigFromStore

  const visibleOrderedCompanyColumns: DataTableColumn<CompanyAttributes>[] = companyColumnsConfig
    .filter((c) => c.visible)
    .map((cfg) => (baseColumns || []).find((bc) => (bc?.field as string) === cfg.field))
    .filter((c): c is DataTableColumn<CompanyAttributes> => Boolean(c))

  // NEW: Clear selection when data changes (search, etc.)
  useEffect(() => {
    setSelectedCompanies([])
  }, [results, queryValue])

  return (
    // <Card className="h-full border-gray-200 bg-white backdrop-blur-sm dark:border-gray-800 dark:bg-gray-950">
    <CardContent className="h-full   ">
      <div className="flex h-full flex-col">
        {/* NEW: Show selected count */}
        {selectedCompanies.length > 0 && (
          <div className="mb-4 flex items-center gap-2">
            <span className="text-xs">{selectedCompanies.length} selected</span>
            <Button
              variant="default"
              className="h-[28px]  bg-[#476CFF19] px-1.5 py-1 text-sm font-medium text-[#335CFF] hover:bg-[#476CFF19] hover:text-[#335CFF]"
              onClick={() => setIsExportOpen(true)}
            >
              <DownloadIcon className="size-5 text-[#335CFF]" />
              Export as CSV
            </Button>
          </div>
        )}

        <div className="flex-1">
          <DataTable<CompanyAttributes>
            columns={visibleOrderedCompanyColumns}
            data={companies}
            loading={status === "loading"}
            fetching={status === "loading"}
            sortableFields={sortableFields}
            onSort={setSortSelected}
            sortSelected={sortSelected}
            searchPlaceholder="Search companies..."
            onSearch={handleSearchChange}
            searchValue={queryValue}
            showCheckbox={true}
            selectedItems={selectedCompanies}
            onItemSelect={handleCompanySelect}
            onSelectAll={handleSelectAll}
            onOpenEditColumns={() => dispatch(openColumnsModal("company"))}
            onRowClick={(row) => {
              dispatch(openContactInfoForCompany(row as CompanyAttributes))
            }}
            entityType="company"
            pagination={{
              currentPage: useAppSelector((state) => state.searchExecution.page) || 1,
              lastPage: Math.ceil(
                (useAppSelector((state) => state.searchExecution.total) || 0) / (useAppSelector((state) => state.searchExecution.count) || 50)
              ),
              onPageChange: (page) => {
                dispatch(setPage(page))
                dispatch(executeSearch())
              }
            }}
          />
        </div>
      </div>
      <ExportLeads
        open={isExportOpen}
        onClose={() => setIsExportOpen(false)}
        selectedCount={selectedCompanies.length}
        totalAvailable={searchMeta.total || 0}
        selectedIds={selectedCompanyExportIds}
        type="companies"
      />
      <Dialog open={!!infoCompanyContact} onOpenChange={(open) => !open && (setInfoCompanyContact(null), setInfoCompanyDetails(null))}>
        <DialogPortal>
          <DialogOverlay />
          <DialogPrimitive.Content className="fixed right-0 top-0 z-50 h-full w-[424px] border-l border-stone-200 bg-white shadow-xl  focus:outline-none">
            <DialogPrimitive.Title className="sr-only">Dialog</DialogPrimitive.Title>
            <DialogPrimitive.Description className="sr-only">Internal dialog content</DialogPrimitive.Description>
            <ContactInformation
              contact={infoCompanyContact}
              company={infoCompanyDetails}
              onClose={() => {
                setInfoCompanyContact(null)
                setInfoCompanyDetails(null)
              }}
              hideContactFields={true}
              hideCompanyActions={true}
            />
          </DialogPrimitive.Content>
        </DialogPortal>
      </Dialog>

      <EditColumn />
    </CardContent>
  )
}

export default CompaniesTable
