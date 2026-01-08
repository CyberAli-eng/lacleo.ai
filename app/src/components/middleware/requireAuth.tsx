import { ACCOUNT_HOST } from "@/app/constants/apiConstants"
import { useAlert } from "@/app/hooks/alertHooks"
import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import { useLazyGetUserQuery } from "@/features/settings/slice/apiSlice"
import { selectToken, setUser, clearUserSession } from "@/features/settings/slice/settingSlice"
import { motion } from "framer-motion"
import { useEffect, useState } from "react"
import { Outlet } from "react-router-dom"
import { Button } from "../ui/button"
import AppHeader from "../layout/appHeader"
import { Alert, AlertDescription, AlertTitle, ANIMATION_VARIANTS } from "../ui/alert"
import { LoadingSpinner } from "../ui/spinner"

const RequireAuth = () => {
  const dispatch = useAppDispatch()
  const { showAlert } = useAlert()
  const token = useAppSelector(selectToken)
  const [isInitialized, setIsInitialized] = useState(false)
  const [isCollapsed, setIsCollapsed] = useState(true)
  const [triggerApi, { isError, currentData, isFetching }] = useLazyGetUserQuery()
  const [redirected, setRedirected] = useState(false)
  const [stalled, setStalled] = useState(false)
  const [hasRequested, setHasRequested] = useState(false)
  const [alreadyRedirected, setAlreadyRedirected] = useState(false)

  const redirectToLogin = () => {
    const target = typeof ACCOUNT_HOST === "string" && ACCOUNT_HOST ? ACCOUNT_HOST : "/account"
    window.location.assign(target)
  }

  useEffect(() => {
    const flag = typeof window !== "undefined" ? sessionStorage.getItem("authRedirectInProgress") : null
    if (!isFetching && isError && !redirected && flag !== "1") {
      showAlert("Session Expired", "Please sign in again to continue.", "warning")
      const target = typeof ACCOUNT_HOST === "string" && ACCOUNT_HOST ? ACCOUNT_HOST : "/account"
      setRedirected(true)
      try {
        sessionStorage.setItem("authRedirectInProgress", "1")
      } catch {
        void 0
      }
      const t = setTimeout(() => window.location.assign(target), 1200)
      return () => clearTimeout(t)
    }
  }, [showAlert, isFetching, isError, redirected])

  useEffect(() => {
    try {
      const flag = typeof window !== "undefined" ? sessionStorage.getItem("authRedirectInProgress") === "1" : false
      setAlreadyRedirected(flag)
    } catch {
      void 0
    }
  }, [])

  useEffect(() => {
    if (!isFetching && currentData) {
      dispatch(setUser(currentData))
      setIsInitialized(true)
      setStalled(false)
      try {
        sessionStorage.removeItem("authRedirectInProgress")
      } catch {
        void 0
      }
    }
  }, [isFetching, currentData, dispatch])

  useEffect(() => {
    if (hasRequested) return
    if (!token && !isFetching && !isInitialized && !redirected && !isError) {
      setHasRequested(true)
      triggerApi()
    } else if (token && !isFetching && !isInitialized && !redirected) {
      setHasRequested(true)
      triggerApi()
    }
  }, [token, triggerApi, redirected, isFetching, isInitialized, isError, hasRequested])

  useEffect(() => {
    if (token && isFetching) {
      const t = setTimeout(() => setStalled(true), 4000)
      return () => clearTimeout(t)
    }
    setStalled(false)
  }, [token, isFetching])

  if (!token && !isInitialized && !isError) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background">
        <LoadingSpinner size="lg" className="text-primary" />
        <div className="text-center">
          <p className="mb-1 text-lg font-medium">Verifying Your Identity</p>
          <p className="text-sm text-muted-foreground">Please wait while we ensure a secure connection...</p>
        </div>
      </div>
    )
  }

  if (isError) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <motion.div layout variants={ANIMATION_VARIANTS.alert} initial="initial" animate="animate" exit="exit">
          <Alert variant="info" className="relative flex min-w-96 items-center overflow-hidden shadow-lg dark:shadow-lg">
            <LoadingSpinner size="default" className="mr-3 text-blue-800 dark:text-blue-300" />
            <div>
              <AlertTitle className="mb-1 text-base font-semibold">Preparing Your Secure Login</AlertTitle>
              <AlertDescription className="text-sm text-muted-foreground">Redirecting to the login page...</AlertDescription>
            </div>
          </Alert>
        </motion.div>
      </div>
    )
  }

  if (!token && ((isFetching && !stalled) || (!isInitialized && !stalled))) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background">
        <LoadingSpinner size="lg" className="text-primary" />
        <div className="text-center">
          <p className="mb-1 text-lg font-medium">Verifying Your Identity</p>
          <p className="text-sm text-muted-foreground">Please wait while we ensure a secure connection...</p>
        </div>
      </div>
    )
  }

  if (stalled && token) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <motion.div layout variants={ANIMATION_VARIANTS.alert} initial="initial" animate="animate" exit="exit">
          <Alert variant="warning" className="relative flex min-w-96 items-center overflow-hidden shadow-lg dark:shadow-lg">
            <LoadingSpinner size="default" className="mr-3 text-blue-800 dark:text-blue-300" />
            <div>
              <AlertTitle className="mb-1 text-base font-semibold">Connection stalled</AlertTitle>
              <AlertDescription className="text-sm text-muted-foreground">Retry fetching your session or sign out to re-login.</AlertDescription>
            </div>
          </Alert>
          <div className="mt-4 flex justify-center gap-3">
            <Button onClick={() => triggerApi()} className="border border-gray-200 bg-white p-2 text-sm text-gray-600 hover:bg-transparent">
              Retry
            </Button>
            <Button
              onClick={() => {
                dispatch(clearUserSession())
              }}
              className="border border-gray-200 bg-white p-2 text-sm text-gray-600 hover:bg-transparent"
            >
              Sign out
            </Button>
          </div>
        </motion.div>
      </div>
    )
  }

  return (
    <div className="flex h-screen flex-col">
      {!!isInitialized && <AppHeader isCollapsed={isCollapsed} setIsCollapsed={setIsCollapsed} />}
      <main className="ml-20 flex-1 bg-white dark:bg-gray-900">
        <Outlet />
      </main>
    </div>
  )
}

export default RequireAuth
