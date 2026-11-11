/**
 * Normalises the paging info of any list response the API returns, so a
 * `<Pagination>` never receives `undefined`/`NaN`.
 *
 * The backend uses three shapes:
 *   • Laravel paginator:  `{ data, current_page, last_page, per_page, total }`
 *   • Meta envelope:      `{ data, meta: { current_page, last_page, ... } }`
 *                         (notifications)
 *   • Report envelope:    `{ data, meta: { page, per_page, total, has_more } }`
 *                         (no `last_page`; derived from total / per_page, or
 *                         from `has_more` when the total is not counted)
 */
export interface PageInfo {
  page: number;
  lastPage: number;
}

function num(value: unknown): number | null {
  if (value === null || value === undefined || value === "") return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

export function pageInfo(response: unknown): PageInfo {
  const root = (response ?? {}) as Record<string, unknown>;
  const meta = (root.meta ?? {}) as Record<string, unknown>;

  const page = Math.max(1, num(root.current_page) ?? num(meta.current_page) ?? num(meta.page) ?? 1);

  const perPage = num(root.per_page) ?? num(meta.per_page);
  const total = num(root.total) ?? num(meta.total);

  let lastPage = num(root.last_page) ?? num(meta.last_page);
  if (lastPage === null && total !== null && perPage !== null && perPage > 0) {
    lastPage = Math.ceil(total / perPage);
  }
  if (lastPage === null) {
    lastPage = meta.has_more === true ? page + 1 : page;
  }

  return { page, lastPage: Math.max(1, page, lastPage) };
}
