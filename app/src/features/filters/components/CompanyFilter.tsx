import React, { useCallback, useMemo, useRef, useState } from "react"
import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import {
  addSelectedItem,
  removeSelectedItem,
  selectSelectedItems,
  selectSearchContext,
  setBucketOperator
} from "@/features/filters/slice/filterSlice"
import { useLazyCompaniesSuggestQuery } from "@/features/filters/slice/apiSlice"
import { Input } from "@/components/ui/input"
import { Button } from "@/components/ui/button"

type SuggestItem = { id: string | null; name: string | null; domain: string | null; employee_count: number | null }

const normalizeDomain = (d: string) =>
  d
    .trim()
    .toLowerCase()
    .replace(/^https?:\/\//, "")
    .replace(/^www\./, "")
const normalizeName = (s: string) => s.trim()

const CompanyFilter: React.FC = () => {
  const dispatch = useAppDispatch()
  const searchContext = useAppSelector(selectSearchContext)
  const selectedItems = useAppSelector(selectSelectedItems)
  const [triggerSuggest] = useLazyCompaniesSuggestQuery()

  const [q, setQ] = useState("")
  const [items, setItems] = useState<SuggestItem[]>([])
  const [operator, setOperator] = useState<"or" | "and">("or")
  const debounceRef = useRef<number | null>(null)

  const sectionIdName = searchContext === "companies" ? "company_name_company" : "company_name_contact"
  const sectionIdDomain = searchContext === "companies" ? "company_domain_company" : "company_domain_contact"

  const appliedNames = useMemo(() => selectedItems[sectionIdName] || [], [selectedItems, sectionIdName])
  const appliedDomains = useMemo(() => selectedItems[sectionIdDomain] || [], [selectedItems, sectionIdDomain])

  const onSearchChange = useCallback(
    (val: string) => {
      setQ(val)
      if (debounceRef.current) window.clearTimeout(debounceRef.current)
      debounceRef.current = window.setTimeout(async () => {
        const query = val.trim()
        if (!query) {
          setItems([])
          return
        }
        try {
          const res = await triggerSuggest({ q: query }).unwrap()
          setItems(res.data || [])
        } catch {
          setItems([])
        }
      }, 250)
    },
    [triggerSuggest]
  )

  const addToken = (name?: string | null, domain?: string | null) => {
    // Persist operator for DSL serialization
    dispatch(setBucketOperator({ bucket: "company", key: "company_names", operator }))
    dispatch(setBucketOperator({ bucket: "company", key: "domains", operator }))
    if (domain && domain.trim()) {
      const nd = normalizeDomain(domain)
      dispatch(
        addSelectedItem({ sectionId: sectionIdDomain, item: { id: `${sectionIdDomain}_${nd}`, name: nd, type: "include" }, isCompanyFilter: true })
      )
    }
    if (name && name.trim()) {
      const nn = normalizeName(name)
      dispatch(
        addSelectedItem({ sectionId: sectionIdName, item: { id: `${sectionIdName}_${nn}`, name: nn, type: "include" }, isCompanyFilter: true })
      )
    }
  }

  const removeToken = (sectionId: string, id: string) => {
    dispatch(removeSelectedItem({ sectionId, itemId: id, isCompanyFilter: true }))
  }

  const clearAll = () => {
    for (const it of appliedNames) removeToken(sectionIdName, it.id)
    for (const it of appliedDomains) removeToken(sectionIdDomain, it.id)
  }

  const safeItems = useMemo(
    () =>
      items.map((s) => ({
        ...s,
        employee_text: s.employee_count !== null && s.employee_count !== undefined ? s.employee_count.toLocaleString() : null
      })),
    [items]
  )

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center gap-2">
        <Input value={q} onChange={(e) => onSearchChange(e.target.value)} placeholder="Company name or domain" />
        <Button variant="outline" className="px-2 text-xs" onClick={clearAll}>
          Clear
        </Button>
        <div className="flex items-center gap-2">
          <span className="text-xs text-gray-600">Operator</span>
          <Button
            variant="outline"
            className="px-2 text-xs"
            onClick={() => {
              const next = operator === "or" ? "and" : "or"
              setOperator(next)
              dispatch(setBucketOperator({ bucket: "company", key: "company_names", operator: next }))
              dispatch(setBucketOperator({ bucket: "company", key: "domains", operator: next }))
            }}
          >
            {operator.toUpperCase()}
          </Button>
        </div>
      </div>

      {safeItems.length > 0 ? (
        <div className="rounded-md border p-2">
          {safeItems.map((s) => (
            <div key={`${s.id}_${s.domain || s.name}`} className="flex items-center justify-between py-1">
              <div className="flex items-center gap-2">
                <span className="text-sm font-medium text-gray-900">{s.name || s.domain}</span>
                {s.domain ? <span className="rounded-full bg-gray-100 px-2 text-xs text-gray-700">{s.domain}</span> : null}
                {s.employee_text ? <span className="text-xs text-gray-500">{s.employee_text} employees</span> : null}
              </div>
              <Button variant="outline" className="px-2 text-xs" onClick={() => addToken(s.name || null, s.domain || null)}>
                Add
              </Button>
            </div>
          ))}
        </div>
      ) : null}

      <div className="flex flex-wrap gap-2">
        {appliedNames.map((t: import("@/interface/filters/slice").SelectedFilter) => (
          <span key={t.id} className="flex items-center gap-1 rounded-full border px-2 py-1 text-xs">
            {t.name}
            <button onClick={() => removeToken(sectionIdName, t.id)}>×</button>
          </span>
        ))}
        {appliedDomains.map((t: import("@/interface/filters/slice").SelectedFilter) => (
          <span key={t.id} className="flex items-center gap-1 rounded-full border px-2 py-1 text-xs">
            {t.name}
            <button onClick={() => removeToken(sectionIdDomain, t.id)}>×</button>
          </span>
        ))}
      </div>
    </div>
  )
}

export default CompanyFilter
