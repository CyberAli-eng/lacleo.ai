import { Button } from "@/components/ui/button"
import FileCopy from "@/static/media/icons/file-copy.svg?react"
import StarFill from "@/static/media/icons/star-fill.svg?react"
import { CalendarRange, Check, Ellipsis, Play } from "lucide-react"

type SavedFilter = {
  id: string
  title: string
  description: string
  tags: string[]
  createdOn: string
  resultsCount: number
  starred?: boolean
}

const mockSavedFilters: SavedFilter[] = [
  {
    id: "sf_1",
    title: "SaaS Marketing VPs",
    description: "VP-level marketing professionals in SaaS companies, 51-200 employees",
    tags: ["Vice President of Marketing", "VP of Growth & Demand Gen", "San Francisco", ">5 YOE", "SaaS", "51-200 employees"],
    createdOn: "2024-01-05",
    resultsCount: 247,
    starred: true
  },
  {
    id: "sf_2",
    title: "EMEA Sales Leaders",
    description: "Directors and VPs of Sales in EMEA, mid-market orgs",
    tags: ["VP of Sales", "Sales Director", "London", "Munich", "3-7 YOE", "Mid-market"],
    createdOn: "2024-03-12",
    resultsCount: 582,
    starred: false
  },
  {
    id: "sf_3",
    title: "US Data Engineers",
    description: "Senior data engineers in product-led companies",
    tags: ["Senior", "Data Engineering", "Remote US", "PLG", "8+ YOE", "Python", "Spark"],
    createdOn: "2023-11-22",
    resultsCount: 134,
    starred: false
  },
  {
    id: "sf_4",
    title: "US Data Engineers",
    description: "Senior data engineers in product-led companies",
    tags: ["Senior", "Data Engineering", "Remote US", "PLG", "8+ YOE", "Python", "Spark"],
    createdOn: "2023-11-22",
    resultsCount: 134,
    starred: false
  },

  {
    id: "sf_5",
    title: "US Data Engineers",
    description: "Senior data engineers in product-led companies",
    tags: ["Senior", "Data Engineering", "Remote US", "PLG", "8+ YOE", "Python", "Spark"],
    createdOn: "2023-11-22",
    resultsCount: 134,
    starred: false
  }
]

const SavedFiltersPage = () => {
  return (
    <div className="flex flex-col gap-3">
      {mockSavedFilters.map((filter) => {
        const visibleTags = filter.tags.slice(0, 3)
        const remainingTagCount = Math.max(filter.tags.length - visibleTags.length, 0)

        return (
          <div key={filter.id} className="flex w-full flex-1 justify-between rounded-[12px] border p-4">
            <div className="flex flex-col gap-3.5">
              <div className="flex flex-col  gap-[5px]">
                <span className="flex items-center gap-1 text-sm font-medium text-gray-950">
                  {filter.title}
                  {filter.starred ? <StarFill className="size-[18px]" /> : null}
                </span>
                <span className="text-xs font-normal text-gray-600">{filter.description}</span>
              </div>

              <div className=" flex flex-wrap gap-2">
                {visibleTags.map((tag, idx) => (
                  <span key={`${filter.id}_tag_${idx}`} className="rounded-full bg-blue-200 px-2 py-0.5 text-xs font-medium text-[#122368]">
                    {tag}
                  </span>
                ))}
                {remainingTagCount > 0 ? (
                  <span className="rounded-full bg-blue-200 px-2 py-0.5 text-xs font-medium text-[#122368]">+{remainingTagCount}</span>
                ) : null}
              </div>

              <div className="flex items-center gap-3.5">
                <span className="flex items-center gap-1 text-xs font-normal text-gray-600">
                  <CalendarRange className="size-3.5" /> Created on {filter.createdOn}
                </span>
                <span className="flex items-center gap-1 text-xs font-normal text-gray-600">
                  <Check className="size-3.5" /> {filter.resultsCount} Results
                </span>
              </div>
            </div>
            <div className=" flex items-end gap-1.5">
              <Button variant="outline" className="rounded-[10px] p-[10px]">
                <Ellipsis className="size-5" />
              </Button>

              <Button variant="outline" className="flex items-center gap-2 rounded-[10px] p-[10px] text-sm font-medium text-gray-600">
                <FileCopy className="size-5" />
                Duplicate
              </Button>

              <Button
                variant="outline"
                className="flex items-center gap-2 rounded-[10px] bg-blue-600 p-[10px] text-sm font-medium text-white hover:bg-blue-600 hover:text-white"
              >
                <Play className="size-5" />
                Run Search
              </Button>
            </div>
          </div>
        )
      })}
    </div>
  )
}

export default SavedFiltersPage
