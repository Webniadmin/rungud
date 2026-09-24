import { useTranslation } from 'react-i18next'

/**
 * Phase 0 placeholder. The shell (rail, top bar, 21 screens) is rebuilt from
 * docs/prototype.html in phase 2.
 */
export default function App() {
  const { t, i18n } = useTranslation()
  return (
    <div className="flex min-h-screen">
      <aside className="w-rail shrink-0 bg-slate text-rail-text">
        <div className="px-[22px] pt-[26px] pb-[22px] text-[19px] font-medium tracking-[.06em] text-white">
          inZENtive
          <small className="mt-[5px] block text-xs font-normal tracking-[.02em] text-[#8DA0AE]">
            {t('nav.backoffice')}
          </small>
        </div>
      </aside>
      <main className="flex-1 px-[34px] py-[30px]">
        <h1 className="m-0 text-[27px] font-medium tracking-[-.01em]">{t('common.today')}</h1>
        <p className="mt-1 text-[14.5px] text-ink-2">{t('common.allclear')}</p>
        <div className="mt-6 flex overflow-hidden rounded-[3px] border border-line-strong w-fit">
          {(['en', 'de'] as const).map((l) => (
            <button
              key={l}
              type="button"
              onClick={() => void i18n.changeLanguage(l)}
              className={
                i18n.language === l
                  ? 'bg-slate px-[13px] py-[7px] text-sm text-white'
                  : 'bg-white px-[13px] py-[7px] text-sm text-ink-2'
              }
            >
              {l.toUpperCase()}
            </button>
          ))}
        </div>
      </main>
    </div>
  )
}
