import { CircleDollarSign, DollarSign, Mail, Phone, Save, X } from "lucide-react"
import { Button } from "../button"
import { Card, CardContent, CardHeader, CardTitle } from "../card"
import InfoIcon from "../../../static/media/icons/info-icon.svg?react"
import CalendarIcon from "../../../static/media/icons/calendar-icon.svg?react"
import { Avatar } from "../avatar"
import { Dialog, DialogClose, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogDescription } from "../dialog"
import { Input } from "../input"
import { useBillingUsageQuery, useLazyAdminBillingContextQuery, useGrantCreditsMutation } from "@/features/searchTable/slice/apiSlice"
import { useBillingPurchaseMutation, useBillingPortalMutation } from "@/features/searchTable/slice/apiSlice"
import { useAppSelector } from "@/app/hooks/reduxHooks"
import { useEffect, useState } from "react"
import { useAlert } from "@/app/hooks/alertHooks"

type CreditUsageProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
}

const CreditUsage = ({ open, onOpenChange }: CreditUsageProps) => {
  const { data } = useBillingUsageQuery()
  const user = useAppSelector((s) => s.setting.user)
  const isAdmin = (user?.email || "").toLowerCase() === "shaizqurashi12345@gmail.com"
  const [adminEmail, setAdminEmail] = useState("")
  const [grantAmount, setGrantAmount] = useState(0)
  const [grantReason, setGrantReason] = useState("")
  const [targetUserId, setTargetUserId] = useState<string | null>(null)
  const [targetUserEmail, setTargetUserEmail] = useState<string | null>(null)
  const [targetBalance, setTargetBalance] = useState<number | null>(null)
  const [lookupStatus, setLookupStatus] = useState<"idle" | "loading" | "found" | "not_found">("idle")
  const [contextTrigger] = useLazyAdminBillingContextQuery()
  const [grantCredits] = useGrantCreditsMutation()
  const { showAlert } = useAlert()
  const totalCredits = (data?.plan_total ?? 0) > 0 ? data?.plan_total ?? 0 : data ? data.balance + data.used : 0
  const availableCredits = data?.balance ?? 0
  const percentage = totalCredits > 0 ? Math.max(0, Math.min(100, Math.round((availableCredits / totalCredits) * 100))) : 0
  const periodLabel = data ? new Date(data.period_start).toLocaleDateString() + " - " + new Date(data.period_end).toLocaleDateString() : ""
  const revealEmailCredits = data?.breakdown?.reveal_email ?? 0
  const revealPhoneCredits = data?.breakdown?.reveal_phone ?? 0
  const revealEmailCount = revealEmailCredits > 0 ? Math.round(revealEmailCredits / 1) : 0
  const revealPhoneCount = revealPhoneCredits > 0 ? Math.round(revealPhoneCredits / 4) : 0
  useEffect(() => {
    const email = adminEmail.trim()
    if (!email) {
      setTargetUserId(null)
      setTargetUserEmail(null)
      setTargetBalance(null)
      setLookupStatus("idle")
      return
    }
    const handle = setTimeout(async () => {
      setLookupStatus("loading")
      const res = await contextTrigger({ email })
        .unwrap()
        .catch(() => null)
      if (!res) {
        setTargetUserId(null)
        setTargetUserEmail(null)
        setTargetBalance(null)
        setLookupStatus("not_found")
        return
      }
      setTargetUserId(res.user.id)
      setTargetUserEmail(res.user.email)
      setTargetBalance(res.workspace.balance)
      setLookupStatus("found")
    }, 500)
    return () => clearTimeout(handle)
  }, [adminEmail, contextTrigger])
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] max-w-[420px] overflow-y-auto rounded-xl border p-0">
        <DialogHeader className="flex flex-row items-start justify-between border-b border-border p-5">
          <DialogTitle className="flex flex-row items-center gap-3.5">
            <span className="flex items-center justify-center rounded-full border p-[10px]">
              <CircleDollarSign className="size-5 text-gray-600" />
            </span>
            <div className="flex flex-col items-start">
              <span className="text-sm font-medium text-gray-950">Credit Usage</span>
              <DialogDescription className="text-xs font-normal text-gray-600">Check your credit balance and usage</DialogDescription>
            </div>
          </DialogTitle>
        </DialogHeader>
        <div className="flex flex-col gap-4 p-4">
          <Card className="p-4">
            <CardHeader className="border-b p-0 pb-4">
              <CardTitle className="text-sm font-medium text-gray-950">Credit Balance</CardTitle>
            </CardHeader>
            <CardContent className="p-0 pt-4">
              <div className="mb-4 flex flex-col gap-1">
                <span className="text-[18px] font-normal text-gray-600">
                  You have <span className="text-[18px] font-medium text-gray-950">{availableCredits} credits</span>{" "}
                </span>

                {!!periodLabel && (
                  <span className="flex items-center gap-1 text-xs font-normal text-gray-600">
                    <InfoIcon className="size-5" />
                    Current period {periodLabel}
                  </span>
                )}
              </div>

              {/* progressbar */}
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
                <span className="text-xs font-medium text-gray-600">{percentage}%</span>
              </div>

              <div className="mt-4 flex flex-row items-center justify-between rounded-lg border px-[10px] py-1.5">
                <span className="text-xs font-normal text-gray-600">
                  Your plan includes
                  <span className="font-medium"> {totalCredits} credits</span>.
                </span>

                <button className="cursor-pointer text-xs font-medium text-[#335CFF]">Upgrade</button>
              </div>
            </CardContent>
          </Card>

          {/* breakdown */}
          <Card className="p-4">
            <CardHeader className="flex flex-row items-center justify-between border-b p-0 pb-4">
              <CardTitle className="text-sm font-medium text-gray-950">Spend Breakdown</CardTitle>
              {!!periodLabel && (
                <Button variant="outline" className="!my-0 h-6 space-y-0 px-[10px] py-1 text-xs font-normal text-gray-600">
                  <CalendarIcon className="size-5" /> {periodLabel}
                </Button>
              )}
            </CardHeader>
            <CardContent className="p-0 pt-4">
              <div className="flex flex-row justify-between">
                <div className="flex flex-row items-start gap-2">
                  <Avatar className="flex items-center justify-center rounded-full border p-1">
                    <Mail className="size-4 text-[#335CFF]" />
                  </Avatar>
                  <div className="flex flex-col gap-1">
                    <span className="text-xs font-medium text-gray-400">EMail </span>
                    <span className="text-sm font-medium text-gray-950">{revealEmailCredits} Credits</span>
                    <div className="flex flex-row items-center justify-center rounded-full bg-[#C0D5FF] px-2 pt-0.5 text-xs font-medium text-[#122368]">
                      {revealEmailCount} Reveals
                    </div>
                  </div>
                </div>

                <div className="border-l"></div>

                <div className="flex flex-row items-start gap-2">
                  <Avatar className="flex items-center justify-center rounded-full border p-1">
                    <Phone className="size-4 text-[#335CFF]" />
                  </Avatar>
                  <div className="flex flex-col gap-1">
                    <span className="text-xs font-medium text-gray-400">Phone Numbers</span>
                    <span className="text-sm font-medium text-gray-950">{revealPhoneCredits} Credits</span>
                    <div className="flex flex-row items-center justify-center rounded-full bg-[#C0D5FF] px-2 pt-0.5 text-xs font-medium text-[#122368]">
                      {revealPhoneCount} Reveals
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          {!!isAdmin && (
            <Card className="mt-4 p-4">
              <CardHeader className="border-b p-0 pb-4">
                <CardTitle className="text-sm font-medium text-gray-950">Admin Actions</CardTitle>
              </CardHeader>
              <CardContent className="p-0 pt-4">
                <div className="grid grid-cols-1 gap-3">
                  <Input placeholder="Target user email" value={adminEmail} onChange={(e) => setAdminEmail(e.target.value)} />
                  {lookupStatus === "loading" && <div className="text-xs font-medium text-gray-600">Looking up…</div>}
                  {lookupStatus === "found" && (
                    <div className="flex items-center justify-between rounded-md border px-2 py-1 text-xs">
                      <span className="font-medium text-green-700">
                        Loaded: {targetUserEmail} • Balance {targetBalance}
                      </span>
                      <div className="flex items-center gap-2">
                        <Button
                          variant="outline"
                          className="h-6 px-2 text-xs"
                          onClick={() => {
                            if (targetUserEmail) navigator.clipboard.writeText(targetUserEmail)
                            showAlert("Copied", "Email copied to clipboard", "info", 2000)
                          }}
                        >
                          Copy email
                        </Button>
                        <Button
                          variant="outline"
                          className="h-6 px-2 text-xs"
                          onClick={() => {
                            setAdminEmail("")
                            setTargetUserId(null)
                            setTargetUserEmail(null)
                            setTargetBalance(null)
                            setLookupStatus("idle")
                          }}
                        >
                          Clear
                        </Button>
                      </div>
                    </div>
                  )}
                  {lookupStatus === "not_found" && adminEmail.trim() && (
                    <div className="text-xs font-medium text-red-600">No user found for {adminEmail}</div>
                  )}
                  <div className="grid grid-cols-2 gap-3">
                    <Input
                      placeholder="Credits"
                      type="number"
                      value={grantAmount}
                      onChange={(e) => setGrantAmount(parseInt(e.target.value || "0", 10))}
                    />
                    <Input placeholder="Reason (optional)" value={grantReason} onChange={(e) => setGrantReason(e.target.value)} />
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <Button
                      variant="outline"
                      className="w-full"
                      onClick={async () => {
                        if (!adminEmail.trim()) return
                        setLookupStatus("loading")
                        const res = await contextTrigger({ email: adminEmail })
                          .unwrap()
                          .catch(() => null)
                        if (!res) {
                          showAlert("Lookup failed", "Unable to find user with that email", "error", 5000)
                          setLookupStatus("not_found")
                          return
                        }
                        setTargetUserId(res.user.id)
                        setTargetUserEmail(res.user.email)
                        setTargetBalance(res.workspace.balance)
                        setLookupStatus("found")
                        showAlert("User loaded", `Current balance: ${res.workspace.balance}`, "info", 4000)
                      }}
                    >
                      Load user
                    </Button>
                    <Button
                      className="w-full bg-[#335CFF] text-white hover:bg-[#335CFF]"
                      onClick={async () => {
                        if (!targetUserId) {
                          if (!adminEmail.trim()) {
                            showAlert("Missing data", "Enter a user email", "warning", 4000)
                            return
                          }
                          setLookupStatus("loading")
                          const res = await contextTrigger({ email: adminEmail })
                            .unwrap()
                            .catch(() => null)
                          if (!res) {
                            setLookupStatus("not_found")
                            showAlert("Lookup failed", "Unable to find user with that email", "error", 5000)
                            return
                          }
                          setTargetUserId(res.user.id)
                          setTargetUserEmail(res.user.email)
                          setTargetBalance(res.workspace.balance)
                          setLookupStatus("found")
                        }
                        if (grantAmount <= 0) {
                          showAlert("Missing data", "Enter a positive credits amount", "warning", 4000)
                          return
                        }
                        try {
                          const userId = targetUserId as string
                          const r = await grantCredits({ user_id: userId, credits: grantAmount, reason: grantReason || undefined }).unwrap()
                          showAlert("Credits granted", `New balance: ${r.new_balance}`, "success", 5000)
                          setAdminEmail("")
                          setGrantAmount(0)
                          setGrantReason("")
                          setTargetUserId(null)
                          setTargetUserEmail(null)
                          setTargetBalance(null)
                          setLookupStatus("idle")
                        } catch (e) {
                          showAlert("Grant failed", "Unable to grant credits", "error", 5000)
                        }
                      }}
                    >
                      Grant credits
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}
        </div>

        <DialogFooter className="border-t p-5">
          <DialogClose asChild>
            <Button variant="outline" className="w-full rounded-lg p-2 text-sm font-medium text-[#5C5C5C]">
              Close
            </Button>
          </DialogClose>
          <div className="grid w-full grid-cols-2 gap-2">
            <BuyCreditsButton />
            <UpgradePlanButton />
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

const BuyCreditsButton = () => {
  const { data } = useBillingUsageQuery()
  const stripeEnabled = !!data?.stripe_enabled
  const [purchase, { isLoading }] = useBillingPurchaseMutation()
  return (
    <Button
      variant="outline"
      className="w-full rounded-lg bg-[#335CFF] p-2 text-sm font-medium text-white hover:bg-[#335CFF] hover:text-white disabled:opacity-50"
      disabled={!stripeEnabled || isLoading}
      onClick={async () => {
        try {
          const r = await purchase({ pack: 500 }).unwrap()
          if (r.checkout_url) {
            window.location.href = r.checkout_url
          }
        } catch (err) {
          console.error(err)
        }
      }}
    >
      Buy Credits
    </Button>
  )
}

const UpgradePlanButton = () => {
  const [portal, { isLoading }] = useBillingPortalMutation()
  return (
    <Button
      variant="outline"
      className="w-full rounded-lg p-2 text-sm font-medium text-[#335CFF] hover:text-[#335CFF] disabled:opacity-50"
      disabled={isLoading}
      onClick={async () => {
        try {
          const r = await portal().unwrap()
          if (r.url) {
            window.location.href = r.url
          }
        } catch (err) {
          console.error(err)
        }
      }}
    >
      Upgrade Plan
    </Button>
  )
}

export default CreditUsage
