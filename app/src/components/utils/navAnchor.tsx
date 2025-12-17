import React from "react"
import { NavLink, NavLinkProps, useLocation } from "react-router-dom"

interface NavAnchorProps extends Omit<NavLinkProps, "className"> {
  to: string
  activeClassName?: string
  className?: string
  children: React.ReactNode
  activePaths?: string[]
}

export default function NavAnchor({ to, activeClassName = "", className = "", children, activePaths = [], ...rest }: NavAnchorProps) {
  const location = useLocation()

  const isPathMatching = (mappedActivePaths: string[], path: string): boolean => {
    return mappedActivePaths.some((pattern: string) => {
      let regexPattern = pattern.replace(/[.+?^${}()|[\]\\]/g, "\\$&")
      regexPattern = regexPattern.replace(/\*/g, ".*")
      const regex = new RegExp("^" + regexPattern + "$")
      return regex.test(path)
    })
  }

  return (
    <NavLink to={to} className={isPathMatching(activePaths, location.pathname) ? activeClassName : className} {...rest}>
      {children}
    </NavLink>
  )
}
