import { Button } from "@/components/ui/button"
import { Sparkles } from "lucide-react"
import React, { useState } from "react"

interface AISearchInputProps {
  placeholder?: string
  buttonText?: string
  buttonIcon?: React.ReactNode
  onSearch?: (query: string) => void
  className?: string
  disabled?: boolean
  maxHeight?: number
}

const AISearchInput: React.FC<AISearchInputProps> = ({
  placeholder = "Enter prompt to generate filters",
  buttonText = "Run AI Search",
  buttonIcon = <Sparkles className="size-5" />,
  onSearch,
  className = "",
  disabled = false,
  maxHeight = 120
}) => {
  const [searchQuery, setSearchQuery] = useState("")

  const autoResize = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    const textarea = e.target
    textarea.style.height = "auto"
    textarea.style.height = `${Math.min(textarea.scrollHeight, maxHeight)}px`
  }

  const handleSearch = () => {
    if (onSearch && searchQuery.trim()) {
      onSearch(searchQuery.trim())
    }
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === "Enter" && !e.shiftKey) {
      e.preventDefault()
      handleSearch()
    }
  }

  return (
    <div className={`mx-auto w-full space-y-4 ${className}`}>
      <div className="rounded-lg border bg-gray-50 p-[18px]">
        <textarea
          value={searchQuery}
          onChange={(e) => {
            setSearchQuery(e.target.value)
            autoResize(e)
          }}
          onKeyDown={handleKeyDown}
          className="max-h-48 w-full resize-none overflow-auto bg-gray-50 text-sm font-medium  text-gray-950 focus:border-transparent focus:outline-none"
          rows={1}
          placeholder={placeholder}
          disabled={disabled}
        />
        <div className="flex w-full items-center justify-end">
          <Button
            onClick={handleSearch}
            disabled={disabled || !searchQuery.trim()}
            className="items-center gap-2 rounded-lg bg-blue-500 p-[10px] text-sm font-medium text-white transition-colors hover:bg-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
          >
            {buttonIcon}
            {buttonText}
          </Button>
        </div>
      </div>
    </div>
  )
}

export default AISearchInput

// v2
// import React, { useState, useRef, useEffect } from "react"
// import { Sparkles } from "lucide-react"
// import { AISearchInputProps } from "@/interface/aisearch/types"

// const AISearchInput: React.FC<AISearchInputProps> = ({
//   onSearch,
//   placeholder = "Enter prompt to generate filters",
//   defaultValue = "",
//   disabled = false
// }) => {
//   const [value, setValue] = useState(defaultValue)
//   const textareaRef = useRef<HTMLTextAreaElement>(null)

//   const autoResize = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
//     const textarea = e.target
//     setValue(textarea.value)

//     // Reset height to auto to get the correct scrollHeight
//     textarea.style.height = "auto"
//     // Set height to scrollHeight with max limit
//     textarea.style.height = `${Math.min(textarea.scrollHeight, 120)}px`
//   }

//   const handleSubmit = () => {
//     if (value.trim() && !disabled) {
//       onSearch(value.trim())
//       setValue("") // Clear after search

//       // Reset textarea height
//       if (textareaRef.current) {
//         textareaRef.current.style.height = "auto"
//       }
//     }
//   }

//   const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
//     if (e.key === "Enter" && !e.shiftKey) {
//       e.preventDefault()
//       handleSubmit()
//     }
//   }

//   // Auto-focus on mount (optional)
//   useEffect(() => {
//     if (textareaRef.current && !defaultValue) {
//       textareaRef.current.focus()
//     }
//   }, [defaultValue])

//   return (
//     <div className="mx-auto w-full max-w-3xl">
//       <div className="relative">
//         <textarea
//           ref={textareaRef}
//           value={value}
//           onChange={autoResize}
//           onKeyDown={handleKeyDown}
//           placeholder={placeholder}
//           disabled={disabled}
//           className="w-full resize-none rounded-lg border border-gray-300 p-4 pr-32 text-gray-700 focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100"
//           rows={2}
//           style={{ minHeight: "56px" }}
//         />
//         <button
//           onClick={handleSubmit}
//           disabled={!value.trim() || disabled}
//           className="absolute right-3 top-1/2 flex -translate-y-1/2 items-center gap-2 rounded-lg bg-blue-500 px-6 py-2 font-medium text-white transition-colors hover:bg-blue-600 disabled:cursor-not-allowed disabled:bg-gray-300"
//         >
//           <Sparkles className="size-4" />
//           Run AI Search
//         </button>
//       </div>
//     </div>
//   )
// }

// export default AISearchInput
