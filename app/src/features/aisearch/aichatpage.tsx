import { MessageList } from "@/components/ui/aimessagelist"
import AISearchInput from "@/components/ui/aisearchinput"
import SearchConfirmation from "@/components/ui/searchconfirmation"
import React, { useCallback, useEffect, useRef, useState } from "react"
import { useDispatch, useSelector } from "react-redux"
// Import useNavigate
import { useNavigate } from "react-router-dom"
import { AiChatPageProps, Message, SearchCriterion, SearchConfirmationProps } from "./types"
import LacleoIcon from "../../static/media/avatars/lacleo_avatar.svg?react"
import LoaderLines from "../../static/media/icons/loader-lines.svg?react"
import {
  applyCriteria,
  finishCriteriaProcessing,
  finishSearch,
  startSearch,
  selectIsProcessingCriteria,
  selectSearchQuery,
  selectLastResultCount,
  setSemanticQuery
} from "./slice/searchslice"
import { useGetFiltersQuery } from "../filters/slice/apiSlice"
import { useTranslateQueryMutation } from "../searchTable/slice/apiSlice"
import { addSelectedItem, resetFilters, importFiltersFromDSL } from "../filters/slice/filterSlice"
import { IFilterGroup } from "@/interface/filters/filterGroup"

const LOADING_PHRASES: string[] = [
  "Curating filters for your search…",
  "Finding the most relevant results…",
  "Scanning through data…",
  "Sifting through possibilities…",
  "Matching the best options for you…",
  "Refining your filters…",
  "Analyzing search parameters…",
  "Narrowing it down for you…",
  "Exploring all possibilities…",
  "Gathering tailored suggestions…",
  "Almost there…",
  "Finalizing your matches…"
]

