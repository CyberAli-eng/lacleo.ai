import { ACCOUNT_HOST } from "@/app/constants/apiConstants"
import { useAppDispatch, useAppSelector } from "@/app/hooks/reduxHooks"
import { Button } from "@/components/ui/button"
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from "@/components/ui/dropdown-menu"
import { useLogoutMutation } from "@/features/settings/slice/apiSlice"
import { clearUserSession, selectTheme, selectUser, setTheme } from "@/features/settings/slice/settingSlice"
import { TThemeMode } from "@/interface/settings/slice"
import { Code2, LogOut, LucideIcon, Monitor, Moon, ScrollText, Search, Sun, User, User2, Menu } from "lucide-react"
import { FC, useEffect } from "react"
import { Link } from "react-router-dom"
import { LoadingSpinner } from "../ui/spinner"
import NavAnchor from "../utils/navAnchor"
import LacleoLogo from "./../../static/media/logo/lacleo_logo.svg?react"
import LacleroIcon from "./../../static/media/logo/lacleo_icon.svg?react"
import SearchEyeIcon from "./../../static/media/icons/search-eye-line.svg?react"
import Credits from "../ui/credits"

const themes: Array<{ value: TThemeMode; label: string; icon: LucideIcon; description: string }> = [
  {
    value: "system",
    label: "System",
    icon: Monitor,
    description: "Follow system theme"
  },
  {
    value: "light",
    label: "Light",
    icon: Sun,
    description: "Bright and clear"
  },
  {
    value: "dark",
    label: "Dark",
    icon: Moon,
    description: "Easy on the eyes"
  }
]

interface ThemeSwitcherProps {
  themeMode: TThemeMode
  onThemeChange: (theme: TThemeMode) => void
  isCollapsed?: boolean
}

