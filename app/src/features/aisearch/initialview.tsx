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
  const suggestionCards = [
    {
      icon: <Users className="size-5 text-[#335CFF]" />,
      text: "Provide list of top AI Engineers at Microsoft."
    },
    {
      icon: <Building2 className="size-5 text-[#335CFF]" />,
      text: "Which are the companies using Selenium with revenue over $20M."
    },
    {
      icon: <Calendar className="size-5 text-[#335CFF]" />,
      text: "Which companies showed up for CRM based conferences in the last 6 months."
    }
  ]

  const handleSuggestionClick = (suggestionText: string) => {
    onSearch(suggestionText)
  }

  return (
    <div className="flex flex-1 flex-col items-center justify-center px-6 py-12">
      <div className="w-full max-w-4xl space-y-8 text-start">
        {/* Main Title */}
        <div className="">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-blue-500 sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl 2xl:mb-12 2xl:text-7xl">
              Hello {user?.name ?? "there"}
            </h1>
            <h2 className="text-2xl font-normal text-gray-400 sm:text-3xl md:text-4xl lg:text-5xl xl:text-6xl 2xl:text-7xl">
              How can i help you today?
            </h2>
          </div>

          {/* Suggestion Cards */}
          <div className="my-16 grid grid-cols-1 gap-6 md:grid-cols-3">
            {suggestionCards.map((card, index) => (
              <Card
                key={index}
                className="group cursor-pointer border-gray-200 bg-gray-50 p-3 transition-all duration-200 hover:shadow-md"
                onClick={() => handleSuggestionClick(card.text)}
              >
                <CardHeader className="p-0 pb-3">
                  <div className="w-fit rounded-full bg-[#476CFF1A] p-2 transition-colors group-hover:bg-blue-100">{card.icon}</div>
                </CardHeader>
                <CardContent className="flex-nowrap p-0 text-left">
                  <span className="text-left text-sm font-medium leading-5 tracking-neg006 text-gray-900">{card.text}</span>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>

        {/* Search Input */}
        <AISearchInput onSearch={onSearch} placeholder="Enter prompt to generate filters" />
      </div>
    </div>
  )
}

export default InitialView
