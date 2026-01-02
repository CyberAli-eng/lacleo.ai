// app/src/features/filters/adapter/querySerializer.ts
import { ActiveFilter } from "@/interface/filters/slice"

export interface FilterDSL {
  contact?: Record<string, ActiveFilter>
  company?: Record<string, ActiveFilter>
}

const LOOSE_TEXT_FILTERS = [
  "company_name",
  "business_category",
  "company_keywords",
  "keywords",
  "technologies",
  "company_technologies"
]

// Helper to sort string arrays in filter buckets for stable DSL and filter out invalid/empty filters
function stabilizeBucket(bucket: Record<string, ActiveFilter> | undefined): Record<string, ActiveFilter> {
  if (!bucket) return {}
  const out: Record<string, ActiveFilter> = {}
  Object.keys(bucket).forEach((key) => {
    const filter = { ...bucket[key] }

    // 1. Check for Presence
    const hasPresence = filter.presence && filter.presence !== "any"

    // 2. Check for Range (Emit if at least one bound exists)
    const hasRange =
      !!filter.range &&
      ((filter.range.min !== null && filter.range.min !== undefined) || (filter.range.max !== null && filter.range.max !== undefined))

    // 3. Check for Items
    const hasIncludes = Array.isArray(filter.include) && filter.include.length > 0
    const hasExcludes = Array.isArray(filter.exclude) && filter.exclude.length > 0

    // Logic: Do NOT emit filters with empty include/exclude arrays (unless range/presence active)
    if (!hasPresence && !hasRange && !hasIncludes && !hasExcludes) {
      return
    }

    // Stable sort
    if (Array.isArray(filter.include)) {
      filter.include = [...filter.include].sort()
    }
    if (Array.isArray(filter.exclude)) {
      filter.exclude = [...filter.exclude].sort()
    }

    // 4. Apollo Rule: Text-based filters use SHOULD clauses (mapped to 'or')
    if (LOOSE_TEXT_FILTERS.includes(key) && !filter.operator) {
      filter.operator = "or"
    }

    out[key] = filter
  })
  return out
}

export function buildFilterDSL(activeFilters: { contact: Record<string, ActiveFilter>; company: Record<string, ActiveFilter> }): FilterDSL {
  return {
    contact: stabilizeBucket(activeFilters.contact),
    company: stabilizeBucket(activeFilters.company)
  }
}
