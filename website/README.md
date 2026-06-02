# AI Boost — Marketing Website

Source for [aiboostnow.com](https://aiboostnow.com).

## Development

```bash
pnpm install
pnpm dev
```

## Build

```bash
pnpm build
```

The `prebuild` hook runs automatically before every build and regenerates all OG images.

## OG Images

OG images (`public/og/*.png`) are **not tracked in git** — they are generated fresh on every build.

- **Full build:** `pnpm build` → runs `prebuild` → generates all OG images automatically
- **Local dev (without full build):** `pnpm generate-og`

The script writes to `public/og/` and creates the directory if it does not exist, so no manual setup is needed on a fresh clone.
