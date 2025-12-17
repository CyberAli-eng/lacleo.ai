import { selectUser } from "@/features/settings/slice/settingSlice"

export type TNonNullUserState = NonNullable<ReturnType<typeof selectUser>>
