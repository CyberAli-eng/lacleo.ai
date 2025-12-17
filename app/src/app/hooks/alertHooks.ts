import { setAlert } from "@/features/alerts/slice/alertSlice"
import { TAlertType } from "@/interface/alerts/slice"
import { useCallback } from "react"
import { useDispatch } from "react-redux"

export const useAlert = () => {
  const dispatch = useDispatch()

  const showAlert = useCallback(
    (title: string, description: string, type?: TAlertType, maxTimer?: number) => {
      dispatch(setAlert(title, description, type, maxTimer))
    },
    [dispatch]
  )

  return { showAlert }
}
