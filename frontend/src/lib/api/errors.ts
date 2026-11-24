import { ApiError } from "./types";

/** Human-readable message from any thrown value, preferring the API envelope. */
export function errorMessage(err: unknown): string {
  if (err instanceof ApiError) {
    // Surface the first field error when the backend returned validation details.
    if (err.details && typeof err.details === "object") {
      // Validation errors live in `details.fields` (or a bare field map); other details (e.g. debug `exception`) are not user messages.
      const d = err.details as Record<string, unknown>;
      const fields = d.fields && typeof d.fields === "object" ? (d.fields as Record<string, unknown>) : d;
      const first = Object.values(fields).find((v) => Array.isArray(v) && typeof v[0] === "string");
      if (Array.isArray(first) && typeof first[0] === "string") return first[0];
      if (typeof first === "string") return first;
    }
    return err.message;
  }
  if (err instanceof Error) return err.message;
  return "Something went wrong.";
}
