"use client";

import { useRef, useState, type ChangeEvent } from "react";

import { useAlerts } from "@/components/providers/alerts-provider";
import { useAuth } from "@/components/providers/auth-provider";
import { Avatar } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Card, PageHeader } from "@/components/ui/primitives";
import { profileApi } from "@/lib/api/endpoints";
import { errorMessage } from "@/lib/api/errors";

/** Output size of the stored picture (square, in pixels). */
const AVATAR_SIZE = 256;
/** Largest original file we accept before resizing. */
const MAX_INPUT_BYTES = 10 * 1024 * 1024;

/**
 * My profile — every signed-in user (any role) can set or remove their own
 * profile picture. The picture is centre-cropped to a square and resized to
 * 256×256 in the browser, so a large phone photo uploads as a small JPEG.
 */
export default function ProfilePage(): JSX.Element {
  const { identity, setAvatar } = useAuth();
  const { toast } = useAlerts();
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!identity) return <></>;

  async function onFile(e: ChangeEvent<HTMLInputElement>): Promise<void> {
    const file = e.target.files?.[0];
    e.target.value = ""; // allow picking the same file again
    if (!file) return;

    setError(null);
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
      setError("Please choose a JPEG, PNG or WebP image.");
      return;
    }
    if (file.size > MAX_INPUT_BYTES) {
      setError("That image is larger than 10 MB. Please choose a smaller one.");
      return;
    }

    setBusy(true);
    try {
      const blob = await squareResize(file, AVATAR_SIZE);
      const res = await profileApi.uploadAvatar(blob, "avatar.jpg");
      setAvatar(res.data.avatar);
      toast({ title: "Profile picture updated", tone: "success" });
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onRemove(): Promise<void> {
    setError(null);
    setBusy(true);
    try {
      await profileApi.removeAvatar();
      setAvatar(null);
      toast({ title: "Profile picture removed", tone: "info" });
    } catch (err) {
      setError(errorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader title="My profile" description="Your account details and profile picture." />

      <Card>
        <div className="flex flex-col gap-6 sm:flex-row sm:items-center">
          <Avatar name={identity.name} src={identity.avatar} size={96} />

          <div className="min-w-0 flex-1 space-y-1">
            <p className="text-lg font-semibold text-foreground">{identity.name}</p>
            <p className="text-sm text-muted-foreground">{identity.email}</p>
            <p className="text-sm text-muted-foreground">
              {identity.roles.join(", ") || "—"}
              {identity.party ? ` · ${identity.party.name}` : ""}
            </p>
          </div>
        </div>

        <div className="mt-6 space-y-3 border-t border-border pt-6">
          <h2 className="text-sm font-semibold text-foreground">Profile picture</h2>
          <p className="text-sm text-muted-foreground">
            JPEG, PNG or WebP. It is cropped to a square and shown in the top bar and sidebar.
          </p>

          <input
            ref={inputRef}
            id="avatar-input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            className="sr-only"
            onChange={(e) => void onFile(e)}
          />

          <div className="flex flex-wrap gap-2">
            <Button disabled={busy} onClick={() => inputRef.current?.click()}>
              {busy ? "Saving…" : identity.avatar ? "Change picture" : "Upload picture"}
            </Button>
            {identity.avatar ? (
              <Button variant="secondary" disabled={busy} onClick={() => void onRemove()}>
                Remove
              </Button>
            ) : null}
          </div>

          {error ? (
            <p className="text-sm text-danger" role="alert">
              {error}
            </p>
          ) : null}
        </div>
      </Card>
    </div>
  );
}

/**
 * Centre-crop an image to a square and scale it to `size`×`size`, returning a
 * JPEG blob. Runs entirely in the browser.
 */
async function squareResize(file: File, size: number): Promise<Blob> {
  const url = URL.createObjectURL(file);
  try {
    const img = await new Promise<HTMLImageElement>((resolve, reject) => {
      const el = new Image();
      el.onload = () => resolve(el);
      el.onerror = () => reject(new Error("That file could not be read as an image."));
      el.src = url;
    });

    const side = Math.min(img.naturalWidth, img.naturalHeight);
    if (side < 32) throw new Error("That image is too small (minimum 32×32 pixels).");
    const sx = (img.naturalWidth - side) / 2;
    const sy = (img.naturalHeight - side) / 2;

    const canvas = document.createElement("canvas");
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext("2d");
    if (!ctx) throw new Error("Your browser could not process the image.");
    // White backdrop so transparent PNGs don't turn black as JPEG.
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, size, size);
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(img, sx, sy, side, side, 0, 0, size, size);

    return await new Promise<Blob>((resolve, reject) => {
      canvas.toBlob(
        (blob) => (blob ? resolve(blob) : reject(new Error("Could not encode the image."))),
        "image/jpeg",
        0.88,
      );
    });
  } finally {
    URL.revokeObjectURL(url);
  }
}
