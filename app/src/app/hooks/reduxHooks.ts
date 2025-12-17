import { TAppDispatch, TRootState } from "@/interface/reduxRoot/state"
import { useDispatch, useSelector } from "react-redux"

export const useAppDispatch = useDispatch.withTypes<TAppDispatch>()
export const useAppSelector = useSelector.withTypes<TRootState>()
