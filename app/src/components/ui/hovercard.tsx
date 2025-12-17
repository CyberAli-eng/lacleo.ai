// components/ui/hover-card.tsx
import React, { useState } from "react"
import { cn } from "@/lib/utils"

interface HoverCardProps {
  trigger: React.ReactNode
  content: React.ReactNode
  className?: string
  contentClassName?: string
  side?: "top" | "right" | "bottom" | "left"
  align?: "start" | "center" | "end"
  sideOffset?: number
}

export const HoverCard: React.FC<HoverCardProps> = ({
  trigger,
  content,
  className,
  contentClassName,
  side = "left",
  align = "start",
  sideOffset = 8
}) => {
  const [isOpen, setIsOpen] = useState(false)

  return (
    <div className={cn("relative inline-block", className)} onMouseEnter={() => setIsOpen(true)} onMouseLeave={() => setIsOpen(false)}>
      {trigger}
      {!!isOpen && (
        <div
          className={cn(
            "absolute z-50 w-[400px] rounded-xl border bg-white p-4 shadow-lg",
            // side placement
            side === "right" && "left-full",
            side === "left" && "right-full",
            side === "top" && "bottom-full",
            side === "bottom" && "top-full",
            // alignment along the perpendicular axis
            side === "right" && align === "start" && "top-0",
            side === "right" && align === "center" && "top-1/2 -translate-y-1/2",
            side === "right" && align === "end" && "bottom-0",

            side === "left" && align === "start" && "top-0",
            side === "left" && align === "center" && "top-1/2 -translate-y-1/2",
            side === "left" && align === "end" && "bottom-0",

            side === "top" && align === "start" && "left-0",
            side === "top" && align === "center" && "left-1/2 -translate-x-1/2",
            side === "top" && align === "end" && "right-0",

            side === "bottom" && align === "start" && "left-0",
            side === "bottom" && align === "center" && "left-1/2 -translate-x-1/2",
            side === "bottom" && align === "end" && "right-0",
            contentClassName
          )}
          style={{
            marginLeft: side === "right" ? sideOffset : undefined,
            marginRight: side === "left" ? sideOffset : undefined,
            marginTop: side === "bottom" ? sideOffset : undefined,
            marginBottom: side === "top" ? sideOffset : undefined
          }}
        >
          {content}
        </div>
      )}
    </div>
  )
}
