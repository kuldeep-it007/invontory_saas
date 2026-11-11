import { ApiError } from "./types";

/** Human-readable message from any thrown value, preferring the API envelope. */
export function errorMessage(err: unknown): string {
  if (err instanceof ApiError) {
    // Surface the first field error when the backend returned validation details.
    if (err.details && typeof err.details === "object") {
      const first = Object.values(err.details)[0];
      if (Array.isArray(first) && typeof first[0] === "string") return first[0];
      if (typeof first === "string") return first;
    }
    return err.message;
  }
  if (err instanceof Error) return err.message;
  return "Something went wrong.";
}
