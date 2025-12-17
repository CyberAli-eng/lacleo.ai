import useDocumentTitle from "@/app/hooks/documentTitleHook"
import { Alert, AlertDescription } from "@/components/ui/alert"
import { Button } from "@/components/ui/button"
import { Card } from "@/components/ui/card"
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Textarea } from "@/components/ui/textarea"
import { motion } from "framer-motion"
import { AlertCircle, ArrowRight, Code2, Loader2, LucideSparkles } from "lucide-react"
import { ChangeEvent, FormEvent, useState } from "react"

interface FormData {
  companyName: string
  name: string
  email: string
  phone: string
  message: string
}

type SubmitStatus = "success" | "error" | null

export default function APIAccess() {
  useDocumentTitle("LaCleo API Access", "Products")
  const [isSubmitting, setIsSubmitting] = useState<boolean>(false)
  const [submitStatus, setSubmitStatus] = useState<SubmitStatus>(null)
  const [formData, setFormData] = useState<FormData>({
    companyName: "",
    name: "",
    email: "",
    phone: "",
    message: ""
  })
  const [isDialogOpen, setIsDialogOpen] = useState<boolean>(false)

  const handleInputChange = (e: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setFormData((prev) => ({
      ...prev,
      [e.target.name]: e.target.value
    }))
  }

  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault()
    setIsSubmitting(true)
    try {
      const response = await fetch("https://contact.lacleo.ai/", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json"
        },
        body: JSON.stringify(formData)
      })

      if (response.ok) {
        setSubmitStatus("success")
        setIsDialogOpen(false)
      } else {
        setSubmitStatus("error")
      }
    } catch (error) {
      setSubmitStatus("error")
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="flex h-full items-center bg-gray-50 p-6 dark:bg-gray-900">
      <div className="mx-auto w-full max-w-4xl">
        <Card className="h-full overflow-hidden rounded-lg border-gray-200/50 bg-white/80 p-8 shadow-xl dark:border-gray-700/50 dark:bg-gray-800/80 md:p-20">
          <div className="absolute inset-0 overflow-hidden">
            <div className="absolute -right-32 -top-32 size-64 rounded-full bg-blue-600/10 blur-3xl" />
            <div className="absolute -bottom-32 -left-32 size-64 rounded-full bg-purple-600/10 blur-3xl" />
          </div>

          <div className="relative flex flex-col items-center justify-center space-y-12">
            <motion.div
              initial={{ opacity: 0, y: -20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5 }}
              className="space-y-6 text-center"
            >
              <div className="flex flex-col items-center justify-center space-y-4">
                <div className="relative">
                  <div className="absolute -right-6 -top-6">
                    <LucideSparkles className="size-6 animate-pulse text-purple-400 dark:text-purple-300" />
                  </div>
                  <Code2 className="size-24 stroke-blue-600 dark:stroke-blue-400" />
                </div>
                <div className="inline-flex items-center rounded-full border border-gray-200 bg-white/50 px-6 py-2 dark:border-gray-700 dark:bg-gray-800/50">
                  <span className="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-sm font-medium text-transparent dark:from-blue-400 dark:to-purple-400">
                    Enterprise Feature
                  </span>
                </div>
              </div>
              <div className="space-y-3">
                <h2 className="bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-3xl font-bold text-transparent dark:from-blue-400 dark:to-purple-400">
                  API Access
                </h2>
                <p className="mx-auto max-w-md text-gray-600 dark:text-gray-300">
                  Unlock the full potential of our API with enterprise-level access. Contact our sales team to get started.
                </p>
              </div>
            </motion.div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.5, delay: 0.2 }}
              className="w-full max-w-md space-y-6"
            >
              {submitStatus === "success" ? (
                <Alert className="bg-green-50 text-green-900 dark:bg-green-900/20 dark:text-green-100">
                  <AlertDescription className="text-center">Thank you! We will get back to you in next few hours.</AlertDescription>
                </Alert>
              ) : (
                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                  <DialogTrigger asChild>
                    <Button
                      size="lg"
                      className="w-full space-x-2 bg-gradient-to-r from-blue-600 to-purple-600 text-white hover:from-blue-700 hover:to-purple-700 dark:from-blue-400 dark:to-purple-400 dark:hover:from-blue-500 dark:hover:to-purple-500"
                    >
                      <span>Request API Access</span>
                      <ArrowRight className="size-4" />
                    </Button>
                  </DialogTrigger>
                  <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                      <DialogTitle>Contact Sales Team</DialogTitle>
                      <DialogDescription>Please provide your details below to request API access.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4 py-4">
                      <div className="space-y-2">
                        <Input name="companyName" placeholder="Company Name" value={formData.companyName} onChange={handleInputChange} required />
                      </div>
                      <div className="space-y-2">
                        <Input name="name" placeholder="Your Name" value={formData.name} onChange={handleInputChange} required />
                      </div>
                      <div className="space-y-2">
                        <Input name="email" type="email" placeholder="Work Email" value={formData.email} onChange={handleInputChange} required />
                      </div>
                      <div className="space-y-2">
                        <Input name="phone" type="tel" placeholder="Phone Number" value={formData.phone} onChange={handleInputChange} required />
                      </div>
                      <div className="space-y-2">
                        <Textarea
                          name="message"
                          placeholder="Tell us about your API needs..."
                          value={formData.message}
                          onChange={handleInputChange}
                          required
                        />
                      </div>
                      {submitStatus === "error" && (
                        <Alert variant="destructive">
                          <AlertCircle className="size-4" />
                          <AlertDescription>Something went wrong. Please try again.</AlertDescription>
                        </Alert>
                      )}
                      <Button type="submit" className="w-full" disabled={isSubmitting}>
                        {isSubmitting ? (
                          <>
                            <Loader2 className="mr-2 size-4 animate-spin" />
                            Submitting...
                          </>
                        ) : (
                          "Submit Request"
                        )}
                      </Button>
                    </form>
                  </DialogContent>
                </Dialog>
              )}
            </motion.div>
          </div>
        </Card>
      </div>
    </div>
  )
}
