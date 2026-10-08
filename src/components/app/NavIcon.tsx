import { NAV_PATHS, type NavIconName } from './nav-icons';

export function NavIcon({ name, size = 24, strokeWidth = 2 }: { name: NavIconName; size?: number; strokeWidth?: number }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={strokeWidth}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      className="md-icon"
    >
      <path d={NAV_PATHS[name]} />
    </svg>
  );
}
