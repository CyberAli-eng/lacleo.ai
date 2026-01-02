import React, { useCallback, useEffect, useRef, useState } from "react"
import { useDispatch, useSelector } from "react-redux"
import { useNavigate } from "react-router-dom"
import { motion, AnimatePresence } from "framer-motion"
import { toast } from "sonner"
import { Loader2, Sparkles, Send, CheckCircle2, ArrowRight } from "lucide-react"

// Redux
import {
  finishCriteriaProcessing,
  finishSearch,
  selectLastResultCount,
  setSemanticQuery,
  setShowResults,
} from "./slice/searchslice"
import { importFiltersFromDSL, resetFilters } from "../filters/slice/filterSlice"
import { useTranslateQueryMutation } from "../searchTable/slice/apiSlice"

// Components
import AISearchInput from "@/components/ui/aisearchinput"
import LacleoIcon from "../../static/media/avatars/lacleo_avatar.svg?react"

// Types
import { AiChatPageProps } from "./types"

type ChatMessage = {
  id: string
  role: "user" | "ai"
  content: string | React.ReactNode
  timestamp: Date
  filters?: any // The proposed DSL filters if present
  entity?: "contacts" | "companies"
}

const AiChatPage: React.FC<AiChatPageProps> = ({ initialQuery }) => {
  const dispatch = useDispatch()
  const navigate = useNavigate()
  const [translateQuery] = useTranslateQueryMutation()
  const lastResultCount = useSelector(selectLastResultCount)

  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const hasProcessedInitial = useRef(false)

  // Auto-scroll to bottom
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" })
  }, [messages, isLoading])

  // Handle Initial Query if provided via navigation/props
  useEffect(() => {
    if (initialQuery && !hasProcessedInitial.current) {
      hasProcessedInitial.current = true
      handleSearch(initialQuery)
    }
  }, [initialQuery])

  const handleSearch = async (query: string) => {
    if (!query.trim()) return

    setIsLoading(true)
    const newUserMsg: ChatMessage = {
      id: crypto.randomUUID(),
      role: "user",
      content: query,
      timestamp: new Date()
    }
    setMessages(prev => [...prev, newUserMsg])

    try {
      // History context for API
      const history = messages
        .filter(m => typeof m.content === 'string')
        .map(m => ({ role: m.role === "user" ? "user" : "assistant", content: m.content as string }))

      // Call Backend (TinyLlama / Ollama)
      const res = await translateQuery({
        query,
        messages: history,
        context: { lastResultCount }
      }).unwrap()

      const dsl = res.filters
      const summary = res.summary || "I've generated filters based on your request."
      const entity = res.entity

      // Add AI Response
      const newAiMsg: ChatMessage = {
        id: crypto.randomUUID(),
        role: "ai",
        content: summary,
        timestamp: new Date(),
        filters: dsl,
        entity: entity
      }
      setMessages(prev => [...prev, newAiMsg])

      if (res.semantic_query) {
        dispatch(setSemanticQuery(res.semantic_query))
      }

    } catch (err) {
      console.error(err)
      toast.error("Failed to process request", { description: "Please try again or refine your query." })
      setMessages(prev => [...prev, {
        id: crypto.randomUUID(),
        role: "ai",
        content: "I encountered an error connecting to the AI service. Please try manually selecting filters.",
        timestamp: new Date()
      }])
    } finally {
      setIsLoading(false)
    }
  }

  const applyFilters = (dsl: any, entity: "contacts" | "companies") => {
    dispatch(resetFilters())
    dispatch(importFiltersFromDSL(dsl)) // Maps DSL to Redux State
    dispatch(finishCriteriaProcessing())
    dispatch(finishSearch())
    dispatch(setShowResults(true))

    // Navigate to correct entity view
    const path = entity === "companies" ? "/app/search/companies" : "/app/search/contacts"
    navigate(path, { state: { fromAi: true } })
    toast.success("Filters Applied", { description: "Switching to results view." })
  }

  return (
    <div className="flex h-full w-full flex-col bg-gray-50/50 dark:bg-gray-900/50">
      {/* Chat Area */}
      {/* Chat Area */}
      <div className="flex-1 overflow-y-auto px-4 py-6 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-gray-200">
        <div className="space-y-6">
          {messages.length === 0 && !isLoading && (
            <div className="mt-12 flex flex-col items-center justify-center space-y-4 text-center">
              <div className="flex size-14 items-center justify-center rounded-2xl bg-blue-100 dark:bg-blue-900/30">
                <Sparkles className="size-7 text-blue-600 dark:text-blue-400" />
              </div>
              <div>
                <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">AI Search Assistant</h2>
                <p className="max-w-xs mx-auto text-sm text-gray-500 dark:text-gray-400">
                  Ask me to find anything. For example: "Find software engineers in London"
                </p>
              </div>
            </div>
          )}

          <AnimatePresence initial={false}>
            {messages.map((msg) => (
              <motion.div
                key={msg.id}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3 }}
                className={`flex w-full ${msg.role === "user" ? "justify-end" : "justify-start"}`}
              >
                <div className={`flex max-w-[90%] items-start gap-3 ${msg.role === "user" ? "flex-row-reverse" : "flex-row"}`}>

                  {/* Avatar */}
                  <div className={`flex size-7 shrink-0 items-center justify-center rounded-full  ${msg.role === "ai" ? "bg-white shadow-sm" : "bg-blue-600"}`}>
                    {msg.role === "ai" ? <LacleoIcon className="size-4" /> : <div className="text-[10px] font-bold text-white">ME</div>}
                  </div>

                  {/* Message Bubble */}
                  <div className="flex flex-col gap-2 min-w-0"> {/* min-w-0 to prevent flex blowout */}
                    <div
                      className={`rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm
                      ${msg.role === "user"
                          ? "bg-blue-600 text-white"
                          : "bg-white text-gray-800 dark:bg-gray-800 dark:text-gray-100"}`}
                    >
                      {msg.content}
                    </div>

                    {/* Filter Preview Card */}
                    {msg.role === "ai" && msg.filters && (
                      <motion.div
                        initial={{ opacity: 0, scale: 0.95 }}
                        animate={{ opacity: 1, scale: 1 }}
                        className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                      >
                        <div className="bg-gray-50 px-3 py-2 border-b border-gray-100 dark:bg-gray-800/50 dark:border-gray-700">
                          <span className="text-[10px] uppercase font-bold tracking-wider text-gray-500">Proposed Filters</span>
                        </div>
                        <div className="p-3">
                          <FilterSummaryPreview dsl={msg.filters} />
                        </div>
                        <div className="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/50">
                          <span className="text-xs text-gray-500">
                            Target: <span className="font-medium text-gray-900 dark:text-gray-100 capitalize">{msg.entity || 'Mixed'}</span>
                          </span>
                          <button
                            onClick={() => applyFilters(msg.filters, msg.entity || "contacts")}
                            className="flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-emerald-700 active:scale-95"
                          >
                            Apply <ArrowRight className="size-3" />
                          </button>
                        </div>
                      </motion.div>
                    )}
                  </div>
                </div>
              </motion.div>
            ))}
          </AnimatePresence>

          {isLoading && (
            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="flex items-start gap-3">
              <div className="flex size-7 shrink-0 items-center justify-center rounded-full bg-white shadow-sm">
                <LacleoIcon className="size-4" />
              </div>
              <div className="flex items-center gap-2 rounded-2xl bg-white px-4 py-3 shadow-sm">
                <Loader2 className="size-4 animate-spin text-blue-600" />
                <span className="text-sm text-gray-500">Thinking...</span>
              </div>
            </motion.div>
          )}
          <div ref={messagesEndRef} />
        </div>
      </div>

      {/* Input Area */}
      <div className="border-t border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
        <AISearchInput
          onSearch={handleSearch}
          placeholder="Ask AI..."
          disabled={isLoading}
          className="shadow-sm"
        />
      </div>
    </div>
  )
}

