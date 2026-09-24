import { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { parseDmy } from '@/lib/date'

/** DD.MM.YYYY text input; see lib/date.ts. */
export function DateField({ id, value, onChange }: { id: string; value: string; onChange: (ymd: string) => void }) {
  const { t } = useTranslation()
  const initial = value ? value.split('-').reverse().join('.') : ''
  const [text, setText] = useState(initial)
  const invalid = text.trim() !== '' && parseDmy(text) === ''
  return (
    <>
      <input
        id={id}
        inputMode="numeric"
        placeholder={t('common.datePlaceholder')}
        value={text}
        aria-invalid={invalid}
        onChange={(e) => {
          setText(e.target.value)
          onChange(parseDmy(e.target.value))
        }}
      />
      {invalid ? <div className="hint" style={{ color: 'var(--bad-fg)' }}>{t('common.dateHint')}</div> : null}
    </>
  )
}
