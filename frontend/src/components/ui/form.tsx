import { Button } from "./button";
import { Input, Label } from "./primitives";

/**
 * Shared form building blocks reused across pages: a labelled text field, a
 * labelled select, and a pagination control. Kept here (not in page files) so
 * Next.js page modules only export their default component.
 */

export function Field({
  label,
  value,
  onChange,
  type = "text",
  required = false,
  step,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  type?: string;
  required?: boolean;
  /** Number inputs only: e.g. "any" to allow decimal quantities. */
  step?: string;
}): JSX.Element {
  const id = label.toLowerCase().replace(/\s+/g, "-");
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>
        {label}
        {required ? " *" : ""}
      </Label>
      <Input
        id={id}
        type={type}
        step={step}
        required={required}
        value={value}
        onChange={(e) => onChange(e.target.value)}
      />
    </div>
  );
}

export function Select({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  options: { value: string; label: string }[];
}): JSX.Element {
  const id = label.toLowerCase().replace(/\s+/g, "-");
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="block text-sm font-medium text-foreground">
        {label}
      </label>
      <select
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
      >
        <option value="">— none —</option>
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
    </div>
  );
}

export function Pagination({
  page,
  lastPage,
  onChange,
}: {
  page: number;
  lastPage: number;
  onChange: (page: number) => void;
}): JSX.Element {
  // Never render "NaN"/blank if a response omits its paging fields.
  const current = Number.isFinite(page) && page >= 1 ? page : 1;
  const last = Math.max(Number.isFinite(lastPage) ? lastPage : 1, current, 1);

  return (
    <div className="flex items-center justify-between">
      <Button variant="ghost" disabled={current <= 1} onClick={() => onChange(current - 1)}>
        Previous
      </Button>
      <span className="text-sm text-muted-foreground">
        Page {current} of {last}
      </span>
      <Button variant="ghost" disabled={current >= last} onClick={() => onChange(current + 1)}>
        Next
      </Button>
    </div>
  );
}
