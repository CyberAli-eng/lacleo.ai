import react from "@vitejs/plugin-react-swc"
import { defineConfig, loadEnv } from "vite"
import mkcert from "vite-plugin-mkcert"
import svgr from "vite-plugin-svgr"
import tsconfigPaths from "vite-tsconfig-paths"

// https://vitejs.dev/config
export default defineConfig(({ mode }) => {
  // Load env file based on `mode` in the current working directory.
  // Set the third parameter to '' to load all env regardless of the `VITE_` prefix.
  const env = loadEnv(mode, process.cwd(), "")

  return {
    plugins: [svgr(), react(), tsconfigPaths(), ...(mode !== "production" ? [mkcert({ savePath: "./certs" })] : [])],
    define: {
      __DEV__: mode === "development",
      __PROD__: mode === "production",
      __STAGE__: mode === "staging"
    },

    server: {
      port: parseInt(env.PORT || "3000"),
      host: "app.lacleo.test",
      strictPort: true,
      proxy: {
        "/account": {
          target: env.VITE_ACCOUNT_HOST || "https://local-accounts.lacleo.test",
          changeOrigin: true,
          secure: false
        }
      }
    },

    build: {
      outDir: "dist",
      sourcemap: mode !== "production",
      ...(mode === "production" && {
        minify: "terser",
        terserOptions: {
          compress: {
            drop_console: true
          }
        }
      })
    }
  }
})
