import '../css/app.css'

const toggle = document.querySelector('[data-nav-toggle]')
const nav = document.querySelector('[data-nav-panel]')

if (toggle instanceof HTMLElement && nav instanceof HTMLElement) {
  const setOpen = (open) => {
    nav.dataset.open = open ? 'true' : 'false'
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false')
  }

  toggle.addEventListener('click', () => {
    setOpen(nav.dataset.open !== 'true')
  })

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && nav.dataset.open === 'true') {
      setOpen(false)
      toggle.focus()
    }
  })
}
