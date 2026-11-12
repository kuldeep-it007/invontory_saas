"use client";

import { useRef, useState, type ChangeEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { Button } from "@/components/ui/button";
import { errorMessage } from "@/lib/api/errors";
import { useBrandAssetMutations } from "@/lib/api/hooks";

const RULES = {
  logo: {
    label: "Logo",
    accept: "image/png,image/jpeg,image/webp",
    types: ["image/png", "image/jpeg", "image/webp"],
    maxBytes: 1024 * 1024,
    help: "PNG, JPEG or WebP, up to 1 MB. Shown on the login page and in the sidebar.",
  },
  favicon: {
    label: "Favicon",
    accept: "image/png,image/x-icon,image/vnd.microsoft.icon,image/webp,.ico",
    types: ["image/png", "image/x-icon", "image/vnd.microsoft.icon", "image/webp"],
    maxBytes: 256 * 1024,
    help: "PNG, ICO or WebP, up to 256 KB (a square 32×32 or 64×64 image works best). Shown in the browser tab.",
  },
} as const;

/**
 * Upload / replace / remove the company logo or favicon. The file goes to the
 * server (and on to object storage); the new image is applied across the app
 * immediately.
 */
export function BrandAssetUpload({
  asset,
  current,
  canEdit,
}: {
  asset: "logo" | "favicon";
  current: string | null;
  canEdit: boolean;
}): JSX.Element {
  const rules = RULES[asset];
  const { upload, remove } = useBrandAssetMutations();
  const { toast } = useAlerts();
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [error, setError] = useState<string | null>(null);
  const busy = upload.isPending || remove.isPending;
  const inputId = `${asset}-upload`;

  async function onFile(e: ChangeEvent<HTMLInputElement>): Promise<void> {
    const file = e.target.files?.[0];
    e.target.value = ""; // allow choosing the same file again
    if (!file) return;
    setError(null);

    // Quick checks for a friendly message; the server re-checks everything.
    const isIco = file.name.toLowerCase().endsWith(".ico");
    if (!(rules.types as readonly string[]).includes(file.type) && !(asset === "favicon" && isIco)) {
      setError(asset === "logo" ? "Please choose a PNG, JPEG or WebP image." : "Please choose a PNG, ICO or WebP image.");
      return;
    }
    if (file.size > rules.maxBytes) {
      setError(asset === "logo" ? "The logo must be smaller than 1 MB." : "The favicon must be smaller than 256 KB.");
      return;
    }

    try {
      await upload.mutateAsync({ asset, file });
      toast({ title: `${rules.label} updated`, tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  async function onRemove(): Promise<void> {
    setError(null);
    try {
      await remove.mutateAsync(asset);
      toast({ title: `${rules.label} removed`, tone: "info" });
    } catch (err) {
      setError(errorMessage(err));
    }
  }

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium text-foreground">{rules.label}</p>
      <div className="flex items-center gap-3">
        <div
          className={`flex shrink-0 items-center justify-center overflow-hidden rounded-md border border-border bg-muted/40 ${
            asset === "logo" ? "h-14 w-36" : "h-14 w-14"
          }`}
        >
          {current ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={current}
              alt={`Current ${rules.label.toLowerCase()}`}
              className={asset === "logo" ? "max-h-12 max-w-[8.5rem] object-contain" : "h-8 w-8 object-contain"}
            />
          ) : (
            <span className="text-xs text-muted-foreground">None</span>
          )}
        </div>

        {canEdit ? (
          <div className="flex flex-wrap gap-2">
            <input
              ref={inputRef}
              id={inputId}
              type="file"
              accept={rules.accept}
              className="sr-only"
              aria-label={`Upload ${rules.label.toLowerCase()}`}
              onChange={(e) => void onFile(e)}
            />
            <Button type="button" size="sm" disabled={busy} onClick={() => inputRef.current?.click()}>
              {upload.isPending ? "Uploading…" : current ? "Replace" : "Upload"}
            </Button>
            {current ? (
              <Button type="button" size="sm" variant="secondary" disabled={busy} onClick={() => void onRemove()}>
                {remove.isPending ? "Removing…" : "Remove"}
              </Button>
            ) : null}
          </div>
        ) : null}
      </div>
      <p className="text-xs text-muted-foreground">{rules.help}</p>
      {error ? (
        <p className="text-sm text-danger" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  );
}
