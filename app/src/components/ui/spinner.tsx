import { Loader2 } from "lucide-react"
import { FC } from "react"

type SpinnerSize = "sm" | "default" | "lg" | "xl"

interface LoadingSpinnerProps {
  size?: SpinnerSize
  className?: string
}

const sizeClasses: Record<SpinnerSize, string> = {
  sm: "h-4 w-4",
  default: "h-6 w-6",
  lg: "h-8 w-8",
  xl: "h-12 w-12"
}

export const LoadingSpinner: FC<LoadingSpinnerProps> = ({ size = "default", className = "" }) => {
  return (
    <div className="flex items-center justify-center">
      <Loader2 className={`animate-spin text-primary ${sizeClasses[size]} ${className}`} />
    </div>
  )
}
