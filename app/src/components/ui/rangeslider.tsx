import React, { useState, useCallback, useRef, useEffect } from "react"
import { Minus } from "lucide-react"

type RangeSliderProps = {
  min?: number
  max?: number
  step?: number
  value?: [number, number]
  onChange?: (range: [number, number]) => void
  unit?: string
  label?: string
  hideHeader?: boolean
}

// Reusable RangeSlider Component
export const RangeSlider = ({
  min = 0,
  max = 30,
  step = 1,
  value = [0, 5],
  onChange,
  unit = "",
  label = "",
  hideHeader = false
}: RangeSliderProps) => {
  const [range, setRange] = useState<[number, number]>(value)
  const [isDragging, setIsDragging] = useState<0 | 1 | null>(null)
  const sliderRef = useRef<HTMLDivElement | null>(null)

  useEffect(() => {
    setRange(value)
  }, [value])

  const getPercentage = useCallback(
    (val: number) => {
      return ((val - min) / (max - min)) * 100
    },
    [min, max]
  )

  const getValue = useCallback(
    (percentage: number) => {
      const computed = (percentage / 100) * (max - min) + min
      return Math.round(computed / step) * step
    },
    [min, max, step]
  )

  const handleMouseDown = (index: 0 | 1) => (e: React.MouseEvent<HTMLDivElement>) => {
    setIsDragging(index)
    e.preventDefault()
  }

  const handleMouseMove = useCallback(
    (e: MouseEvent) => {
      if (isDragging === null || !sliderRef.current) return

      const rect = sliderRef.current.getBoundingClientRect()
      const percentage = Math.min(100, Math.max(0, ((e.clientX - rect.left) / rect.width) * 100))
      const newValue = getValue(percentage)

      const newRange: [number, number] = [...range] as [number, number]
      newRange[isDragging] = newValue

      // Ensure min doesn't exceed max
      if (isDragging === 0 && newValue > range[1]) {
        newRange[0] = range[1]
      } else if (isDragging === 1 && newValue < range[0]) {
        newRange[1] = range[0]
      }

      setRange(newRange)
      onChange?.(newRange)
    },
    [isDragging, range, getValue, onChange]
  )

  const handleMouseUp = useCallback(() => {
    setIsDragging(null)
  }, [])

  useEffect(() => {
    if (isDragging !== null) {
      document.addEventListener("mousemove", handleMouseMove)
      document.addEventListener("mouseup", handleMouseUp)
      return () => {
        document.removeEventListener("mousemove", handleMouseMove)
        document.removeEventListener("mouseup", handleMouseUp)
      }
    }
  }, [isDragging, handleMouseMove, handleMouseUp])

  const leftPercentage = getPercentage(Math.min(...range))
  const rightPercentage = getPercentage(Math.max(...range))

  return (
    <div className="w-full space-y-4">
      {!hideHeader && (
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium text-gray-950 dark:text-gray-200">{label}</span>
          <button className="text-gray-400 dark:text-gray-500">
            <Minus className="size-4" />
          </button>
        </div>
      )}

      <div className="space-y-3">
        <div className="flex items-center gap-1">
          <span className="rounded-lg border bg-white px-1.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-400">
            {Math.min(...range)} {unit}
          </span>
          <span className="text-xs text-gray-400 dark:text-gray-500">to</span>
          <span className="rounded-lg border bg-white px-1.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-400">
            {Math.max(...range)} {unit}
          </span>
        </div>

        <div className="relative px-2">
          <div ref={sliderRef} className="relative h-2 w-full rounded-full bg-gray-200 dark:bg-gray-700">
            {/* Active track */}
            <div
              className="absolute h-full rounded-full bg-[#335CFF]"
              style={{
                left: `${leftPercentage}%`,
                width: `${rightPercentage - leftPercentage}%`
              }}
            />

            {/* Left handle */}
            <div
              className={`absolute top-1/2 size-5 -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-full border-2 border-white bg-[#335CFF] shadow-lg transition-transform hover:scale-110 ${
                isDragging === 0 ? "scale-125" : ""
              }`}
              style={{ left: `${getPercentage(range[0])}%` }}
              onMouseDown={handleMouseDown(0)}
            />

            {/* Right handle */}
            <div
              className={`absolute top-1/2 size-5 -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-full border-2 border-white bg-[#335CFF] shadow-lg transition-transform hover:scale-110 ${
                isDragging === 1 ? "scale-125" : ""
              }`}
              style={{ left: `${getPercentage(range[1])}%` }}
              onMouseDown={handleMouseDown(1)}
            />
          </div>
        </div>
      </div>
    </div>
  )
}
