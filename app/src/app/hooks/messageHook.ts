import { Message, SearchCriterion, SearchResult, SearchState } from "@/features/aisearch/types"
import { useState, useCallback } from "react"

export const useMessages = () => {
  const [messages, setMessages] = useState<Message[]>([])

  const addMessage = useCallback((message: Omit<Message, "id" | "timestamp">) => {
    const newMessage: Message = {
      ...message,
      id: crypto.randomUUID(),
      timestamp: new Date()
    }

    setMessages((prev) => [...prev, newMessage])
    return newMessage
  }, [])

  const clearMessages = useCallback(() => {
    setMessages([])
  }, [])

  const updateMessage = useCallback((id: string, updates: Partial<Message>) => {
    setMessages((prev) => prev.map((msg) => (msg.id === id ? { ...msg, ...updates } : msg)))
  }, [])

  return {
    messages,
    addMessage,
    clearMessages,
    updateMessage
  }
}

// Hook for managing search state
export const useSearch = () => {
  const [searchState, setSearchState] = useState<SearchState>({
    query: "",
    criteria: [],
    isLoading: false,
    results: []
  })

  const updateQuery = useCallback((query: string) => {
    setSearchState((prev) => ({ ...prev, query }))
  }, [])

  const updateCriteria = useCallback((criteria: SearchCriterion[]) => {
    setSearchState((prev) => ({ ...prev, criteria }))
  }, [])

  const setLoading = useCallback((isLoading: boolean) => {
    setSearchState((prev) => ({ ...prev, isLoading }))
  }, [])

  const updateResults = useCallback((results: SearchResult[]) => {
    setSearchState((prev) => ({ ...prev, results }))
  }, [])

  const resetSearch = useCallback(() => {
    setSearchState({
      query: "",
      criteria: [],
      isLoading: false,
      results: []
    })
  }, [])

  return {
    searchState,
    updateQuery,
    updateCriteria,
    setLoading,
    updateResults,
    resetSearch
  }
}

// Hook for local storage persistence (optional)
export const useLocalStorage = <T>(key: string, initialValue: T) => {
  const [storedValue, setStoredValue] = useState<T>(() => {
    try {
      const item = window.localStorage.getItem(key)
      return item ? JSON.parse(item) : initialValue
    } catch (error) {
      console.error(`Error reading localStorage key "${key}":`, error)
      return initialValue
    }
  })

  const setValue = useCallback(
    (value: T | ((val: T) => T)) => {
      try {
        const valueToStore = value instanceof Function ? value(storedValue) : value
        setStoredValue(valueToStore)
        window.localStorage.setItem(key, JSON.stringify(valueToStore))
      } catch (error) {
        console.error(`Error setting localStorage key "${key}":`, error)
      }
    },
    [key, storedValue]
  )

  const removeValue = useCallback(() => {
    try {
      window.localStorage.removeItem(key)
      setStoredValue(initialValue)
    } catch (error) {
      console.error(`Error removing localStorage key "${key}":`, error)
    }
  }, [key, initialValue])

  return [storedValue, setValue, removeValue] as const
}

// Hook for managing search history
export const useSearchHistory = () => {
  const [history, setHistory, clearHistory] = useLocalStorage<string[]>("search-history", [])

  const addToHistory = useCallback(
    (query: string) => {
      if (query.trim()) {
        setHistory((prev) => {
          const filtered = prev.filter((item) => item !== query)
          return [query, ...filtered].slice(0, 10) // Keep only last 10 searches
        })
      }
    },
    [setHistory]
  )

  const removeFromHistory = useCallback(
    (query: string) => {
      setHistory((prev) => prev.filter((item) => item !== query))
    },
    [setHistory]
  )

  return {
    history,
    addToHistory,
    removeFromHistory,
    clearHistory
  }
}
