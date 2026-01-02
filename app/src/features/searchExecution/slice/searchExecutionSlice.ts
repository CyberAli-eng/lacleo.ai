import { createSlice, PayloadAction, createAsyncThunk, createSelector } from "@reduxjs/toolkit"
import { TRootState } from "@/interface/reduxRoot/state"
import { buildSearchUrl } from "@/app/utils/searchUtils"

type Entity = "contact" | "company"

interface SearchExecutionState {
  entity: Entity | null
  dsl: Record<string, unknown> | null
  status: "idle" | "loading" | "success" | "error"
  results: Array<Record<string, unknown>>
  total: number
  aggregations: Record<string, unknown> | null
  lastExecutedAt: string | null
  lastExecutedDsl: Record<string, unknown> | null
  page: number
  count: number
  sort: string[]
  errorMessage?: string | null
  searchTerm?: string | null
  semanticQuery?: string | null
}

const initialState: SearchExecutionState = {
  entity: null,
  dsl: null,
  status: "idle",
  results: [],
  total: 0,
  aggregations: null,
  lastExecutedAt: null,
  lastExecutedDsl: null,
  page: 1,
  count: 50,
  sort: [],
  errorMessage: null,
  searchTerm: null,
  semanticQuery: null
}

export const executeSearch = createAsyncThunk<
  { data: Array<Record<string, unknown>>; total: number; aggregations?: Record<string, unknown> | null },
  void,
  { state: TRootState }
>("searchExecution/executeSearch", async (_arg, thunkApi) => {
  const state = thunkApi.getState()
  const exec = state.searchExecution
  if (!exec.entity || !exec.dsl) {
    throw new Error("Missing entity or DSL for execution")
  }

  const queryParams: Record<string, unknown> = {
    filter_dsl: exec.dsl
  }
  if (exec.searchTerm && exec.searchTerm.length >= 2) {
    queryParams.searchTerm = exec.searchTerm
  }
  if (exec.semanticQuery) {
    queryParams.semantic_query = exec.semanticQuery
  }

  const searchParams = {
    page: exec.page,
    count: exec.count,
    ...(exec.sort.length ? { sort: exec.sort.join(",") } : {})
  }

  const qs = buildSearchUrl(searchParams, queryParams)
  const base = (import.meta.env.VITE_API_HOST as string) || "/api/v1"
  const url = `${base}/search/${exec.entity}${qs}`

  const res = await fetch(url, { credentials: "include" })
  if (!res.ok) {
    const text = await res.text()
    // Search failed
    throw new Error(text || `Search failed with status ${res.status}`)
  }
  const json = await res.json()
  const data = (json?.data ?? []) as Array<Record<string, unknown>>
  const total = (json?.total ?? data.length) as number
  const aggregations = (json?.aggregations ?? null) as Record<string, unknown> | null
  return { data, total, aggregations }
})

const slice = createSlice({
  name: "searchExecution",
  initialState,
  reducers: {
    setEntity(state, action: PayloadAction<Entity>) {
      state.entity = action.payload
    },
    setDsl(state, action: PayloadAction<Record<string, unknown>>) {
      state.dsl = action.payload
    },
    setSearchTerm(state, action: PayloadAction<string | null>) {
      state.searchTerm = action.payload || null
    },
    setSemanticQuery(state, action: PayloadAction<string | null>) {
      state.semanticQuery = action.payload || null
    },
    setPage(state, action: PayloadAction<number>) {
      state.page = Math.max(1, action.payload)
    },
    setCount(state, action: PayloadAction<number>) {
      state.count = Math.max(10, Math.min(100, action.payload))
    },
    setSort(state, action: PayloadAction<string[]>) {
      state.sort = action.payload
    },
    reset(state) {
      Object.assign(state, initialState)
    }
  },
  extraReducers: (builder) => {
    builder
      .addCase(executeSearch.pending, (state) => {
        state.status = "loading"
        state.errorMessage = null
        state.lastExecutedDsl = state.dsl
      })
      .addCase(executeSearch.fulfilled, (state, action) => {
        // Search results loaded
        state.status = "success"
        state.results = action.payload.data
        state.total = action.payload.total
        state.aggregations = action.payload.aggregations ?? null
        state.lastExecutedAt = new Date().toISOString()
      })
      .addCase(executeSearch.rejected, (state, action) => {
        state.status = "error"
        state.errorMessage = action.error.message || "Search failed"
        state.results = []
        state.total = 0
        state.lastExecutedAt = new Date().toISOString()
      })
  }
})

export const { setEntity, setDsl, setSearchTerm, setSemanticQuery, setPage, setCount, setSort, reset } = slice.actions

export default slice.reducer

export const selectSearchExecution = (state: TRootState) => state.searchExecution
export const selectSearchResults = (state: TRootState) => state.searchExecution.results
export const selectSearchStatus = (state: TRootState) => state.searchExecution.status
export const selectSearchMeta = createSelector([selectSearchExecution], (se) => ({
  total: se.total,
  lastExecutedAt: se.lastExecutedAt,
  entity: se.entity,
  page: se.page,
  count: se.count,
  sort: se.sort,
  dsl: se.lastExecutedDsl
}))
