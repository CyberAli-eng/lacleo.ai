import { useEffect } from "react"

const useDocumentTitle = (pageTitle: string, fallback = "LaCleo") => {
  useEffect(() => {
    const prevTitle = document.title
    document.title = pageTitle ? `${pageTitle} | ${fallback}` : fallback
    return () => {
      document.title = prevTitle
    }
  }, [pageTitle, fallback])
}

export default useDocumentTitle
