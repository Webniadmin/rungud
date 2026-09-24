import { useTranslation } from 'react-i18next'
import { PageHead } from '@/components/ui'

/** A screen from the prototype that a later build phase fills. */
export default function Later({ titleKey, phase }: { titleKey: string; phase: number }) {
  const { t } = useTranslation()
  return (
    <div className="page">
      <PageHead title={t(titleKey)} />
      <div className="note info" style={{ marginTop: 20 }}>{t('later.text', { phase })}</div>
    </div>
  )
}
