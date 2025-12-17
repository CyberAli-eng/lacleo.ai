import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import { Alert, AlertDescription, AlertTitle, ANIMATION_VARIANTS } from "@/components/ui/alert"
import { TAlertType } from "@/interface/alerts/slice"
import { AnimatePresence, motion } from "framer-motion"
import { AlertCircle, CheckCircle2, Info, XCircle } from "lucide-react"
import { FC, useCallback, useEffect } from "react"
import { destroyAlert, selectAllAlerts } from "./slice/alertSlice"

const ALERT_CONFIGS = {
  success: {
    icon: CheckCircle2,
    variant: "success" as const
  },
  error: {
    icon: XCircle,
    variant: "destructive" as const
  },
  warning: {
    icon: AlertCircle,
    variant: "warning" as const
  },
  info: {
    icon: Info,
    variant: "info" as const
  }
} as const

const AlertProvider: FC = () => {
  const dispatch = useAppDispatch()
  const alerts = useAppSelector(selectAllAlerts)

  const handleDismiss = useCallback(
    (id: string) => {
      dispatch(destroyAlert(id))
    },
    [dispatch]
  )

  useEffect(() => {
    const timers = alerts.map((alert) => setTimeout(() => handleDismiss(alert.id), alert.maxTimer))
    return () => timers.forEach(clearTimeout)
  }, [alerts, handleDismiss])

  const getAlertConfig = useCallback((type: TAlertType) => ALERT_CONFIGS[type] || ALERT_CONFIGS.info, [])

  if (alerts.length === 0) return null

  return (
    <div className="fixed right-4 top-[72px] z-50 flex min-w-[320px] max-w-md flex-col gap-3">
      <AnimatePresence mode="sync">
        {alerts.map((alert) => {
          const { icon: Icon, variant } = getAlertConfig(alert.type)
          return (
            <motion.div key={alert.id} layout variants={ANIMATION_VARIANTS.alert} initial="initial" animate="animate" exit="exit" layoutId={alert.id}>
              <Alert variant={variant} className="relative overflow-hidden shadow-lg dark:shadow-lg">
                <motion.div
                  variants={ANIMATION_VARIANTS.progressBar}
                  initial="initial"
                  animate="animate"
                  custom={alert.maxTimer / 1000}
                  className="absolute bottom-0 left-0 h-0.5 bg-current opacity-20"
                />
                <Icon className="size-4" />
                <AlertTitle>{alert.title}</AlertTitle>
                <AlertDescription>{alert.description}</AlertDescription>
                <button
                  onClick={() => handleDismiss(alert.id)}
                  className="absolute right-2 top-2 rounded-full p-1 opacity-70 transition-opacity hover:opacity-100 focus:outline-none"
                  aria-label="Dismiss alert"
                >
                  <XCircle className="size-4" />
                </button>
              </Alert>
            </motion.div>
          )
        })}
      </AnimatePresence>
    </div>
  )
}

export default AlertProvider
