import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import { selectIsCreditUsageOpen, setCreditUsageOpen } from "@/features/settings/slice/settingSlice"
import { useBillingUsageQuery } from "@/features/searchTable/slice/apiSlice"
import { Card, CardContent, CardHeader, CardTitle } from "./card"
import { Button } from "./button"
import { CircleDollarSign, Star } from "lucide-react"
import CreditUsage from "./modals/creditusage"

const Credits = () => {
  const dispatch = useAppDispatch()
  const isCreditUsageOpen = useAppSelector(selectIsCreditUsageOpen)

  const user = useAppSelector((s) => s.setting.user)
  const { data, isFetching } = useBillingUsageQuery(undefined, { skip: !user })
  const availableCredits = data?.balance ?? 0
  const planTotal = data?.plan_total ?? 0
  const computedTotal = planTotal > 0 ? planTotal : data ? data.balance + data.used : 0
  const percentage = computedTotal > 0 ? Math.max(0, Math.min(100, Math.round((availableCredits / computedTotal) * 100))) : 0

  return (
    <>
      <Card className="mb-3 p-5">
        <CardHeader className="p-0">
          <CardTitle className="text-xs font-medium text-gray-600">Available Credits</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          <div className="flex items-center gap-2">
            <Star className="size-6" />
            <span className="text-2xl font-semibold text-gray-950">{availableCredits}</span>
            {computedTotal > 0 && <span className="text-xs font-normal text-gray-400">(Total {computedTotal} creds/mo)</span>}
          </div>
          {/* progress bar */}
          <div className="mt-3 flex items-center gap-3">
            <div
              className="relative h-3 flex-1 rounded-full bg-gray-200"
              role="progressbar"
              aria-valuenow={percentage}
              aria-valuemin={0}
              aria-valuemax={100}
              aria-label="Available credits percentage"
            >
              <div className="h-3 rounded-full bg-blue-600 transition-[width] duration-500 ease-out" style={{ width: `${percentage}%` }} />
            </div>
            <span className="text-xs font-medium text-gray-600">{isFetching ? "…" : `${percentage}%`}</span>
          </div>

          <Button
            variant="outline"
            className="mt-3 w-full p-[10px] text-sm font-medium text-gray-600 hover:text-gray-600"
            onClick={() => dispatch(setCreditUsageOpen(true))}
          >
            <CircleDollarSign className="size-5 text-gray-600" /> View credit usage
          </Button>
        </CardContent>
      </Card>

      <CreditUsage open={isCreditUsageOpen} onOpenChange={(open) => dispatch(setCreditUsageOpen(open))} />
    </>
  )
}

export default Credits
