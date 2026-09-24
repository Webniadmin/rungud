import { ICONS, type IconName } from './icons'

export function Icon({ name, small }: { name: IconName; small?: boolean }) {
  return (
    <svg
      className={small ? 'ico ico-sm' : 'ico'}
      viewBox="0 0 24 24"
      aria-hidden="true"
      dangerouslySetInnerHTML={{ __html: ICONS[name] }}
    />
  )
}
