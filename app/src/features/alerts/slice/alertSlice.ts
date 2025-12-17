import { IAlert, IAlertState, TAlertType } from "@/interface/alerts/slice"
import { TRootState } from "@/interface/reduxRoot/state"
import { createEntityAdapter, createSlice, nanoid, PayloadAction } from "@reduxjs/toolkit"

const alertAdapter = createEntityAdapter<IAlert>()

const alertSlice = createSlice({
  name: "alerts",
  initialState: alertAdapter.getInitialState() as IAlertState,
  reducers: {
    setAlert: {
      reducer(state, action: PayloadAction<IAlert>) {
        alertAdapter.addOne(state, action.payload)
      },
      prepare(title: string, description: string, type: TAlertType = "info", maxTimer: number = 5000) {
        return {
          payload: {
            id: nanoid(),
            title,
            description,
            type,
            maxTimer,
            date: new Date().toISOString()
          }
        }
      }
    },
    destroyAlert: (state, action: PayloadAction<string>) => {
      alertAdapter.removeOne(state, action.payload)
    }
  }
})

export const { setAlert, destroyAlert } = alertSlice.actions

export const { selectAll: selectAllAlerts } = alertAdapter.getSelectors<TRootState>((state) => state.alerts)

export default alertSlice.reducer
