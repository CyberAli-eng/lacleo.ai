import { MessageBubbleProps, MessageListProps } from "@/features/aisearch/types"
import React, { useEffect, useRef } from "react"

// Message Bubble Component
const MessageBubble: React.FC<MessageBubbleProps> = ({ message }) => {
  const getMessageStyles = () => {
    switch (message.type) {
      case "user":
        return "bg-[#F7F7F7] text-[#5C5C5C] font-medium ml-auto max-w-xl"
      case "ai":
        return "bg-gray-100 text-gray-900 mr-auto max-w-xl"
      case "system":
        return "mx-auto max-w-4xl bg-transparent"
      default:
        return "bg-gray-100 text-gray-900 mr-auto max-w-xl"
    }
  }

  const renderContent = () => {
    // If there's a component (like SearchConfirmation), render it
    if (message.component) {
      return <div className="w-full">{message.component}</div>
    }

    // Otherwise render text content
    return (
      <div className={`rounded-2xl px-4 py-3 ${getMessageStyles()}`}>
        <p className="text-sm leading-relaxed">{message.content}</p>
      </div>
    )
  }

  return (
    <div className={`mb-4 flex w-full ${message.type === "user" ? "justify-end" : message.type === "system" ? "justify-center" : "justify-start"}`}>
      {renderContent()}
    </div>
  )
}

// Message List Component
const MessageList: React.FC<MessageListProps> = ({ messages }) => {
  const messagesEndRef = useRef<HTMLDivElement>(null)

  // Auto-scroll to bottom when new messages arrive
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" })
  }

  useEffect(() => {
    scrollToBottom()
  }, [messages])

  if (messages.length === 0) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <p className="text-gray-500">No messages yet. Start a conversation!</p>
      </div>
    )
  }

  return (
    <div className="flex-1 space-y-4 overflow-y-auto p-4">
      <div className="mx-auto max-w-4xl">
        {messages.map((message) => (
          <MessageBubble key={message.id} message={message} />
        ))}
        <div ref={messagesEndRef} />
      </div>
    </div>
  )
}

export { MessageList, MessageBubble }
