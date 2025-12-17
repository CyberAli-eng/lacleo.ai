export type TThemeMode = "system" | "light" | "dark"

export interface IUser {
  id: string
  name: string
  email: string
  email_verified: boolean
  profile_photo_url: string
}

export interface ISettingSliceState {
  theme: TThemeMode
  token?: string
  user?: IUser
  isCreditUsageOpen?: boolean
}
