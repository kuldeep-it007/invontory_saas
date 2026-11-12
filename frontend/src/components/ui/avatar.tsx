/**
 * A user's profile picture, falling back to their initials. Used for the
 * profile icon in the top bar and the sidebar identity card.
 */
export function Avatar({
  name,
  src,
  size = 36,
  className = "",
}: {
  name: string;
  src?: string | null;
  size?: number;
  className?: string;
}): JSX.Element {
  const style = { width: size, height: size };

  if (src) {
    return (
      // A data URL from our own API; plain <img> avoids next/image config.
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={src}
        alt={name}
        style={style}
        className={`shrink-0 rounded-full object-cover ring-1 ring-border ${className}`}
      />
    );
  }

  return (
    <span
      style={{ ...style, fontSize: Math.max(11, Math.round(size * 0.38)) }}
      className={`flex shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary ${className}`}
      aria-hidden="true"
    >
      {monogram(name)}
    </span>
  );
}

export function monogram(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}
