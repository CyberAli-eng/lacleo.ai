import { createSlice, PayloadAction } from "@reduxjs/toolkit"
import { TRootState } from "@/interface/reduxRoot/state"

export type SearchTableView = "search" | "savedFilters"

interface SearchTableUIState {
  view: SearchTableView
}

const initialState: SearchTableUIState = {
  view: "search"
}

const searchTableViewSlice = createSlice({
  name: "searchTable",
  initialState,
  reducers: {
    setView: (state, action: PayloadAction<SearchTableView>) => {
      state.view = action.payload
    }
  }
})

export const { setView } = searchTableViewSlice.actions
export default searchTableViewSlice.reducer

export const selectSearchTableView = (state: TRootState) => state.searchTable.view
