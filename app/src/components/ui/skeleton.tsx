import { cn } from "@/lib/utils"
import { HTMLAttributes } from "react"

function Skeleton({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("animate-pulse rounded-md bg-stone-900/10 dark:bg-stone-50/10", className)} {...props} />
}

export { Skeleton }
