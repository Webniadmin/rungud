import { useState } from 'react'

/** Submit-on-enter search field. Remount it with `key={value}` to follow the URL. */
export function SearchBox({ value, placeholder, onSubmit, width }: { value: string; placeholder: string; onSubmit: (v: string) => void; width?: number }) {
  const [draft, setDraft] = useState(value)
  return (
    <form
      className="field"
      style={{ margin: 0, flex: width ? undefined : 1, width, maxWidth: width ?? 420 }}
      onSubmit={(e) => {
        e.preventDefault()
        onSubmit(draft.trim())
      }}
    >
      <input type="search" value={draft} onChange={(e) => setDraft(e.target.value)} placeholder={placeholder} aria-label={placeholder} />
    </form>
  )
}
