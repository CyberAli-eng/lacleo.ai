import { TThemeMode } from "@/interface/settings/slice"

const ENCRYPTED_KEY_TOKEN_STORAGE = "pros-ui-theme"

function isValidTheme(theme: string): theme is TThemeMode {
  return ["system", "light", "dark"].includes(theme)
}

export function GET_USER_THEME(): TThemeMode {
  try {
    const theme = localStorage.getItem(ENCRYPTED_KEY_TOKEN_STORAGE)
    if (!theme) return "system"

    const decodedTheme = window.atob(theme)
    if (isValidTheme(decodedTheme)) return decodedTheme

    return "system"
  } catch (error) {
    console.warn("theme error, defaulting to system")
    return "system"
  }
}

export function SET_USER_THEME(theme: TThemeMode): void {
  localStorage.setItem(ENCRYPTED_KEY_TOKEN_STORAGE, window.btoa(theme))
}
