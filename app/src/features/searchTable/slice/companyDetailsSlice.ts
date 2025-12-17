import { CompanyAttributes, ContactAttributes } from "@/interface/searchTable/search"
import { TRootState } from "@/interface/reduxRoot/state"
import { PayloadAction, createSlice } from "@reduxjs/toolkit"

export type CompanyDetailsState = {
  isOpen: boolean
  company: CompanyAttributes | null
  contact: ContactAttributes | null
}

const initialState: CompanyDetailsState = {
  isOpen: false,
  company: null,
  contact: null
}

const companyDetailsSlice = createSlice({
  name: "companyDetails",
  initialState,
  reducers: {
    openCompanyDetails: (
      state,
      action: PayloadAction<{ company: CompanyAttributes | null; contact?: ContactAttributes | null } | CompanyAttributes | null>
    ) => {
      state.isOpen = true
      // Handle both old (CompanyAttributes only) and new ({company, contact}) payload structures
      if (action.payload && "company" in action.payload && !("website" in action.payload)) {
        // It's the new structure { company: ..., contact: ... }
        const payload = action.payload as { company: CompanyAttributes | null; contact?: ContactAttributes | null }
        state.company = payload.company
        state.contact = payload.contact || null
      } else {
        // It's likely the old structure (CompanyAttributes directly)
        state.company = action.payload as CompanyAttributes | null
        state.contact = null
      }
    },
    closeCompanyDetails: (state) => {
      state.isOpen = false
      state.company = null
      state.contact = null
    }
  }
})

export const { openCompanyDetails, closeCompanyDetails } = companyDetailsSlice.actions

export const selectIsCompanyDetailsOpen = (state: TRootState) => state.companyDetails.isOpen
export const selectCompanyDetails = (state: TRootState) => state.companyDetails.company
export const selectCompanyDetailsContact = (state: TRootState) => state.companyDetails.contact

export default companyDetailsSlice.reducer
