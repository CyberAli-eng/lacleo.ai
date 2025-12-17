import { createSlice, PayloadAction } from "@reduxjs/toolkit"

export type ColumnOption = {
  field: string
  title: string
  visible: boolean
}

export type ColumnsTab = "contact" | "company"

interface ColumnsState {
  open: boolean
  activeTab: ColumnsTab
  configs: Record<ColumnsTab, ColumnOption[]>
}

const initialState: ColumnsState = {
  open: false,
  activeTab: "contact",
  configs: {
    contact: [
      { field: "full_name", title: "Contact Name", visible: true },
      { field: "company", title: "Company", visible: true },
      { field: "title", title: "Job Profile", visible: true },
      { field: "contact", title: "Contact", visible: true },
      { field: "actions", title: "Actions", visible: true }
    ],
    company: [
      { field: "company", title: "Company Name", visible: true },
      { field: "industry", title: "Industry", visible: true },
      { field: "location", title: "HQ Location", visible: true },
      { field: "company_headcount", title: "Employees", visible: true },
      { field: "actions", title: "Actions", visible: true }
    ]
  }
}

const columnsSlice = createSlice({
  name: "columns",
  initialState,
  reducers: {
    openColumnsModal(state, action: PayloadAction<ColumnsTab | undefined>) {
      state.open = true
      if (action.payload) {
        state.activeTab = action.payload
      }
    },
    closeColumnsModal(state) {
      state.open = false
    },
    setActiveColumnsTab(state, action: PayloadAction<ColumnsTab>) {
      state.activeTab = action.payload
    },
    setColumnsForTab(state, action: PayloadAction<{ tab: ColumnsTab; columns: ColumnOption[] }>) {
      state.configs[action.payload.tab] = action.payload.columns
    }
  }
})

export const { openColumnsModal, closeColumnsModal, setActiveColumnsTab, setColumnsForTab } = columnsSlice.actions
export default columnsSlice.reducer
