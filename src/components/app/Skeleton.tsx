/** Loading placeholder in the shape of the content (design-system.md: no full-page spinner). */
export function Skeleton({ className = '' }: { className?: string }) {
  return <div aria-hidden="true" className={`animate-pulse rounded-md bg-surface-sunken motion-reduce:animate-none ${className}`} />;
}