// Minimal Component to visualizing DSL JSON nicely
const FilterSummaryPreview = ({ dsl }: { dsl: any }) => {
  const contact = dsl?.contact || {}
  const company = dsl?.company || {}

  const hasFilters = Object.keys(contact).length > 0 || Object.keys(company).length > 0
  if (!hasFilters) return <div className="text-sm text-gray-500">No specific filters detected.</div>

  const renderValue = (val: any) => {
    if (val?.include) return val.include.join(", ")
    if (val?.range) {
      const { min, max } = val.range
      if (min && max) return `${min} - ${max}`
      if (min) return `${min}+`
      if (max) return `< ${max}`
    }
    return JSON.stringify(val)
  }

  return (
    <div className="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
      {Object.keys(contact).length > 0 && (
        <div>
          <h4 className="mb-2 font-medium text-blue-600">Contact Filters</h4>
          <ul className="space-y-1">
            {Object.entries(contact).map(([k, v]) => (
              <li key={k} className="flex gap-2">
                <CheckCircle2 className="mt-0.5 size-3 text-emerald-500 shrink-0" />
                <span className="text-gray-600 dark:text-gray-300">
                  <strong className="text-gray-800 dark:text-gray-200 capitalize">{k.replace(/_/g, " ")}:</strong> {renderValue(v)}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
      {Object.keys(company).length > 0 && (
        <div>
          <h4 className="mb-2 font-medium text-indigo-600">Company Filters</h4>
          <ul className="space-y-1">
            {Object.entries(company).map(([k, v]) => (
              <li key={k} className="flex gap-2">
                <CheckCircle2 className="mt-0.5 size-3 text-emerald-500 shrink-0" />
                <span className="text-gray-600 dark:text-gray-300">
                  <strong className="text-gray-800 dark:text-gray-200 capitalize">{k.replace(/_/g, " ")}:</strong> {renderValue(v)}
                </span>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

export default AiChatPage
