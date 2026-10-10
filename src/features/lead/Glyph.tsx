import { ICON_PATHS } from '@/components/ui/icons';

/** Line icons of the lead page: the design-system set plus a few page glyphs (24px grid, 2px stroke). */
const PATHS = {
  ...ICON_PATHS,
  back: 'M9 6l6 6-6 6',
  more: 'M12 5h.01M12 12h.01M12 19h.01',
  edit: 'M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4',
  stage: 'M4 6h16M4 12h10M4 18h6',
  lost: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM5.6 5.6l12.8 12.8',
  quote: 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6',
} as const;

export type GlyphName = keyof typeof PATHS;

export function Glyph({ name, size = 20, className, strokeWidth = 2 }: { name: GlyphName; size?: number; className?: string; strokeWidth?: number }) {
  return (
    <svg
      className={['md-icon', className].filter(Boolean).join(' ')}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d={PATHS[name]} />
    </svg>
  );
}
