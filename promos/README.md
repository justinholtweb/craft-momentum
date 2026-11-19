# Plugin Store promo images

Marketing images for the Momentum listing on the Craft Plugin Store, in the same theme as
[justinholt.com/plugins/craft-momentum](https://justinholt.com/plugins/craft-momentum).

```bash
./build.sh          # all slides
./build.sh "2 5"    # just slides 2 and 5
```

Output: `out/momentum-promo-N.jpg`, 1920×1080 (rendered at 2× in headless Chrome, downsampled).
**JPEG, never PNG.** `fonts.css` is generated and gitignored.

The cover and the closing slide carry the Craft 6 beta caution, and every footer says
"Craft CMS 6 (beta)" — keep it there until Craft 6 and Momentum leave beta.

The watermark is the arrow's **outline** (`assets/watermark.svg`, the stroked `resources/icon-mask.svg`
paths), not the filled mark.

| # | Slide |
| --- | --- |
| 1 | Cover — Lite free, Pro price TBD, Craft 6 beta caution |
| 2 | A section setting, a component |
| 3 | Every field type, as props |
| 4 | No N+1 — eager vs lazy query counts from the test suite |
| 5 | Preview, multi-site and GraphQL keep working |
| 6 | Pro — TypeScript, SSR, deferred props |
| 7 | Closing — summary, beta caution |
