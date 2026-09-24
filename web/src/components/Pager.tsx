import { useTranslation } from 'react-i18next'

export function Pager({ page, pages, onPage }: { page: number; pages: number; onPage: (n: number) => void }) {
  const { t } = useTranslation()
  if (pages <= 1) return null
  return (
    <div className="pager">
      <button type="button" className="btn sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>{t('common.back')}</button>
      <span className="num">{t('common.pageOf', { page, pages })}</span>
      <button type="button" className="btn sm" disabled={page >= pages} onClick={() => onPage(page + 1)}>{t('common.next')}</button>
    </div>
  )
}
