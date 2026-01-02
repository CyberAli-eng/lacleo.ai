import useDocumentTitle from "@/app/hooks/documentTitleHook"
import { useAppSelector } from "@/app/hooks/reduxHooks"
import { buildSearchUrl } from "@/app/utils/searchUtils"
import { Avatar } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { CardContent } from "@/components/ui/card"
import ContactCard from "@/components/ui/contactcard"
import ContactInformation from "@/components/ui/contactinformation"
import { Dialog, DialogOverlay, DialogPortal } from "@/components/ui/dialog"
import EditColumn from "@/components/ui/modals/editcolumn"
import type { ColumnOption } from "@/features/searchTable/slice/columnsSlice"
import { openColumnsModal } from "@/features/searchTable/slice/columnsSlice"
import { useAppDispatch } from "@/app/hooks/reduxHooks"
import ExportLeads from "@/components/ui/modals/exportleads"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "@/components/ui/tooltip"
import { HoverCard } from "@/components/ui/hovercard"
import { DataTableColumn } from "@/interface/searchTable/components"
import { ContactAttributes, SearchApiResponse } from "@/interface/searchTable/search"
import * as DialogPrimitive from "@radix-ui/react-dialog"
import { Eye, Mail, Phone, User2 } from "lucide-react"
import { useCallback, useEffect, useMemo, useState } from "react"
import { useLocation } from "react-router-dom"
import DownloadIcon from "../../static/media/icons/download-icon.svg?react"
import { setLastResultCount, selectSemanticQuery, selectShowResults, setShowResults } from "../aisearch/slice/searchslice"
import { DataTable } from "./baseDataTable"
import { useCompanyLogoQuery } from "./slice/apiSlice"
import { CompanyAttributes } from "@/interface/searchTable/search"
import { openContactInfoForContact } from "./slice/contactInfoSlice"
import { useDebounce } from "@/app/hooks/useDebounce"
import { selectSearchResults, selectSearchStatus, selectSearchMeta, setSort, executeSearch, setPage } from "@/features/searchExecution/slice/searchExecutionSlice"

