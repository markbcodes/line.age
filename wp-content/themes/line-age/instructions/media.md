# Images and media

Apply these rules while seeding or adding theme-owned assets, not before the first visible templates exist.

- Source imagery when the brief asks for it, when the user supplies files, or when the requested look is photo-first (news, magazine, portfolio, and similar). Do not add a photo program the brief did not request. Use the smallest set that covers the MVP: at most 10 photos, reused across records, unless the user asks for more.
- Do not generate covers or other artwork with Pillow or ImageMagick unless the user asks for original artwork. Do not generate flat-color placeholders to satisfy the seeded-rendering check.
- When you download external files, record provenance (source page, direct URL or local path, creator, license, subject, alt text) in a small development-only manifest such as `tools/media-manifest.json`. Skip the manifest when the user supplied the files, the theme needs no content imagery, or only theme-owned decorative assets are used.
- Download once at about 1600px wide. Do not re-download to chase `srcset` or extra resolution.
- Open one representative downloaded file to confirm it is a real image of the recorded subject. Do not inspect every file; never guess stock-photo IDs from a URL.
- Verify and follow the source’s current license and attribution requirements. Preserve the source page and creator credit when attribution is required; never claim third-party work as original.
- When redistributing fonts or other licensed assets, keep the required notices in `licenses/` so the production package includes them.
- Never hotlink production images from stock-library URLs. Download content imagery into the WordPress Media Library and reference attachment IDs so WordPress owns delivery, thumbnails, `srcset`, and sizing.
- Download each external source once and cache it under a development-only path such as `tools/seed-media/` when practical. Do not repeatedly download unchanged media on every seed run.
- To change one picture on a site that is already built, such as a post's featured image, an image or gallery field, or a settings page's logo, use Shader's `replace_image` tool with the file. Do not edit the seed for it: the seed never overwrites an image that is set. An image the user attaches in the chat is saved on disk, and the message says where. A theme-owned image under `resources/` is replaced by copying the file over it.
- Import and validate a replacement attachment before deleting the old one. A failed download or import must leave the previous valid attachment intact, print a clear warning/error, and fail nonzero when required content is missing.
- Theme-owned decorative assets may live under `resources/` and must be imported by the build so deployable output lands in `assets/build/`.
- Probe network access once before treating it as unavailable. Do not fabricate remote image URLs when a probe shows it is unavailable.
- Supply alt text for informative images and empty alt text for purely decorative images.
- Keep content images out of `vx-config/`: config defines media fields, while attachment IDs and selected images remain database content.

## Finding photos

- Find photos yourself, in the conversation, not in a background agent.
- Use only sources that work without an API key. If a source asks for a key, move on to another; do not probe it again.
- Take the first suitable result for each photo: judge candidates by their titles, sizes and licences, and open at most a couple of previews. Do not download batches of previews or build contact sheets to compare them.
- Make at most two searches per photo. If neither finds something suitable, reuse a photo you already have.
- Save each photo and its manifest entry as soon as you choose it, so a search that stops part-way still leaves usable files.
- Give every download a timeout, such as `-TimeoutSec 20` in PowerShell or `--max-time 20` with curl. Keep each command to a few downloads, so none runs longer than about two minutes.
- If some photos are still missing after that, seed with the ones you have, reused across records, tell the user, and offer to use photos they drop into the chat.
