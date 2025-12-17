import { cn } from "@/lib/utils"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

const ANIMATION_VARIANTS = {
  alert: {
    initial: {
      opacity: 0,
      y: -16,
      scale: 0.95,
      x: 20,
      rotate: -2
    },
    animate: {
      opacity: 1,
      y: 0,
      scale: 1,
      x: 0,
      rotate: 0,
      transition: {
        duration: 0.4,
        ease: [0.175, 0.885, 0.32, 1]
      }
    },
    exit: {
      opacity: 0,
      x: 50,
      transition: {
        duration: 0.2,
        ease: [0.4, 0, 1, 1]
      }
    }
  },
  progressBar: {
    initial: { width: "100%" },
    animate: (duration: number) => ({
      width: "0%",
      transition: {
        duration,
        ease: "linear"
      }
    })
  }
} as const

const ALERT_VARIANTS = {
  default: "bg-white dark:bg-stone-900 text-stone-900 dark:text-stone-100 border-stone-200 dark:border-stone-800 shadow-sm",
  success:
    "border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-300 [&>svg]:text-green-600 dark:[&>svg]:text-green-400",
  info: "border-blue-200 dark:border-blue-900 bg-blue-50 dark:bg-blue-900/20 text-blue-800 dark:text-blue-300 [&>svg]:text-blue-600 dark:[&>svg]:text-blue-400",
  warning:
    "border-yellow-200 dark:border-yellow-900 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-300 [&>svg]:text-yellow-600 dark:[&>svg]:text-yellow-400",
  destructive:
    "border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-900/20 text-red-800 dark:text-red-300 [&>svg]:text-red-600 dark:[&>svg]:text-red-400"
} as const

const alertVariants = cva(
  "relative w-full rounded-lg border p-4 text-sm backdrop-blur-sm transition-colors duration-200 [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg~*]:pl-7",
  {
    variants: { variant: ALERT_VARIANTS },
    defaultVariants: { variant: "default" }
  }
)

type AlertProps = React.HTMLAttributes<HTMLDivElement> & VariantProps<typeof alertVariants>

const Alert = React.forwardRef<HTMLDivElement, AlertProps>(({ className, variant, ...props }, ref) => (
  <div ref={ref} role="alert" className={cn(alertVariants({ variant }), className)} {...props} />
))
Alert.displayName = "Alert"

const AlertTitle = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLHeadingElement>>(({ className, ...props }, ref) => (
  <h5 ref={ref} className={cn("mb-1 font-medium leading-none tracking-tight", className)} {...props} />
))
AlertTitle.displayName = "AlertTitle"

const AlertDescription = React.forwardRef<HTMLParagraphElement, React.HTMLAttributes<HTMLParagraphElement>>(({ className, ...props }, ref) => (
  <div ref={ref} className={cn("text-sm [&_p]:leading-relaxed opacity-90", className)} {...props} />
))
AlertDescription.displayName = "AlertDescription"

export { Alert, AlertDescription, AlertTitle, ANIMATION_VARIANTS, type AlertProps }