const ContactCompanyCell = ({ row }: { row: ContactAttributes }) => {
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

// Component to calculate and display phone count (including company phone)
const PhoneCountCell = ({ contact }: { contact: ContactAttributes }) => {
  // Count contact's own phones
  let phoneCount = 0

  if (Array.isArray(contact.phone_numbers) && contact.phone_numbers.length > 0) {
    phoneCount += contact.phone_numbers.length
  } else if (contact.phone_number) {
    phoneCount += 1
  } else if ((contact as unknown as { has_contact_phone?: boolean })?.has_contact_phone === true) {
    phoneCount += 1
  }

  // Do not perform extra network calls per row; keep deterministic and cheap

  return phoneCount
}

export function ContactsTable() {
  const location = useLocation()
  const navState = (location.state as { fromAi?: boolean } | null) || null
  const fromAi = Boolean(navState?.fromAi)
  const dispatch = useAppDispatch()
  const columnsConfigFromStore = useAppSelector((s) => s.columns.configs.contact)
  useDocumentTitle("Search People")
  // Table consumes executed results only

  // const globalSearchQuery = useAppSelector(selectSearchQuery)
  // Removed local query state
  const queryValue = useAppSelector((state) => state.searchExecution.searchTerm) || ""

  const handleSearchChange = (val: string) => {
    dispatch(setSearchTerm(val))
  }

  // Removed local sort state
  const sortSelected = useAppSelector((state) => state.searchExecution.sort)
  const setSortSelected = (sort: string[]) => dispatch(setSort(sort))
  const [infoContact, setInfoContact] = useState<ContactAttributes | null>(null)
  const [isExportOpen, setIsExportOpen] = useState(false)

  // NEW: State for checkbox selection
  const [selectedContacts, setSelectedContacts] = useState<string[]>([])

  // NEW: Handle individual contact selection
  const handleContactSelect = useCallback((contactId: string) => {
    setSelectedContacts((prev) => (prev.includes(contactId) ? prev.filter((id) => id !== contactId) : [...prev, contactId]))
  }, [])

  // NEW: Handle select all contacts
  const handleSelectAll = (checked: boolean) => {
    if (checked) {
      const allContactIds = (results as Array<{ id: string; attributes: ContactAttributes }>).map((r) => r.id || "")
      setSelectedContacts(allContactIds.filter((id) => id !== ""))
    } else {
      setSelectedContacts([])
    }
  }

  const semanticQuery = useAppSelector(selectSemanticQuery)

  // Query params are built by the execution engine only when user runs search

  // Pagination is controlled by execution engine

  const showResults = useAppSelector(selectShowResults)
  const results = useAppSelector(selectSearchResults)
  const status = useAppSelector(selectSearchStatus)
  const searchMeta = useAppSelector(selectSearchMeta)

  const sortableFields = ["full_name", "website", "title", "linkedin_url", "company"]

  // Enhanced columns with better styling and icons
  const baseColumns: DataTableColumn<ContactAttributes>[] = [
    {
      title: "Contact Name",
      field: "full_name",
      width: "w-1/3",
      render: (value, row) => (
        <TooltipProvider>
          <div className="flex items-center gap-3">
            <div>
              {value ? <div className="text-sm font-normal">{value}</div> : <span className="text-sm text-muted-foreground">Not Available</span>}
              {!!row.email && <div className="text-xs text-muted-foreground">{row.email}</div>}
            </div>
          </div>
        </TooltipProvider>
      )
    },

    {
      title: "Company",
      field: "company",
      width: "w-1/4",
      render: (_value, row) => <ContactCompanyCell row={row} />
    },
    {
      title: "Job Profile",
      field: "title",
      width: "w-1/4",
      render: (value) => <div className="flex items-center gap-2">{value || "N/A"}</div>
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
      title: "Work Email",
      field: "work_email",
      width: "w-1/6",
      render: (value) => <div className="text-gray-950">{value || "N/A"}</div>
    },
    {
      title: "Personal Email",
      field: "personal_email",
      width: "w-1/6",
      render: (value) => <div className="text-gray-950">{value || "N/A"}</div>
    },
    {
      title: "Mobile Phone",
      field: "mobile_phone",
      width: "w-1/6",
      render: (value) => <div className="text-gray-950">{value || "N/A"}</div>
    },
    {
      title: "Business Category",
      field: "business_category",
      width: "w-1/6",
      render: (value) => <div className="text-gray-950">{value || "N/A"}</div>
    },
    {
      title: "Employees",
      field: "number_of_employees",
      width: "w-1/6",
      render: (value) => <div className="text-gray-950">{value || "N/A"}</div>
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
      title: "Contact",
      field: "contact",
      width: "w-1/4",
      render: (_: unknown, row: ContactAttributes) => {
        // Count emails for this specific contact
        // const phoneCount = row.phone_number ? 1 : 0
        // const emailCount = row.email ? 1 : 0
        const emailCount = Array.isArray(row.emails) ? row.emails.length : 0

        return (
          <HoverCard
            className=""
            contentClassName="z-50 rounded-none bg-white p-0 shadow-none border-none translate-x-0 mt-0 "
            trigger={
              <div className="flex items-center gap-1">
                <Button className="rounded-lg border bg-transparent px-1.5 py-1 hover:bg-transparent">
                  <Phone className="size-3 text-[#5C5C5C]" />
                  <span className="rounded-full bg-[#EBEBEB] px-2 py-0.5 text-[11px] font-medium  text-gray-950">
                    <PhoneCountCell contact={row} />
                  </span>
                </Button>
                <Button className="rounded-lg border bg-transparent px-1.5 py-1 hover:bg-transparent">
                  <Mail className="size-3 text-[#5C5C5C]" />
                  <span className="rounded-full bg-[#EBEBEB] px-2 py-0.5 text-[11px] font-medium text-gray-950">{emailCount}</span>
                </Button>
              </div>
            }
            content={<ContactCard contact={row} />}
            side="left"
          />
        )
      }
    },

    {
      title: "Actions",
      field: "actions",
      width: "w-1/5",
      render: (_: unknown, row: ContactAttributes) => (
        <TooltipProvider>
          <Tooltip delayDuration={200}>
            <TooltipTrigger asChild>
              <Button size="sm" className="h-8 gap-2 bg-[#335CFF] text-xs hover:bg-[#335CFF]" onClick={() => setInfoContact(row)}>
                <Eye className="size-3" />
                Reveal info
              </Button>
            </TooltipTrigger>
            <TooltipContent>Reveal contact (Email: 1, Phone: 4 credits)</TooltipContent>
          </Tooltip>
        </TooltipProvider>
      )
    }
  ]

  const contactColumnsConfig: ColumnOption[] = columnsConfigFromStore

  const visibleOrderedContactColumns: DataTableColumn<ContactAttributes>[] = contactColumnsConfig
    .filter((c) => c.visible)
    .map((cfg) => (baseColumns || []).find((bc) => (bc?.field as string) === cfg.field))
    .filter((c): c is DataTableColumn<ContactAttributes> => Boolean(c))

  // NEW: Clear selection when data changes (search, etc.)
  useEffect(() => {
    setSelectedContacts([])
  }, [results, queryValue])

  // Do not auto-search on typing; leave execution to explicit actions

  return (
    // <Card className="h-full border-gray-200 bg-white backdrop-blur-sm dark:border-gray-800 dark:bg-gray-950">
    <CardContent className="h-full p-0">
      <div className="flex h-full flex-col">
        {/* NEW: Show selected count */}
        {selectedContacts.length > 0 && (
          <div className="mb-4 flex items-center gap-2">
            <span className="text-sm font-medium text-[#262626]">{selectedContacts.length} Selected</span>
            <Button
              variant="default"
              className="h-[28px] bg-[#476CFF19] px-1.5 py-1 text-sm font-medium text-[#335CFF] hover:bg-[#476CFF19] hover:text-[#335CFF]"
              onClick={() => setIsExportOpen(true)}
            >
              <DownloadIcon className="size-5 text-[#335CFF]" />
              Export as CSV
            </Button>
          </div>
        )}

        <div className="flex-1">
          <DataTable<ContactAttributes>
            columns={visibleOrderedContactColumns}
            data={results as Array<{ id: string; attributes: ContactAttributes; highlights: Record<string, string[]> | null }>}
            loading={status === "loading"}
            fetching={status === "loading"}
            sortableFields={sortableFields}
            onSort={setSortSelected}
            sortSelected={sortSelected}
            searchPlaceholder="Search contacts..."
            onSearch={handleSearchChange}
            searchValue={queryValue}
            showCheckbox={true}
            selectedItems={selectedContacts}
            onItemSelect={handleContactSelect}
            onSelectAll={handleSelectAll}
            onOpenEditColumns={() => dispatch(openColumnsModal("contact"))}
            onRowClick={(row) => {
              dispatch(openContactInfoForContact(row as ContactAttributes))
            }}
            entityType="contact"
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
      <Dialog open={!!infoContact} onOpenChange={(open) => !open && setInfoContact(null)}>
        <DialogPortal>
          <DialogOverlay />
          <DialogPrimitive.Content className="fixed right-0 top-0 z-50 h-full w-[424px] border-l border-stone-200 bg-white shadow-xl focus:outline-none">
            <DialogPrimitive.Title className="sr-only">Contact Details</DialogPrimitive.Title>
            <DialogPrimitive.Description className="sr-only">Detailed information about the selected contact.</DialogPrimitive.Description>
            <ContactInformation contact={infoContact} onClose={() => setInfoContact(null)} company={null} />
          </DialogPrimitive.Content>
        </DialogPortal>
      </Dialog>
      <ExportLeads
        open={isExportOpen}
        onClose={() => setIsExportOpen(false)}
        selectedCount={selectedContacts.length}
        totalAvailable={searchMeta.total || 0}
        selectedIds={selectedContacts}
        type="contacts"
      />

      <EditColumn />
    </CardContent>
  )
}

export default ContactsTable
