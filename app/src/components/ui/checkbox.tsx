import { Check } from "lucide-react"
import React from "react"

interface CheckboxProps {
  checked: boolean
  onChange: (checked: boolean) => void
  className?: string
  disabled?: boolean
}

const Checkbox: React.FC<CheckboxProps> = ({ checked, onChange, className = "", disabled = false }) => {
  const handleClick = () => {
    if (!disabled) onChange(!checked)
  }

  return (
    <button
      type="button"
      onClick={handleClick}
      disabled={disabled}
      className={`flex size-[18px] items-center justify-center rounded border-2 transition-colors focus:outline-none disabled:opacity-50 ${
        checked ? "border-blue-500 bg-blue-500 text-primary-foreground" : "border-input hover:border-primary/50"
      } ${className}`}
      aria-pressed={checked}
      aria-label={checked ? "Checked" : "Unchecked"}
    >
      {!!checked && <Check className="size-3" />}
    </button>
  )
}

export default Checkbox
