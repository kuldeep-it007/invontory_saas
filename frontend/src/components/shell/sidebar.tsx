"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useMemo, useState } from "react";

import { BrandLogo } from "@/components/branding/brand-logo";
import { useAuth } from "@/components/providers/auth-provider";
import { useEffectiveAuth } from "@/components/providers/impersonation-provider";
import { Icon } from "@/components/ui/icons";

import { NAV_SECTIONS, navLabel, type NavLink, type NavSection } from "./nav-items";

/**
 * App sidebar (DreamsPOS-style, hierarchical). Renders the brand logo, ability-
 * gated nav grouped into sections, and a signed-in identity card with sign-out.
 *
 * Flat sections render their links directly under a muted header. Collapsible
 * sections (Distributor & Inventory Management, Order Management) render an
 * expand/collapse parent with an icon, a rotating chevron, and an active-state
 * highlight that propagates up from the active child. Only links the current
 * (possibly impersonated) user may access are shown; a section with no visible
 * links is hidden entirely.
 */
export function Sidebar(): JSX.Element {
  const pathname = usePathname();
  const { identity, logout } = useAuth();
  const { can, actorKind } = useEffectiveAuth();

  const isActive = (href: string) => pathname === href || pathname.startsWith(`${href}/`);

  // Visible sections = sections with at least one link the actor may see:
  // the link's ability must be held AND (if the link is kind-scoped) the
  // current actor kind must be in its allow-list.
  const sections = useMemo(
    () =>
      NAV_SECTIONS.map((section) => ({
        section,
        links: section.children.filter(
          (l) =>
            (!l.ability || can(l.ability)) &&
            (!l.actorKinds || l.actorKinds.includes(actorKind)),
        ),
      })).filter((s) => s.links.length > 0),
    [can, actorKind],
  );

  return (
    <aside className="flex h-screen w-64 shrink-0 flex-col border-r border-border bg-card">
      <div className="flex h-16 items-center border-b border-border px-5">
        <BrandLogo className="h-8 w-auto" />
      </div>

      <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-5" aria-label="Primary">
        {sections.map(({ section, links }) =>
          section.collapsible ? (
            <CollapsibleSection
              key={section.title}
              section={section}
              links={links}
              isActive={isActive}
            />
          ) : (
            <FlatSection key={section.title} section={section} links={links} isActive={isActive} />
          ),
        )}
      </nav>

      <div className="border-t border-border p-3">
        {identity ? (
          <div className="mb-2 flex items-center gap-3 rounded-lg px-2 py-2">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
              {monogram(identity.name)}
            </span>
            <div className="min-w-0">
              <p className="truncate text-sm font-medium text-foreground">{identity.name}</p>
              <p className="truncate text-xs text-muted-foreground">
                {identity.roles[0] ?? identity.email}
              </p>
            </div>
          </div>
        ) : null}
        <button
          onClick={() => void logout()}
          className="flex w-full items-center justify-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
          <Icon.logout size={16} />
          Sign out
        </button>
      </div>
    </aside>
  );
}

/** A plain titled group of links (no expand/collapse). */
function FlatSection({
  section,
  links,
  isActive,
}: {
  section: NavSection;
  links: NavLink[];
  isActive: (href: string) => boolean;
}): JSX.Element {
  return (
    <div className="space-y-1">
      <p className="px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground/70">
        {section.title}
      </p>
      {links.map((link) => (
        <NavLinkRow key={link.href} link={link} active={isActive(link.href)} />
      ))}
    </div>
  );
}

/** An expand/collapse parent whose children are separate pages. */
function CollapsibleSection({
  section,
  links,
  isActive,
}: {
  section: NavSection;
  links: NavLink[];
  isActive: (href: string) => boolean;
}): JSX.Element {
  const hasActiveChild = links.some((l) => isActive(l.href));
  const [open, setOpen] = useState<boolean>(section.defaultOpen ?? false);

  // Keep the section open whenever one of its children is the active route so
  // the current page is always reachable after navigation.
  useEffect(() => {
    if (hasActiveChild) setOpen(true);
  }, [hasActiveChild]);

  const ParentIcon = section.icon ? Icon[section.icon] : null;
  const panelId = `nav-${slug(section.title)}`;

  return (
    <div className="space-y-1">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-controls={panelId}
        className={`group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold transition-colors ${
          hasActiveChild
            ? "bg-primary/10 text-primary"
            : "text-foreground hover:bg-muted"
        }`}
      >
        {ParentIcon ? (
          <ParentIcon
            size={18}
            className={hasActiveChild ? "text-primary" : "text-muted-foreground group-hover:text-foreground"}
          />
        ) : null}
        <span className="flex-1 truncate text-left">{section.title}</span>
        <Icon.chevronRight
          size={16}
          className={`shrink-0 text-muted-foreground transition-transform ${open ? "rotate-90" : ""}`}
        />
      </button>

      {open ? (
        <div id={panelId} className="ml-3 space-y-1 border-l border-border pl-2">
          {links.map((link) => (
            <NavLinkRow key={link.href} link={link} active={isActive(link.href)} nested />
          ))}
        </div>
      ) : null}
    </div>
  );
}

/** A single nav link row with icon + active pill. */
function NavLinkRow({
  link,
  active,
  nested = false,
}: {
  link: NavLink;
  active: boolean;
  nested?: boolean;
}): JSX.Element {
  const IconComp = Icon[link.icon];
  const { actorKind } = useEffectiveAuth();
  return (
    <Link
      href={link.href}
      aria-current={active ? "page" : undefined}
      className={`group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
        active
          ? "bg-primary text-primary-foreground shadow-sm"
          : "text-muted-foreground hover:bg-muted hover:text-foreground"
      } ${nested ? "text-[13px]" : ""}`}
    >
      <IconComp
        size={nested ? 16 : 18}
        className={active ? "text-primary-foreground" : "text-muted-foreground group-hover:text-foreground"}
      />
      <span className="truncate">{navLabel(link, actorKind)}</span>
    </Link>
  );
}

function monogram(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (!parts.length) return "?";
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function slug(s: string): string {
  return s.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/(^-|-$)/g, "");
}
