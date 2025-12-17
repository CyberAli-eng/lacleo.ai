import { createBrowserRouter, createRoutesFromElements, Navigate, Route } from "react-router-dom"
import AppLayout from "./components/layout/appLayout"
import SearchLayout from "./components/layout/searchLayout"
import RequireAuth from "./components/middleware/requireAuth"
import PageNotFound from "./components/ui/PageNotFound"
import Lists from "./features/lists"
import CompaniesTable from "./features/searchTable/companiesTable"
import { ContactsTable } from "./features/searchTable/contactsTable"
import APIAccess from "./features/userApi"
import AISearchPage from "./features/aisearch/AISearchPage"

const router = createBrowserRouter(
  createRoutesFromElements(
    <Route path="/" element={<AppLayout />}>
      <Route index element={<Navigate to="/app/search/contacts" />} />
      <Route path="/app" element={<RequireAuth />}>
        <Route index element={<Navigate to="search/contacts" />} />
        <Route path="search" element={<SearchLayout />}>
          <Route index element={<Navigate to="contacts" />} />
          <Route path="contacts" element={<ContactsTable />} />
          <Route path="companies" element={<CompaniesTable />} />
          <Route path="*" element={<Navigate to="contacts" replace />} />
        </Route>
        <Route path="lists" element={<Lists />} />
        <Route path="api" element={<APIAccess />} />
      </Route>
      <Route path="*" element={<PageNotFound />} />
    </Route>
  )
)

export default router