const AiChatPage: React.FC<AiChatPageProps> = ({ initialQuery, onBackToHome }) => {
  const dispatch = useDispatch()
  const navigate = useNavigate()
  const { currentData: filterGroups = [] as IFilterGroup[] } = useGetFiltersQuery()
  const [translateQuery] = useTranslateQueryMutation()

  // Local state for messages and UI
  const [messages, setMessages] = useState<Message[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [currentCriteria, setCurrentCriteria] = useState<SearchCriterion[]>([])
  const [loadingPhraseIndex, setLoadingPhraseIndex] = useState(0)
  const [inferredEntity, setInferredEntity] = useState<"contacts" | "companies" | null>(null)
  const [hasProcessedInitialQuery, setHasProcessedInitialQuery] = useState(false)

  // Redux selectors
  const isProcessingCriteria = useSelector(selectIsProcessingCriteria)
  const reduxSearchQuery = useSelector(selectSearchQuery)
  const lastResultCount = useSelector(selectLastResultCount)

  // Track the last processed initial query to prevent duplicates
  const lastProcessedInitialQuery = useRef<string | null>(null)
  const hasProcessedInitialRef = useRef(false)
  const isProcessingRef = useRef(false)
  const mountedRef = useRef(false)
  const initialQueryRef = useRef(initialQuery)
  const reduxSearchQueryRef = useRef(reduxSearchQuery)
  const processQueryRef = useRef<(q: string, isInitial?: boolean) => void>(() => { })

  const mapBackendFiltersToCriteria = useCallback((filters: Record<string, unknown>): SearchCriterion[] => {
    const out: SearchCriterion[] = []

    const push = (id: string, label: string, value: string) => {
      if (value && value.trim()) {
        out.push({ id, label, value: value.trim(), checked: true })
      }
    }

    // Helper to format ranges
    const formatRange = (val: { gte?: number; lte?: number; min?: number; max?: number }): string => {
      const gte = val.gte ?? val.min
      const lte = val.lte ?? val.max

      const toKMB = (n: number) => {
        if (n >= 1_000_000_000) return `${n / 1_000_000_000}B`
        if (n >= 1_000_000) return `${n / 1_000_000}M`
        if (n >= 1_000) return `${n / 1_000}K`
        return String(n)
      }
      if (gte !== undefined && lte !== undefined) return `${toKMB(gte)}-${toKMB(lte)}`
      if (gte !== undefined) return `${toKMB(gte)}+`
      if (lte !== undefined) return `<${toKMB(lte)}`
      return ""
    }

    for (const [key, val] of Object.entries(filters)) {
      if (!val) continue

      if (key === "title" || key === "job_title") {
        ; (Array.isArray(val) ? val : [val]).forEach((v) => push("job_title", "Job Title", String(v)))
      } else if (key === "departments") {
        ; (Array.isArray(val) ? val : [val]).forEach((v) => push("departments", "Department", String(v)))
      } else if (key === "seniority") {
        ; (Array.isArray(val) ? val : [val]).forEach((v) => push("seniority_level", "Seniority", String(v)))
      } else if (key === "company_names" || key === "company") {
        ; (Array.isArray(val) ? val : [val]).forEach((v) => push("company_brand", "Company", String(v)))
      } else if (key === "company_size" || key === "company.employee_count" || key === "employee_count") {
        push("employee_count", "Employee Count", formatRange(val as { gte?: number; lte?: number }))
      } else if (key === "revenue" || key === "company.revenue" || key === "annual_revenue") {
        push("company_revenue", "Company Revenue", formatRange(val as { gte?: number; lte?: number }))
      } else if (key === "years_experience" || key === "years_of_experience") {
        push("years_of_experience", "Experience", formatRange(val as { gte?: number; lte?: number }))
      } else if (key === "location") {
        // Handle nested location object { country: [], state: [], city: [] }
        const loc = val as { country?: string[]; state?: string[]; city?: string[] }
        if (loc.country) loc.country.forEach((v) => push("company_location", "Location (Country)", v))
        if (loc.state) loc.state.forEach((v) => push("company_location", "Location (State)", v))
        if (loc.city) loc.city.forEach((v) => push("contact_city", "Location (City)", v))
      } else if (key === "location.country") {
        ; (Array.isArray(val) ? val : [val]).forEach((v) => push("company_location", "Location", String(v)))
      } else if (key === "skills" || key === "technologies") {
        ; (Array.isArray(val) ? val : [val]).forEach((v) => push("technologies", "Skills/Tech", String(v)))
      } else if (key === "company_keywords") {
        ; (Array.isArray(val) ? val : [val]).forEach((v) => push("company_keywords", "Keywords", String(v)))
      } else if (key === "industry" || key === "company.industry" || key === "industries") {
        ; (Array.isArray(val) ? val : [val]).forEach((v) => push("industry", "Industry", String(v)))
      }
    }

    if (out.length === 0) {
      return [{ id: "general", label: "Search Type", value: "General Search", checked: true }]
    }
    return out
  }, [])

  const addMessage = useCallback((message: Omit<Message, "id" | "timestamp">) => {
    const newMessage: Message = {
      ...message,
      id: crypto.randomUUID(),
      timestamp: new Date()
    }

    setMessages((prev) => [...prev, newMessage])
    return newMessage
  }, [])

  const disablePreviousConfirmations = useCallback(() => {
    setMessages((prev) =>
      prev.map((message) => {
        const componentNode = message.component
        if (componentNode && React.isValidElement(componentNode) && componentNode.type === SearchConfirmation) {
          const element = componentNode as React.ReactElement<SearchConfirmationProps>
          return {
            ...message,
            component: React.cloneElement<SearchConfirmationProps>(element, { disabled: true })
          }
        }
        return message
      })
    )
  }, [])

  // Memoized handlers
  const handleCriterionChange = useCallback((id: string, checked: boolean) => {
    setCurrentCriteria((prev) => prev.map((c) => (c.id === id ? { ...c, checked } : c)))
  }, [])

  const handleApplySearch = useCallback(
    async (criteria: SearchCriterion[]) => {
      if (!mountedRef.current) return

      setIsLoading(true)
      dispatch(applyCriteria(criteria))

      try {
        dispatch(resetFilters())

        const allFilters = (filterGroups || []).flatMap((g) => g.filters)

        criteria
          .filter((c) => c.checked)
          .forEach((c) => {
            let filterId = c.id.toLowerCase()

            if (filterId === "revenue" || filterId === "annual_revenue") filterId = "company_revenue"
            if (filterId === "headcount" || filterId === "company_headcount") filterId = "employee_count"
            if (filterId === "experience") filterId = "years_of_experience"

            const matched =
              allFilters.find((f) => f.name.toLowerCase() === c.label.toLowerCase()) ||
              allFilters.find((f) => f.id.toLowerCase() === filterId) ||
              allFilters.find((f) => f.id.toLowerCase() === c.id.toLowerCase())

            if (matched) {
              dispatch(
                addSelectedItem({
                  sectionId: matched.id,
                  item: { id: c.value, name: c.value, type: "include" }
                })
              )
            } else if (c.id === "company_keywords") {
              // Ensure keywords are added even if not matched by ID lookup (since it uses sub-filter IDs)
              dispatch(
                addSelectedItem({
                  sectionId: "company_keywords",
                  item: { id: c.value, name: c.value, type: "include" },
                  isCompanyFilter: true
                })
              )
            }
          })

        if (!mountedRef.current) return

        if (inferredEntity === "contacts") {
          navigate("/app/search/contacts")
        } else if (inferredEntity === "companies") {
          navigate("/app/search/companies")
        } else {
          const hasContactFilters = criteria.some((c) => ["job_title", "departments", "seniority"].includes(c.id))
          if (hasContactFilters) navigate("/app/search/contacts")
          else navigate("/app/search/companies")
        }

        dispatch(finishSearch())
      } catch (error) {
        console.error("Error applying search:", error)
        if (mountedRef.current) {
          addMessage({
            type: "ai",
            content: "Sorry, there was an error executing your search. Please try again."
          })
        }
      } finally {
        if (mountedRef.current) {
          setIsLoading(false)
        }
      }
    },
    [addMessage, dispatch, filterGroups, navigate, inferredEntity]
  )

  useEffect(() => {
    if (!isLoading) {
      setLoadingPhraseIndex(0)
      return
    }
    let nextIndex = 0
    setLoadingPhraseIndex(0)
    const intervalId = setInterval(() => {
      nextIndex = (nextIndex + 1) % LOADING_PHRASES.length
      setLoadingPhraseIndex(nextIndex)
    }, 1500)
    return () => clearInterval(intervalId)
  }, [isLoading])

  const processQuery = useCallback(
    async (query: string, isInitial = false) => {
      if (isProcessingRef.current) return

      if (isInitial && lastProcessedInitialQuery.current === query) return

      isProcessingRef.current = true
      if (isInitial) {
        lastProcessedInitialQuery.current = query
      }

      setIsLoading(true)

      // Construct message history for the AI
      // We need to map our local Message[] (which has IDs, timestamps, components) 
      // to the API's simple format { role, content }
      const historyContext = messages
        .filter(m => m.type === 'user' || m.type === 'ai')
        .map(m => ({
          role: m.type === 'user' ? 'user' : 'assistant',
          content: m.content
        }));

      // Append the new user query to the history
      const newHistory = [...historyContext, { role: 'user', content: query }];

      addMessage({
        type: "user",
        content: query
      })

      try {
        const response = await translateQuery({
          messages: newHistory,
          context: { lastResultCount: typeof lastResultCount === 'number' ? lastResultCount : null }
        }).unwrap()

        if (!mountedRef.current) return

        const criteria = mapBackendFiltersToCriteria(response.filters)
        setInferredEntity(response.entity)
        setCurrentCriteria(criteria)

        const dsl: { contact: Record<string, unknown>; company: Record<string, unknown> } = { contact: {}, company: {} }
        const ensureInclude = (bucket: Record<string, unknown>, key: string, value: string) => {
          const existing = (bucket[key] as { include?: string[] } | undefined) || {}
          const include = Array.isArray(existing.include) ? existing.include : []
          bucket[key] = { ...existing, include: Array.from(new Set([...include, value])) }
        }
        const setRange = (bucket: Record<string, unknown>, key: string, val: unknown) => {
          const range: { min?: number; max?: number } = {}
          if (val && typeof val === "object") {
            const obj = val as { gte?: number; lte?: number; min?: number; max?: number }
            if (typeof obj.gte === "number") range.min = obj.gte
            if (typeof obj.lte === "number") range.max = obj.lte
            if (typeof obj.min === "number") range.min = obj.min
            if (typeof obj.max === "number") range.max = obj.max
          }
          bucket[key] = range
        }

        for (const [key, val] of Object.entries(response.filters as Record<string, unknown>)) {
          if (!val) continue
          if (key === "title" || key === "job_title") {
            ; (Array.isArray(val) ? val : [val]).forEach((v) => ensureInclude(dsl.contact, "job_title", String(v)))
          } else if (key === "departments") {
            ; (Array.isArray(val) ? val : [val]).forEach((v) => ensureInclude(dsl.contact, "departments", String(v)))
          } else if (key === "seniority") {
            ; (Array.isArray(val) ? val : [val]).forEach((v) => ensureInclude(dsl.contact, "seniority", String(v)))
          } else if (key === "years_experience" || key === "years_of_experience") {
            setRange(dsl.contact, "years_of_experience", val)
          } else if (key === "location") {
            const loc = val as { country?: string[]; state?: string[]; city?: string[] }
            if (loc.country) loc.country.forEach((v) => ensureInclude(dsl.company, "locations", v))
            if (loc.state) loc.state.forEach((v) => ensureInclude(dsl.company, "locations", v))
            if (loc.city) loc.city.forEach((v) => ensureInclude(dsl.contact, "city", v))
          } else if (key === "location.country") {
            ; (Array.isArray(val) ? val : [val]).forEach((v) => ensureInclude(dsl.company, "locations", String(v)))
          } else if (key === "location.city" || key === "city") {
            ; (Array.isArray(val) ? val : [val]).forEach((v) => ensureInclude(dsl.contact, "city", String(v)))
          } else if (key === "industry" || key === "company.industry" || key === "industries") {
            ; (Array.isArray(val) ? val : [val]).forEach((v) => ensureInclude(dsl.company, "industries", String(v)))
          } else if (key === "skills" || key === "technologies" || key === "company.technologies") {
            ; (Array.isArray(val) ? val : [val]).forEach((v) => ensureInclude(dsl.company, "technologies", String(v)))
          } else if (key === "company_size" || key === "company.employee_count" || key === "employee_count") {
            setRange(dsl.company, "employee_count", val)
          } else if (key === "revenue" || key === "company.revenue" || key === "annual_revenue") {
            setRange(dsl.company, "annual_revenue", val)
          } else if (key === "company_keywords") {
            ; (Array.isArray(val) ? val : [val]).forEach((v) => ensureInclude(dsl.company, "company_keywords", String(v)))
          } else if (key === "company_names" || key === "company") {
            // ...
          }
        }
        dispatch(importFiltersFromDSL(dsl))

        // Dispatch that criteria processing is finished
        dispatch(finishCriteriaProcessing())

        // Create a stable component instance to prevent re-renders
        const confirmationComponent = (
          <SearchConfirmation criteria={criteria} onApply={handleApplySearch} onCriterionChange={handleCriterionChange} disabled={false} />
        )

        // Add AI summary explanation if available
        if (response.summary) {
          addMessage({
            type: "ai",
            content: response.summary
          })
        }

        // Set Semantic Query if available (for vector search)
        if (response.semantic_query) {
          dispatch(setSemanticQuery(response.semantic_query))
        } else {
          dispatch(setSemanticQuery(null))
        }

        // Add confirmation message with component
        addMessage({
          type: "system",
          content: "I've analyzed your request. Please confirm these filters:",
          component: confirmationComponent
        })
      } catch (error) {
        console.error("Error processing query:", error)
        if (mountedRef.current) {
          addMessage({
            type: "ai",
            content: "Sorry, I encountered an error interpreting your request. Please try rephrasing."
          })
        }
      } finally {
        if (mountedRef.current) {
          setIsLoading(false)
        }
        isProcessingRef.current = false
      }
    },
    [addMessage, translateQuery, mapBackendFiltersToCriteria, handleApplySearch, handleCriterionChange, dispatch]
  )

  // Keep ref in sync with latest processQuery
  useEffect(() => {
    processQueryRef.current = processQuery
  }, [processQuery])

  // Handle initial query processing — run exactly once per distinct initialQuery
  useEffect(() => {
    mountedRef.current = true
    const redirecting = typeof window !== "undefined" ? sessionStorage.getItem("authRedirectInProgress") === "1" : false
    if (initialQuery && !hasProcessedInitialRef.current && !redirecting) {
      hasProcessedInitialRef.current = true
      processQuery(initialQuery, true)
      setHasProcessedInitialQuery(true)
    }
    return () => {
      mountedRef.current = false
    }
  }, [initialQuery, hasProcessedInitialQuery, processQuery])

  // Reset when initialQuery changes to a new value
  useEffect(() => {
    const queryToCheck = initialQuery || reduxSearchQuery
    if (queryToCheck && lastProcessedInitialQuery.current && lastProcessedInitialQuery.current !== queryToCheck) {
      lastProcessedInitialQuery.current = null
      isProcessingRef.current = false
    }
  }, [initialQuery, reduxSearchQuery])

  const handleNewSearch = useCallback(
    (query: string) => {
      // Disable previous confirmation Apply buttons
      disablePreviousConfirmations()
      // Dispatch Redux action for new search
      dispatch(startSearch(query))
      // Prevent duplicate processing if same as initial processed query
      if (lastProcessedInitialQuery.current && lastProcessedInitialQuery.current === query) {
        return
      }
      // Reset processing flag for new searches
      isProcessingRef.current = false
      processQuery(query, false)
    },
    [processQuery, dispatch, disablePreviousConfirmations]
  )

  return (
    <div className="flex h-full flex-1 flex-col">
      {/* Messages Container */}
      <div className="flex max-h-[calc(100vh-363px)] min-h-0 flex-1 flex-col overflow-auto">
        <MessageList messages={messages} />

        {/* Loading indicator */}
        {!!isLoading && (
          <div className="ml-6 flex items-end gap-[10px]">
            <LacleoIcon />
            <div className="rounded-[12px] border border-[#EBEBEB] px-6 py-[18px]">
              <span className="flex items-center gap-2 text-base font-normal text-[#5C5C5C]">
                <LoaderLines className="size-8" />
                {LOADING_PHRASES[loadingPhraseIndex]}
              </span>
            </div>
          </div>
        )}
      </div>

      {/* Search Input at bottom */}
      <div className="bg-white p-4">
        <AISearchInput onSearch={handleNewSearch} placeholder="Ask a follow-up question or start a new search..." disabled={isLoading} />
      </div>
    </div>
  )
}

export default AiChatPage
