import { GET_USER_THEME, SET_USER_THEME } from "@/app/utils/localStorage"
import { TRootState } from "@/interface/reduxRoot/state"
import { ISettingSliceState, IUser, TThemeMode } from "@/interface/settings/slice"
import { createSlice, PayloadAction } from "@reduxjs/toolkit"
import Cookies from "js-cookie"

const TOKEN_KEY = import.meta.env.VITE_ACCOUNT_COOKIE_TOKEN_KEY || "LocalAccessToken"

const initialState: ISettingSliceState = {
  token: Cookies.get(TOKEN_KEY),
  user: undefined,
  theme: GET_USER_THEME()
}

const settingSlice = createSlice({
  name: "setting",
  initialState,
  reducers: {
    setUser: (state, action: PayloadAction<IUser | undefined>) => {
      state.user = action.payload
    },

    setToken: (state, action: PayloadAction<string | undefined>) => {
      state.token = action.payload
    },

    setTheme: (state, action: PayloadAction<TThemeMode>) => {
      state.theme = action.payload
      SET_USER_THEME(action.payload)
    },

    clearUserSession: (state) => {
      state.token = undefined
      state.user = undefined
    },

    setCreditUsageOpen: (state, action: PayloadAction<boolean>) => {
      state.isCreditUsageOpen = action.payload
    }
  }
})

export const { setToken, setUser, setTheme, clearUserSession, setCreditUsageOpen } = settingSlice.actions

export const selectToken = (state: TRootState) => state.setting.token
export const selectUser = (state: TRootState) => state.setting.user
export const selectTheme = (state: TRootState) => state.setting.theme
export const selectIsCreditUsageOpen = (state: TRootState) => state.setting.isCreditUsageOpen || false

export default settingSlice.reducer
