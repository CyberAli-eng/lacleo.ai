import alertsSliceReducer from "@/features/alerts/slice/alertSlice"
import filtersSliceReducer from "@/features/filters/slice/filterSlice"
import settingSliceReducer from "@/features/settings/slice/settingSlice"
import searchSliceReducer from "@/features/aisearch/slice/searchslice"
import searchTableViewReducer from "@/interface/searchTable/view"
import companyDetailsReducer from "@/features/searchTable/slice/companyDetailsSlice"
import contactInfoReducer from "@/features/searchTable/slice/contactInfoSlice"
import { configureStore } from "@reduxjs/toolkit"
import columnsReducer from "@/features/searchTable/slice/columnsSlice"
import { apiSlice } from "./apiSlice"
import searchExecutionReducer from "@/features/searchExecution/slice/searchExecutionSlice"

export const store = configureStore({
  reducer: {
    [apiSlice.reducerPath]: apiSlice.reducer,
    setting: settingSliceReducer,
    alerts: alertsSliceReducer,
    filters: filtersSliceReducer,
    search: searchSliceReducer,
    searchExecution: searchExecutionReducer,
    searchTable: searchTableViewReducer,
    companyDetails: companyDetailsReducer,
    contactInfo: contactInfoReducer,
    columns: columnsReducer
  },
  middleware: (getDefaultMiddleware) => getDefaultMiddleware({ immutableCheck: { warnAfter: 100 }, serializableCheck: false }).concat(apiSlice.middleware),
  devTools: true
})
