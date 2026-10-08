/** Activity glyphs from design/screens/NewLead1.dc.html, keyed by activity_types.icon. */
export const ACTIVITY_PATHS: Record<string, string> = {
  restaurant: 'M7 3v8a2 2 0 0 0 2 2v8M11 3v8M5 3v8M17 3c-1.7 0-3 2.2-3 5s1.3 4 3 4v9',
  cafe: 'M4 9h13v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V9zM17 10h1.5a2.5 2.5 0 0 1 0 5H17M8 3v3M12 3v3',
  dental:
    'M8 3c-2.5 0-4 2-4 4.5 0 3 1.5 4 2 7 .5 3 1 6.5 2.5 6.5s1.5-5 3.5-5 2 5 3.5 5 2-3.5 2.5-6.5c.5-3 2-4 2-7C20 5 18.5 3 16 3c-1.6 0-2.6 1-4 1S9.6 3 8 3z',
  store: 'M4 9.5V20h16V9.5M3 4h18l-1.5 5.5a2.7 2.7 0 0 1-5.2 0 2.7 2.7 0 0 1-5.1 0 2.7 2.7 0 0 1-5.2 0L3 4z',
};

export function activityPath(icon: string): string {
  return ACTIVITY_PATHS[icon] ?? ACTIVITY_PATHS.store ?? '';
}
