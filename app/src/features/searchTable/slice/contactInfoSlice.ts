import { TRootState } from "@/interface/reduxRoot/state"
import { CompanyAttributes, ContactAttributes } from "@/interface/searchTable/search"
import { PayloadAction, createSelector, createSlice } from "@reduxjs/toolkit"

export type ContactInfoState = {
  isOpen: boolean
  contact: ContactAttributes | null
  company: CompanyAttributes | null
  hideContactFields: boolean
  hideCompanyActions: boolean
}

const initialState: ContactInfoState = {
  isOpen: false,
  contact: null,
  company: null,
  hideContactFields: false,
  hideCompanyActions: false
}

const contactInfoSlice = createSlice({
  name: "contactInfo",
  initialState,
  reducers: {
    openContactInfoForContact: (state, action: PayloadAction<ContactAttributes>) => {
      state.isOpen = true
      state.contact = action.payload
      state.company = null
      state.hideContactFields = false
      state.hideCompanyActions = false
    },
    openContactInfoForCompany: (state, action: PayloadAction<CompanyAttributes>) => {
      state.isOpen = true
      state.company = action.payload
      // Provide minimal contact-like info for the panel header/actions
      state.contact = {
        _id: "",
        company: action.payload.company,
        linkedin_url: action.payload.company_linkedin_url ?? undefined,
        website: action.payload.website
      }
      state.hideContactFields = true
      state.hideCompanyActions = true
    },
    closeContactInfo: (state) => {
      state.isOpen = false
      state.contact = null
      state.company = null
      state.hideContactFields = false
      state.hideCompanyActions = false
    }
  }
})

export const { openContactInfoForContact, openContactInfoForCompany, closeContactInfo } = contactInfoSlice.actions

export const selectIsContactInfoOpen = (state: TRootState) => state.contactInfo.isOpen
export const selectContactInfoContact = (state: TRootState) => state.contactInfo.contact
export const selectContactInfoCompany = (state: TRootState) => state.contactInfo.company
const selectContactInfoState = (state: TRootState) => state.contactInfo

export const selectContactInfoFlags = createSelector(selectContactInfoState, (s) => ({
  hideContactFields: s.hideContactFields,
  hideCompanyActions: s.hideCompanyActions
}))

export default contactInfoSlice.reducer
