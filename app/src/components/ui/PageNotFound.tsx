import { Card } from "@/components/ui/card"
import { motion } from "framer-motion"
import { ArrowLeft, ExternalLink, Home } from "lucide-react"
import PageNotFoundSVG from "../../static/media/avatars/404.svg?react"

export default function PageNotFound() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 via-gray-100 to-gray-50 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950">
      <Card className="relative min-h-screen w-full overflow-hidden border border-gray-200/50 bg-white/80 p-8 shadow-xl backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/80 md:p-20">
        <div className="absolute inset-0 overflow-hidden">
          <div className="absolute -right-32 -top-32 size-64 rounded-full bg-blue-500/10 blur-3xl" />
          <div className="absolute -bottom-32 -left-32 size-64 rounded-full bg-purple-500/10 blur-3xl" />
        </div>

        <div className="relative flex flex-col items-center justify-center space-y-12">
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="space-y-6 text-center"
          >
            <h1 className="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-7xl font-extrabold text-transparent dark:from-blue-400 dark:to-purple-400 md:text-8xl">
              404
            </h1>
            <div className="space-y-3">
              <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Page Not Found</h2>
              <p className="max-w-md text-gray-600 dark:text-gray-300">
                The page you&apos;re looking for doesn&apos;t exist or has been moved. Let&apos;s get you back on track.
              </p>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.5, delay: 0.2 }}
            className="relative h-48 w-full max-w-md md:h-64"
          >
            <div className="absolute inset-0 flex items-center justify-center">
              <PageNotFoundSVG className="size-full object-contain dark:invert" />
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5, delay: 0.4 }}
            className="w-full max-w-2xl space-y-6"
          >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <a href="/" className="group block">
                <div
                  className="flex items-start space-x-4 rounded-lg border border-gray-200 bg-white p-6 transition-all duration-200 hover:border-blue-500
                  hover:shadow-lg hover:shadow-blue-500/10 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-400
                  dark:hover:shadow-blue-400/10"
                >
                  <Home className="mt-1 size-5 shrink-0 text-blue-500 transition-transform duration-200 group-hover:scale-110 dark:text-blue-400" />
                  <div className="flex-1">
                    <div className="flex items-center justify-between">
                      <h3 className="font-semibold text-gray-900 transition-colors duration-200 group-hover:text-blue-500 dark:text-white dark:group-hover:text-blue-400">
                        Return Home
                      </h3>
                      <div className="hidden group-hover:block">
                        <ArrowLeft className="size-4 rotate-180 text-blue-500 dark:text-blue-400" />
                      </div>
                    </div>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Back to our main page</p>
                  </div>
                </div>
              </a>

              <a href="https://lacleo-website-staging.pages.dev/contact-us" className="group block">
                <div
                  className="flex items-start space-x-4 rounded-lg border border-gray-200 bg-white p-6 transition-all duration-200 hover:border-blue-500
                  hover:shadow-lg hover:shadow-blue-500/10 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-400
                  dark:hover:shadow-blue-400/10"
                >
                  <ExternalLink className="mt-1 size-5 shrink-0 text-blue-500 transition-transform duration-200 group-hover:scale-110 dark:text-blue-400" />
                  <div className="flex-1">
                    <div className="flex items-center justify-between">
                      <h3 className="font-semibold text-gray-900 transition-colors duration-200 group-hover:text-blue-500 dark:text-white dark:group-hover:text-blue-400">
                        Contact Center
                      </h3>
                      <div className="hidden group-hover:block">
                        <ExternalLink className="size-4 text-blue-500 dark:text-blue-400" />
                      </div>
                    </div>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">Visit our support pages</p>
                  </div>
                </div>
              </a>
            </div>

            <button
              onClick={() => window.history.back()}
              className="mx-auto flex items-center space-x-2 text-sm text-gray-500 transition-colors duration-200 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
              <ArrowLeft className="size-4" />
              <span>Go back to previous page</span>
            </button>
          </motion.div>

          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ duration: 0.5, delay: 0.6 }} className="text-center">
            <p className="text-sm text-gray-500 dark:text-gray-400">
              Error Code: <span className="font-mono font-medium">404_PAGE_NOT_FOUND</span>
            </p>
          </motion.div>
        </div>
      </Card>
    </div>
  )
}
