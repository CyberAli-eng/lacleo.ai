import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import AlertProvider from "@/features/alerts/alertProvider"
import { selectTheme, selectIsCreditUsageOpen, setCreditUsageOpen } from "@/features/settings/slice/settingSlice"
import CreditUsage from "@/components/ui/modals/creditusage"
import { useEffect } from "react"
import { Outlet } from "react-router-dom"

export default function AppLayout() {
  const dispatch = useAppDispatch()
  const currentTheme = useAppSelector(selectTheme)
  const isCreditUsageOpen = useAppSelector(selectIsCreditUsageOpen)

  const applyTheme = (isDark: boolean) => {
    document.documentElement.className = isDark ? "dark" : "light"
  }

  useEffect(() => {
    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)")

    const handleSystemThemeChange = (event: MediaQueryListEvent) => {
      if (currentTheme === "system") applyTheme(event.matches)
    }

    if (currentTheme === "system") {
      applyTheme(mediaQuery.matches)
      mediaQuery.addEventListener("change", handleSystemThemeChange)
    } else {
      applyTheme(currentTheme === "dark")
    }

    return () => mediaQuery.removeEventListener("change", handleSystemThemeChange)
  }, [currentTheme])

  return (
    <div className="min-h-screen">
      <AlertProvider />
      <CreditUsage open={isCreditUsageOpen} onOpenChange={(open) => dispatch(setCreditUsageOpen(open))} />
      <Outlet />
    </div>
  )
}
