import { GET_USER_THEME, SET_USER_THEME } from "@/app/utils/localStorage"
import { TRootState } from "@/interface/reduxRoot/state"
import { ISettingSliceState, IUser, TThemeMode } from "@/interface/settings/slice"
import { createSlice, PayloadAction } from "@reduxjs/toolkit"

const TOKEN_KEY = "auth_token"

// Get token from localStorage
const getStoredToken = (): string | undefined => {
  try {
    return localStorage.getItem(TOKEN_KEY) || undefined
  } catch {
    return undefined
  }
}

// Store token in localStorage
const storeToken = (token: string | undefined) => {
  try {
    if (token) {
      localStorage.setItem(TOKEN_KEY, token)
    } else {
      localStorage.removeItem(TOKEN_KEY)
    }
  } catch {
    // Ignore storage errors
  }
}

const initialState: ISettingSliceState = {
  token: getStoredToken(),
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
      storeToken(action.payload)
    },

    setTheme: (state, action: PayloadAction<TThemeMode>) => {
      state.theme = action.payload
      SET_USER_THEME(action.payload)
    },

    clearUserSession: (state) => {
      state.token = undefined
      state.user = undefined
      storeToken(undefined)
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
