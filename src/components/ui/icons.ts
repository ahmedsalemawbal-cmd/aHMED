/** Internal glyphs of the design system (24px grid, 2px stroke, currentColor). */
export const ICON_PATHS = {
  plus: 'M12 5v14M5 12h14',
  chat: 'M4 19.5l1.4-3.6A8 8 0 1 1 8.2 18.7L4 19.5z',
  phone:
    'M6.6 3.5h3l1.5 4-2 1.3a11 11 0 0 0 6.1 6.1l1.3-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z',
  check: 'M5 12.5l4.5 4.5L19 7.5',
  x: 'M6 6l12 12M18 6L6 18',
  minus: 'M6 12h12',
  clock: 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
  alert: 'M12 8v5M12 16.5v.5M10.3 3.9L2.6 17.5A2 2 0 0 0 4.3 20.5h15.4a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z',
  info: 'M12 11v6M12 7.5v.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
  pin: 'M12 21s-7-6.1-7-11.5a7 7 0 0 1 14 0C19 14.9 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z',
  store: 'M4 9.5V20h16V9.5M3 4h18l-1.5 5.5a2.7 2.7 0 0 1-5.2 0 2.7 2.7 0 0 1-5.1 0 2.7 2.7 0 0 1-5.2 0L3 4zM10 20v-5h4v5',
  flame:
    'M12 21a6.5 6.5 0 0 0 6.5-6.5c0-4.5-4-6.5-4.5-10.5-2 1.5-3.5 3.5-3.5 6-1-.5-1.8-1.5-2-2.5-1.7 1.6-3 4-3 7A6.5 6.5 0 0 0 12 21z',
  snow: 'M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9',
  sun: 'M12 16a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2',
  forward: 'M15 6l-6 6 6 6',
} as const;

export type IconName = keyof typeof ICON_PATHS;

export function isIconName(name: string): name is IconName {
  return name in ICON_PATHS;
}
