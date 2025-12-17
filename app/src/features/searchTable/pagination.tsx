import { Button } from "@/components/ui/button"
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from "lucide-react"
import { useMemo } from "react"

interface PaginationProps {
  currentPage: number
  lastPage: number
  onPageChange: (page: number) => void
  className?: string
}

export function Pagination({ currentPage, lastPage, onPageChange, className = "" }: PaginationProps) {
  const pageNumbers = useMemo(() => {
    const pages = []
    if (lastPage <= 5) {
      for (let i = 1; i <= lastPage; i++) {
        pages.push(i)
      }
    } else {
      pages.push(1)
      if (currentPage > 3) {
        pages.push(null)
      }
      let start = Math.max(2, currentPage - 1)
      let end = Math.min(lastPage - 1, currentPage + 1)
      if (currentPage <= 3) {
        end = 4
      }
      if (currentPage >= lastPage - 2) {
        start = lastPage - 3
      }
      for (let i = start; i <= end; i++) {
        pages.push(i)
      }
      if (currentPage < lastPage - 2) {
        pages.push(null)
      }
      pages.push(lastPage)
    }
    return pages
  }, [currentPage, lastPage])

  return (
    <div className={`flex items-center justify-center gap-x-1 bg-background p-4 ${className}`}>
      <Button variant="ghost" size="icon" className="size-8" onClick={() => onPageChange(1)} disabled={currentPage === 1}>
        <ChevronsLeft className="size-4" />
      </Button>
      <Button variant="ghost" size="icon" className="size-8" onClick={() => onPageChange(currentPage - 1)} disabled={currentPage === 1}>
        <ChevronLeft className="size-4" />
      </Button>

      {pageNumbers.map((pageNum, idx) =>
        pageNum === null ? (
          <span key={`ellipsis-${idx}`} className="mr-1 text-[8px] text-muted-foreground">
            •••
          </span>
        ) : (
          <Button
            key={pageNum}
            variant={currentPage === pageNum ? "ghost" : "outline"}
            size="icon"
            className={`h-6 w-10  text-black hover:bg-white ${currentPage === pageNum ? "bg-[#F7F7F7] hover:bg-[#F7F7F7]" : ""}`}
            onClick={() => onPageChange(pageNum)}
          >
            {pageNum}
          </Button>
        )
      )}

      <Button variant="ghost" size="icon" className="size-8" onClick={() => onPageChange(currentPage + 1)} disabled={currentPage === lastPage}>
        <ChevronRight className="size-4" />
      </Button>
      <Button variant="ghost" size="icon" className="size-8" onClick={() => onPageChange(lastPage)} disabled={currentPage === lastPage}>
        <ChevronsRight className="size-4" />
      </Button>
    </div>
  )
}

export default Pagination
