/* eslint-disable @typescript-eslint/no-unused-vars */
import { transformErrorResponse, USER, ACCOUNT_HOST } from "@/app/constants/apiConstants"
import { accountBaseQuery, apiSlice } from "@/app/redux/apiSlice"
import { IUser } from "@/interface/settings/slice"
import { FetchBaseQueryError, BaseQueryApi, BaseQueryFn, FetchArgs, FetchBaseQueryMeta } from "@reduxjs/toolkit/query"

export const authSlice = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    getUser: builder.query<IUser, void>({
      query: () => ({ url: `${ACCOUNT_HOST}${USER.GET_USER}` }),
      keepUnusedDataFor: 0
    }),
    logout: builder.mutation<string, void>({
      queryFn: async (
        _arg: void,
        queryApi: BaseQueryApi,
        _extraOptions: Record<string, never>,
        _baseQuery: BaseQueryFn<string | FetchArgs, unknown, FetchBaseQueryError, Record<string, never>, FetchBaseQueryMeta>
      ) => {
        try {
          const result = await accountBaseQuery({ url: USER.LOGOUT_USER, method: "POST" }, queryApi, {})
          if (result.error) {
            return { error: result.error }
          }

          return { data: "success" }
        } catch (error) {
          return {
            error: {
              status: 500,
              data: error instanceof Error ? error.message : "Unknown error"
            } as FetchBaseQueryError
          }
        }
      }
    })
  })
})

export const { useLazyGetUserQuery, useLogoutMutation } = authSlice
