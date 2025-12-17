type EmployeesDetailsProps = { headcount?: number | null }

const EmployeesDetails = ({ headcount }: EmployeesDetailsProps) => {
  return (
    <div className=" w-full">
      <div className="rounded-xl border border-gray-200 bg-white p-4">
        <div className="grid h-12 grid-cols-1">
          <div className="text-center">
            <div className="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">Headcount</div>
            <div className="text-base font-medium text-gray-950">{headcount ?? "N/A"}</div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default EmployeesDetails
