import React from "react"
import { Card, CardContent, CardHeader } from "@/components/ui/card"
import { Building2, Calendar, Users } from "lucide-react"
import AISearchInput from "@/components/ui/aisearchinput"
import { useAppSelector } from "@/app/hooks/reduxHooks"

interface InitialViewProps {
  onSearch: (query: string) => void
}

const InitialView: React.FC<InitialViewProps> = ({ onSearch }) => {
  const user = useAppSelector((s) => s.setting.user)


  return (
    <div className="flex flex-1 flex-col items-center justify-center px-6 py-12">
      <div className="w-full max-w-md space-y-6 text-left">
        {/* Main Title */}
        <div className="space-y-2">
          <h1 className="text-3xl font-bold tracking-tight text-blue-600 dark:text-blue-500">
            Hello {user?.name?.split(' ')[0] ?? "there"},
          </h1>
          <h2 className="text-2xl font-medium text-gray-400 dark:text-gray-500">
            How can I help you?
          </h2>
        </div>
        <div className="w-full mt-8">
          {/* Search Input */}
          <AISearchInput onSearch={onSearch} placeholder="Enter prompt to generate filters" />
        </div>
      </div>
    </div>
  )
}

export default InitialView
