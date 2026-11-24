"use client";

import { useEffect } from "react";

import { Button } from "@/components/ui/button";

/** Route-segment error boundary: keeps the rest of the shell usable and offers a retry. */
export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}): JSX.Element {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <div className="mx-auto max-w-md space-y-4 py-16 text-center" role="alert">
      <h1 className="text-lg font-semibold">Something went wrong</h1>
      <p className="text-sm text-muted-foreground">
        This page failed to load. You can try again, and if it keeps happening contact support
        {error.digest ? ` with reference ${error.digest}` : ""}.
      </p>
      <Button onClick={reset}>Try again</Button>
    </div>
  );
}