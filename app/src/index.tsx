import { store } from "@/app/redux/store"
import { Toaster } from "sonner"
import { StrictMode } from "react"
import { createRoot } from "react-dom/client"
import { Provider } from "react-redux"
import { RouterProvider } from "react-router-dom"
import "./index.css"
import router from "./router"

const container = document.getElementById("root") as HTMLDivElement
const root = createRoot(container)
root.render(
  <StrictMode>
    <Provider store={store}>
      <RouterProvider router={router} />
      <Toaster position="top-right" richColors />
    </Provider>
  </StrictMode>
)
