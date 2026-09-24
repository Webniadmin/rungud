import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import en from './locales/en.json'
import de from './locales/de.json'

export const LANGS = ['en', 'de'] as const
export type AppLang = (typeof LANGS)[number]

const STORAGE_KEY = 'rungud.lang'

function initialLang(): AppLang {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (saved === 'de' || saved === 'en') return saved
  } catch {
    /* storage unavailable — fall through to default */
  }
  return 'en'
}

void i18n.use(initReactI18next).init({
  resources: { en: { translation: en }, de: { translation: de } },
  lng: initialLang(),
  fallbackLng: false,
  interpolation: { escapeValue: false },
  returnNull: false,
})

i18n.on('languageChanged', (lng) => {
  document.documentElement.lang = lng
  try {
    localStorage.setItem(STORAGE_KEY, lng)
  } catch {
    /* ignore */
  }
})

export default i18n
