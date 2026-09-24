// SVG path data from docs/prototype.html (const P). Rendered by <Icon>.
export const ICONS = {
  "today": "<circle cx=\"12\" cy=\"12\" r=\"4\"/><path d=\"M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4\"/>",
  "events": "<rect x=\"3\" y=\"5\" width=\"18\" height=\"16\" rx=\"2\"/><path d=\"M3 10h18M8 3v4M16 3v4\"/>",
  "people": "<circle cx=\"9\" cy=\"8\" r=\"3.2\"/><path d=\"M3 20a6 6 0 0 1 12 0\"/><path d=\"M16 5.5a3 3 0 0 1 0 5.6M17 20a6 6 0 0 0-2-4.5\"/>",
  "members": "<rect x=\"2.5\" y=\"5\" width=\"19\" height=\"14\" rx=\"2\"/><path d=\"M2.5 10h19M6 15h4\"/>",
  "invoices": "<path d=\"M5 3h9l5 5v13H5z\"/><path d=\"M14 3v5h5M8 13h8M8 17h5\"/>",
  "payments": "<circle cx=\"12\" cy=\"12\" r=\"8\"/><path d=\"M12 7.5v9M14.4 9.6c-.5-.7-1.4-1.1-2.4-1.1-1.4 0-2.4.7-2.4 1.8 0 2.4 4.8 1.2 4.8 3.6 0 1.1-1 1.8-2.4 1.8-1.1 0-2-.4-2.4-1.2\"/>",
  "insights": "<path d=\"M9.5 18h5M10 21h4\"/><path d=\"M12 3a6 6 0 0 0-3.5 10.9c.5.4.8 1 .9 1.6h5.2c.1-.6.4-1.2.9-1.6A6 6 0 0 0 12 3z\"/>",
  "video": "<rect x=\"2.5\" y=\"5\" width=\"13\" height=\"14\" rx=\"2\"/><path d=\"M15.5 10.5l6-3.5v10l-6-3.5z\"/>",
  "messages": "<rect x=\"2.5\" y=\"5\" width=\"19\" height=\"14\" rx=\"2\"/><path d=\"m3 7 9 6 9-6\"/>",
  "certs": "<circle cx=\"12\" cy=\"9\" r=\"5.5\"/><path d=\"m8.5 14-1.5 7 5-2.5 5 2.5-1.5-7\"/>",
  "codes": "<path d=\"M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z\"/><circle cx=\"7.5\" cy=\"7.5\" r=\"1.5\"/>",
  "sync": "<path d=\"M4 12a8 8 0 0 1 13.7-5.7L20 8.5M20 4v4.5h-4.5\"/><path d=\"M20 12a8 8 0 0 1-13.7 5.7L4 15.5M4 20v-4.5h4.5\"/>",
  "help": "<circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M9.6 9.3a2.5 2.5 0 0 1 4.9.7c0 1.7-2.5 2-2.5 3.5\"/><path d=\"M12 17h.01\"/>",
  "search": "<circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"m20 20-3.5-3.5\"/>",
} as const

export type IconName = keyof typeof ICONS
