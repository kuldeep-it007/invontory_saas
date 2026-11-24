"use client";

/** Last-resort boundary for failures in the root layout itself (must render its own <html>). */
export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}): JSX.Element {
  return (
    <html lang="en">
      <body style={{ fontFamily: "system-ui, sans-serif", textAlign: "center", padding: "4rem 1rem" }}>
        <h1>Something went wrong</h1>
        <p>The application could not load. Please try again.</p>
        {error.digest ? <p style={{ color: "#666" }}>Reference: {error.digest}</p> : null}
        <button onClick={reset}>Try again</button>
      </body>
    </html>
  );
}