# Block content

WordPress users author posts and pages with blocks. Those blocks must keep readable typography, lists, quotes, images, buttons, and alignment.

- Do not dequeue core block styles to “win” against Preflight.
- Do not put margin utilities on `figure`. Gutenberg’s unlayered `:where(figure)` rule sets `margin: 0 0 1em` and wins over them. Space from a parent. Any core block rule wins over Tailwind the same way; see `visual.md`.
- Preview a block-authored page after visual changes, not only archive cards or custom VX templates.
- Do not register the full Preflight/frontend bundle with `add_editor_style()` by default. Frontend base and chrome rules can interfere with the Gutenberg canvas, and `.entry-content` selectors do not automatically target editor content.
- When editor/front-end parity is needed, build a dedicated editor stylesheet without Preflight or site chrome, scope its content rules to `.editor-styles-wrapper`, register that output deliberately, and verify a real post in the editor and on the frontend.