const ThemeSwitcher: FC<ThemeSwitcherProps> = ({ themeMode, onThemeChange, isCollapsed = false }) => {
  const ThemeIcon: FC<{ mode: TThemeMode }> = ({ mode }) => {
    const theme = themes.find((t) => t.value === mode)
    const Icon = theme?.icon || Monitor
    return <Icon className="size-4" />
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          size={isCollapsed ? "icon" : "default"}
          className={`${
            isCollapsed ? "size-10" : "w-full justify-start"
          } gap-2 transition-all duration-500 ease-in-out hover:bg-accent dark:hover:bg-accent`}
          aria-label="Toggle theme"
        >
          <ThemeIcon mode={themeMode} />
          <span className={`transition-all duration-500 ease-in-out ${isCollapsed ? "w-0 overflow-hidden opacity-0" : "w-auto opacity-100"}`}>
            Theme
          </span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align={isCollapsed ? "end" : "start"} className="w-64">
        {themes.map(({ value, label, icon: Icon, description }) => (
          <DropdownMenuItem key={value} className="flex cursor-pointer items-center px-4 py-2 focus:bg-accent" onClick={() => onThemeChange(value)}>
            <div className="flex items-center space-x-3">
              <div className={`rounded-md p-1 ${themeMode === value ? "bg-accent" : ""}`}>
                <Icon className="size-4" />
              </div>
              <div className="space-y-1">
                <p className="text-sm font-medium leading-none">{label}</p>
                <p className="text-xs text-muted-foreground">{description}</p>
              </div>
            </div>
            {themeMode === value && (
              <span className="absolute right-4 flex size-3 items-center justify-center">
                <span className="size-2 rounded-full bg-primary"></span>
              </span>
            )}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

// Add interface for AppHeader props
interface AppHeaderProps {
  isCollapsed: boolean
  setIsCollapsed: (collapsed: boolean) => void
}

const AppHeader: FC<AppHeaderProps> = ({ isCollapsed, setIsCollapsed }) => {
  const dispatch = useAppDispatch()
  const user = useAppSelector(selectUser)
  const themeMode = useAppSelector(selectTheme)
  const [triggerMutation, { isError, isLoading, data }] = useLogoutMutation()

  const onThemeChange = (value: TThemeMode) => {
    dispatch(setTheme(value))
  }

  const handleMouseEnter = () => {
    setIsCollapsed(false)
  }

  const handleMouseLeave = () => {
    setIsCollapsed(true)
  }

  useEffect(() => {
    if (!isLoading && (data || isError)) {
      dispatch(clearUserSession())
      const target = typeof ACCOUNT_HOST === "string" && ACCOUNT_HOST ? ACCOUNT_HOST : ""
      if (/^https?:\/\//.test(target)) {
        window.location.href = target
      }
    }
  }, [isError, data, isLoading, dispatch])

  return (
    <aside
      className={`fixed left-0 top-0 z-50 h-full border-r border-border bg-white transition-all duration-500 ease-in-out dark:bg-gray-900 ${
        isCollapsed ? "w-20" : "w-72"
      }`}
      onMouseEnter={handleMouseEnter}
      onMouseLeave={handleMouseLeave}
    >
      <div className="flex h-full flex-col">
        {/* Header with Logo */}
        <div
          className={`flex items-center border-b border-border p-4 transition-all duration-500 ease-in-out ${
            isCollapsed ? "justify-center" : "justify-start"
          }`}
        >
          {isCollapsed ? (
            <NavAnchor to="/app" className="flex items-center gap-2">
              <LacleroIcon className="size-[45px] transition-all duration-500 ease-in-out dark:invert" />
            </NavAnchor>
          ) : (
            <NavAnchor to="/app" className="flex items-center gap-2">
              <LacleoLogo className="w-[115px] transition-all duration-500 ease-in-out dark:invert" />
            </NavAnchor>
          )}
        </div>

        {/* Navigation */}
        <nav className="flex-1 space-y-2 px-3 py-5">
          <div className={`${isCollapsed ? "flex items-center justify-center" : ""}`}>
            <span className="text-xs text-[#A3A3A3]">MAIN</span>
          </div>
          <NavAnchor
            to="/app/search"
            activePaths={["/app/search/*"]}
            activeClassName={`flex items-center  rounded-lg p-2 text-sm font-medium text-gray-900 dark:text-white bg-gray-100 bg-background dark:bg-gray-800 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700 transition-all duration-500 ease-in-out ${
              isCollapsed ? "justify-center" : "gap-3"
            }`}
            className={`flex items-center rounded-lg p-2 text-sm font-medium text-gray-600 transition-all duration-500 ease-in-out hover:bg-accent hover:text-foreground dark:text-gray-400 ${
              isCollapsed ? "justify-center" : "gap-3 "
            }`}
          >
            <SearchEyeIcon className="shrink-0" />
            <span className={`transition-all duration-500 ease-in-out ${isCollapsed ? "w-0 overflow-hidden opacity-0" : "w-auto opacity-100"}`}>
              Search
            </span>
          </NavAnchor>

          <NavAnchor
            to="/app/lists"
            activeClassName={`flex items-center  rounded-lg p-2 text-sm font-medium text-gray-900 dark:text-white bg-gray-100 bg-background dark:bg-gray-800 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700 transition-all duration-500 ease-in-out ${
              isCollapsed ? "justify-center" : "gap-3"
            }`}
            className={`flex items-center rounded-lg p-2 text-sm font-medium text-gray-600 transition-all duration-500 ease-in-out hover:bg-accent hover:text-foreground dark:text-gray-400 ${
              isCollapsed ? "justify-center" : "gap-3 "
            }`}
            activePaths={["/app/lists"]}
          >
            <ScrollText className="size-5 shrink-0" />
            <span className={`transition-all duration-500 ease-in-out ${isCollapsed ? "w-0 overflow-hidden opacity-0" : "w-auto opacity-100"}`}>
              Lists
            </span>
          </NavAnchor>

          <NavAnchor
            to="/app/api"
            activePaths={["/app/api"]}
            activeClassName={`flex items-center  rounded-lg p-2 text-sm font-medium text-gray-900 dark:text-white bg-gray-100 bg-background dark:bg-gray-800 shadow-sm ring-1 ring-gray-200 dark:ring-gray-700 transition-all duration-500 ease-in-out ${
              isCollapsed ? "justify-center" : "gap-3"
            }`}
            className={`flex items-center rounded-lg p-2 text-sm font-medium text-gray-600 transition-all duration-500 ease-in-out hover:bg-accent hover:text-foreground dark:text-gray-400 ${
              isCollapsed ? "justify-center" : "gap-3 "
            }`}
          >
            <Code2 className="size-5 shrink-0" />
            <span className={`transition-all duration-500 ease-in-out ${isCollapsed ? "w-0 overflow-hidden opacity-0" : "w-auto opacity-100"}`}>
              API
            </span>
          </NavAnchor>
        </nav>
        {!isCollapsed && (
          <div className="p-3">
            <Credits />
          </div>
        )}

        {/* Bottom Section - Theme Switcher and User Menu */}
        <div className="space-y-2 border-t border-border p-4">
          <ThemeSwitcher themeMode={themeMode} onThemeChange={onThemeChange} isCollapsed={isCollapsed} />

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                variant="ghost"
                className={`${isCollapsed ? "size-10 p-0" : "w-full justify-start"} gap-2 transition-all duration-500 ease-in-out hover:bg-accent`}
              >
                {user?.profile_photo_url ? (
                  <img
                    className="size-6 shrink-0 rounded-full object-cover transition-all duration-500 ease-in-out"
                    src={user?.profile_photo_url || ""}
                    alt={user?.name || "User"}
                  />
                ) : (
                  <User className="size-4 shrink-0 transition-all duration-500 ease-in-out" />
                )}
                <div
                  className={`flex min-w-0 flex-col items-start text-left transition-all duration-500 ease-in-out ${
                    isCollapsed ? "w-0 overflow-hidden opacity-0" : "w-auto opacity-100"
                  }`}
                >
                  <span className="truncate text-sm font-medium">{user?.name || "User"}</span>
                  <span className="truncate text-xs text-muted-foreground">{user?.email || ""}</span>
                </div>
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align={isCollapsed ? "end" : "start"} className="w-56">
              <div className="flex items-center justify-start gap-2 p-2">
                <div className="flex flex-col space-y-1">
                  <span className="mb-1 block text-sm text-foreground/60">Signed in as</span>
                  <p className="text-sm font-medium leading-none">{user?.name || "User"}</p>
                  <p className="text-xs leading-none text-muted-foreground">{user?.email || ""}</p>
                </div>
              </div>
              <DropdownMenuSeparator />
              <DropdownMenuItem asChild>
                <Link to={`${ACCOUNT_HOST}/user/profile`} className="flex w-full cursor-pointer items-center">
                  <User2 className="mr-2 size-4" />
                  <span>Profile</span>
                </Link>
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => triggerMutation()} className="text-red-600 focus:text-red-600">
                {isLoading ? <LoadingSpinner size="sm" /> : <LogOut className="mr-2 size-4" />}
                <span>Log out</span>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </aside>
  )
}
export default AppHeader
