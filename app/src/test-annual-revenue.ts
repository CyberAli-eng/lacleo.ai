import { buildSearchQuery } from "./app/utils/buildSearchQuery"

// Simulate selected items for annual revenue
const selectedItems = {
  annual_revenue: [
    { id: "0-1M", name: "0-1M", type: "include" as const, value: "" }
  ]
}

const result = buildSearchQuery(selectedItems)
console.log("Annual Revenue Query:")
console.log(JSON.stringify(result, null, 2))
