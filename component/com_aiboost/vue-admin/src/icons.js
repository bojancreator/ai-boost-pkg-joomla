/**
 * AI Boost — admin icon set ("Instrument" line-icons).
 *
 * Source of truth: the designer mockup (Claude Design "UI mockup collection",
 * screens.html). These are the inner SVG primitives; <AbIcon> wraps them in a
 * `<svg viewBox fill="none" stroke="currentColor">`. Single petrol-teal accent,
 * 1.4px hairline strokes — restrained, technical.
 *
 * Nav/UI icons default to a 16×16 viewBox, 1.4 stroke. Status/alert glyphs use a
 * 20×20 viewBox at 1.6 stroke (object form: { vb, sw, p }).
 *
 * Do not add emoji or coloured icons here — colour comes from `currentColor`
 * (the consuming context), keeping both themes consistent.
 */
export const AB_ICONS = {
  // ── Navigation (16×16, stroke 1.4) ──────────────────────────────
  dash:   '<rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="2" width="5" height="5" rx="1"/><rect x="2" y="9" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/>',
  heart:  '<path d="M8 14S2 10 2 6a3 3 0 0 1 6-1 3 3 0 0 1 6 1c0 4-6 8-6 8z"/>',
  bolt:   '<path d="M9 1 3 9h4l-1 6 6-8H8z"/>',
  id:     '<circle cx="8" cy="5.5" r="2.6"/><path d="M3 13c0-2.5 2.2-4 5-4s5 1.5 5 4"/>',
  key:    '<circle cx="6" cy="8" r="3"/><path d="M9 8h5M12 8v2.5"/>',
  plug:   '<rect x="2" y="2" width="5" height="5" rx="1"/><rect x="9" y="9" width="5" height="5" rx="1"/><path d="M7 4.5h3.5V8"/>',
  shield: '<path d="M8 1.6 14 4v4c0 4-6 6.4-6 6.4S2 12 2 8V4z"/>',
  cog:    '<circle cx="8" cy="8" r="2.2"/><path d="M8 1v2M8 13v2M1 8h2M13 8h2M3 3l1.4 1.4M11.6 11.6 13 13M13 3l-1.4 1.4M4.4 11.6 3 13"/>',
  tag:    '<path d="M2 2h6l6 6-6 6-6-6z"/><circle cx="5" cy="5" r="1"/>',
  schema: '<path d="M8 1.6 14 5v6L8 14.4 2 11V5z"/>',
  map:    '<path d="M2 4h12v9H2z"/><path d="M2 7h12"/>',
  share:  '<circle cx="4" cy="8" r="2"/><circle cx="12" cy="4" r="2"/><circle cx="12" cy="12" r="2"/><path d="M6 7l4-2M6 9l4 2"/>',
  chart:  '<path d="M2 14V2M2 14h12M5 11v-3M8 11V6M11 11V4"/>',
  ai:     '<circle cx="8" cy="8" r="6"/><path d="M5.5 9.5 8 4l2.5 5.5M6 8h4"/>',
  robot:  '<rect x="3" y="5" width="10" height="8" rx="1.5"/><path d="M8 5V3M5.5 9h.5M10 9h.5"/>',
  arrow:  '<path d="M3 8h10M9 4l4 4-4 4"/>',
  search: '<circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5 14 14"/>',
  link:   '<path d="M6 10a3 3 0 0 0 4 0l2-2a3 3 0 0 0-4-4M10 6a3 3 0 0 0-4 0L4 8a3 3 0 0 0 4 4"/>',
  upload: '<path d="M8 11V3M5 6l3-3 3 3M3 13h10"/>',
  code:   '<path d="M5 4 2 8l3 4M11 4l3 4-3 4"/>',
  bug:    '<circle cx="8" cy="9" r="3.5"/><path d="M8 5.5V3M4.5 9H2M14 9h-2.5M5.5 6 4 4.5M10.5 6 12 4.5"/>',
  help:   '<circle cx="8" cy="8" r="6"/><path d="M6.3 6.2a1.8 1.8 0 1 1 2.4 1.7c-.5.2-.7.5-.7 1M8 11.5v.3"/>',

  // ── Status / alert glyphs (20×20, stroke 1.6) ───────────────────
  ok:    { vb: '0 0 20 20', sw: 1.6, p: '<circle cx="10" cy="10" r="8"/><path d="M6 10.5 9 13l5-6"/>' },
  warn:  { vb: '0 0 20 20', sw: 1.6, p: '<path d="M10 3 18 17H2z"/><path d="M10 8v4M10 14.5v.5"/>' },
  err:   { vb: '0 0 20 20', sw: 1.6, p: '<circle cx="10" cy="10" r="8"/><path d="M10 6v5M10 13.5v.5"/>' },
  info:  { vb: '0 0 20 20', sw: 1.6, p: '<circle cx="10" cy="10" r="7"/><path d="M10 9v5M10 6.5v.5"/>' },
  check: { vb: '0 0 20 20', sw: 1.6, p: '<path d="M4 10.5 8 14l8-8"/>' },
}

export function iconEntry(name) {
  return AB_ICONS[name] || null
}
