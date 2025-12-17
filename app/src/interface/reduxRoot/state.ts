import { store } from "@/app/redux/store"

export type TRootState = ReturnType<typeof store.getState>
export type TAppDispatch = typeof store.dispatch
