import useDocumentTitle from "@/app/hooks/documentTitleHook"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Avatar } from "@/components/ui/avatar"
import { useCompanyLogoQuery } from "../searchTable/slice/apiSlice"
import { Bell, Clock } from "lucide-react"
import ComingSoonSVG from "../../static/media/avatars/coming_soon.svg?react"
export default function Lists() {
  useDocumentTitle("Saved Lists")
  return (
    <div className="flex h-full items-center bg-white p-6 dark:bg-gray-900">
      <div className="mx-auto w-full max-w-4xl">
        <Card className="h-full overflow-hidden rounded-lg border-gray-200/50 bg-white/80 p-8 shadow-xl dark:border-gray-700/50 dark:bg-gray-800/80 md:p-20">
          <div className="flex h-full flex-col justify-between">
            {/* Header */}
            <div className="space-y-2 text-center">
              <h1 className="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-3xl font-bold text-transparent dark:from-blue-400 dark:to-purple-400">
                Coming Soon!
              </h1>
              <p className="text-base text-gray-600 dark:text-gray-300">We&apos;re working hard to bring you something amazing</p>
            </div>
            {/* SVG Illustration */}
            <div className="flex flex-1 items-center justify-center py-4">
              <ComingSoonSVG className="mx-auto w-full max-w-md dark:brightness-90 dark:invert" />
            </div>
            {/* Features Preview */}
            <div className="mx-auto grid max-w-2xl grid-cols-1 gap-4 md:grid-cols-2">
              <div className="flex items-start space-x-3">
                <Clock className="mt-1 size-5 shrink-0 text-blue-500 dark:text-blue-400" />
                <div className="text-left">
                  <h3 className="font-semibold text-gray-900 dark:text-white">Launch Timeline</h3>
                  <p className="text-sm text-gray-600 dark:text-gray-300">Stay tuned for our upcoming release with exciting new features</p>
                </div>
              </div>
              <div className="flex items-start space-x-3">
                <Bell className="mt-1 size-5 shrink-0 text-blue-500 dark:text-blue-400" />
                <div className="text-left">
                  <h3 className="font-semibold text-gray-900 dark:text-white">Get Notified</h3>
                  <p className="text-sm text-gray-600 dark:text-gray-300">Be the first to know when we launch by staying connected</p>
                </div>
              </div>
            </div>

            {/* Logo Preview */}
            <Card className="mt-8">
              <CardHeader className="p-4">
                <CardTitle className="text-sm">Logo Preview (Saved Lists)</CardTitle>
              </CardHeader>
              <CardContent className="p-4 pt-0">
                <div className="space-y-3">
                  <LogoListItem company="Netflix" website="netflix.com" />
                  <LogoListItem company="Microsoft" website="microsoft.com" />
                </div>
              </CardContent>
            </Card>
          </div>
        </Card>
      </div>
    </div>
  )
}

function LogoListItem({ company, website }: { company: string; website: string }) {
  const normalizedDomain = (website || "")
    .replace(/^https?:\/\//, "")
    .replace(/^www\./, "")
    .trim()
  const { data: logoData } = useCompanyLogoQuery({ domain: normalizedDomain }, { skip: normalizedDomain === "" })
  const logoUrl = logoData?.logo_url || null
  return (
    <div className="flex items-center gap-3">
      <Avatar className="flex size-8 items-center justify-center rounded-full border">
        {logoUrl ? <img src={logoUrl} alt={company || "Company logo"} className="size-5" /> : <span className="text-xs">{company[0]}</span>}
      </Avatar>
      <div className="flex flex-col">
        <span className="text-sm font-medium text-gray-950">{company}</span>
        <span className="text-xs text-gray-600">{normalizedDomain}</span>
      </div>
    </div>
  )
}
