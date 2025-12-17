import React from "react"

// Message types for chat
export type MessageType = "user" | "system" | "ai"

export interface Message {
  id: string
  type: MessageType
  content: string
  component?: React.ReactNode
  timestamp: Date
}

// Search criteria types
export interface SearchCriterion {
  id: string
  label: string
  value: string
  checked: boolean
}

// Search result interface
export interface SearchResult {
  id: string
  title: string
  description?: string
  [key: string]: unknown
}

// Search state
export interface SearchState {
  query: string
  criteria: SearchCriterion[]
  isLoading: boolean
  results?: SearchResult[]
}

// Component prop interfaces
export interface AISearchInputProps {
  onSearch: (query: string) => void
  placeholder?: string
  defaultValue?: string
  disabled?: boolean
}

export interface SearchConfirmationProps {
  title?: string
  criteria: SearchCriterion[]
  onApply: (criteria: SearchCriterion[]) => void
  onCriterionChange?: (id: string, checked: boolean) => void
  applyButtonText?: string
  className?: string
  disabled?: boolean
}

export interface MessageListProps {
  messages: Message[]
}

export interface MessageBubbleProps {
  message: Message
}

export interface AiChatPageProps {
  initialQuery: string
  onBackToHome?: () => void
}
