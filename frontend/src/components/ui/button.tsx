import { forwardRef, type ButtonHTMLAttributes } from "react";

/**
 * Minimal button primitive wired to the white-label theme tokens. Variants map
 * to the CSS variables the theme loader sets, so buttons re-colour per tenant
 * without a rebuild (R3.2).
 */
type Variant = "primary" | "secondary" | "ghost" | "danger" | "outline";
type Size = "sm" | "md" | "icon";

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: Size;
}

const base =
  "inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50";

const sizes: Record<Size, string> = {
  sm: "px-3 py-1.5 text-xs",
  md: "px-4 py-2 text-sm",
  icon: "h-9 w-9 p-0 text-sm",
};

const variants: Record<Variant, string> = {
  primary: "bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 active:bg-primary",
  secondary: "bg-accent text-accent-foreground hover:bg-accent/70 active:bg-accent/80",
  ghost: "text-muted-foreground hover:bg-muted hover:text-foreground",
  danger: "bg-danger text-danger-foreground shadow-sm hover:bg-danger/90 active:bg-danger",
  outline: "border border-border bg-card text-foreground shadow-sm hover:bg-muted hover:border-muted-foreground/30",
};

/**
 * Compose the button class string for a given variant/size. Exported so other
 * interactive elements that must render as a different tag (e.g. a Next.js
 * `<Link>`) can share the exact same styling without a `<button>` inside an
 * `<a>`.
 */
export function buttonClasses(variant: Variant = "primary", size: Size = "md", className = ""): string {
  return `${base} ${sizes[size]} ${variants[variant]} ${className}`.trim();
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = "primary", size = "md", className = "", ...props },
  ref,
): JSX.Element {
  return <button ref={ref} className={buttonClasses(variant, size, className)} {...props} />;
});
