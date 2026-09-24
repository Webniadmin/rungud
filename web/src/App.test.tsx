import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it } from 'vitest'
import '@/i18n'
import App from './App'

describe('App shell', () => {
  it('renders English by default and switches to German', async () => {
    render(<App />)
    expect(screen.getByRole('heading', { name: 'Today' })).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'DE' }))
    expect(screen.getByRole('heading', { name: 'Heute' })).toBeInTheDocument()
    expect(document.documentElement.lang).toBe('de')
  })
})
