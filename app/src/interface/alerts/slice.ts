export type TAlertType = "info" | "success" | "warning" | "error"

export interface IAlert {
  id: string
  title: string
  description: string
  type: TAlertType
  maxTimer: number
  date: string
}

export interface IAlertState {
  ids: string[]
  entities: { [key: string]: IAlert }
}
